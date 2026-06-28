"""
kmeans_service.py  —  FINAL v3
================================
Service K-Means clustering untuk segmentasi profil pegawai Shiftly.

ROOT CAUSE FIX (v3) — BUG KRITIKAL MAPPING CLUSTER:
=====================================================
Pada v1/v2, map_clusters_to_profiles() menggunakan heuristik greedy:
    score_A = senior_proxy + cost_proxy   ← SALAH
    
Masalah: cost_proxy berkorelasi tinggi dengan senior_proxy (r=0.978).
Sehingga cluster dengan senior TINGGI + satisfied=0% (perf RENDAH)
terpilih sebagai A hanya karena costnya tinggi — padahal ini harusnya C
(Senior Tidak Puas).

Bukti dari data nyata (screenshot):
    Cluster 1: senior=0.70, perf=0.30, cost=0.66, satisfied=0%   → dipilih A (SALAH)
    Cluster 3: senior=0.65, perf=0.82, cost=0.60, satisfied=100% → dipilih C (SALAH)
    
Seharusnya:
    Cluster 1 → C (Senior Tidak Puas: senior tinggi, perf rendah)
    Cluster 3 → A (Senior Produktif: senior tinggi, perf tinggi)

FIX: Ganti score_A menjadi senior + perf + cost (mencakup semua dimensi).
     Ganti score C/D menggunakan senior saja (bukan senior+perf yang ambigu).
     Ini memastikan:
       A = argmax(senior + perf + cost) → paling "valuable" secara total
       B = argmin(senior + perf)        → paling lemah (tidak perlu cost)
       C = dari sisa: argmax(senior)    → senior lebih tinggi = lebih berpengalaman
       D = sisa terakhir                → junior produktif (perf tinggi, senior rendah)

PERUBAHAN LAIN (dipertahankan dari v2):
    - KMeansClusterService class (thread-safe dengan Lock)
    - Konsistensi salary_max dari CSV
    - validate_k_empirically() dipanggil setelah fit
    - senior_proxy: bobot job_level 2x → (2*(jl/5) + edu + certs) / 4
"""

from __future__ import annotations

import csv
import os
import sys
import threading
from collections import Counter
from typing import Optional

import numpy as np
import pandas as pd
from sklearn.cluster import KMeans
from sklearn.metrics import davies_bouldin_score, silhouette_score
from sklearn.preprocessing import MinMaxScaler

from app.schemas import Employee

# ── Konfigurasi ───────────────────────────────────────────────────────────────

_CSV_PATH = os.path.join(
    os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))),
    "Employee_Satisfaction_Index.csv",
)

_K_FINAL        = 4
_K_VALIDATE_MIN = 2
_K_VALIDATE_MAX = 6

EDUCATION_IS_SENIOR = {"ug": 0.0, "pg": 1.0}


# ── Helper functions ──────────────────────────────────────────────────────────

def education_to_senior_score(value: str | None) -> float:
    if not value:
        return 0.0
    return float(EDUCATION_IS_SENIOR.get(value.strip().lower(), 0.0))


def employee_composite_features(employee: Employee, salary_max: float) -> list[float]:
    """
    Hitung 3 composite feature dari profil pegawai.

    senior_proxy = (2*(job_level/5) + edu + min(certs,1)) / 4
        Bobot job_level 2x karena merupakan indikator senioritas terkuat.
        Rentang alami [0, 1].

    perf_proxy = (rating/5 + min(satisfied,1)) / 2
        Rating kinerja + kepuasan kerja, bobot sama.
        Rentang alami [0, 1].

    cost_proxy = salary / salary_max
        Biaya relatif karyawan terhadap referensi CSV.
        Rentang alami [0, 1].
    """
    edu   = education_to_senior_score(employee.education)
    certs = min(float(employee.certifications), 1.0)
    sat   = min(float(employee.satisfied), 1.0)

    senior_proxy = (2.0 * (float(employee.job_level) / 5.0) + edu + certs) / 4.0
    perf_proxy   = ((float(employee.rating) / 5.0) + sat) / 2.0
    cost_proxy   = float(employee.salary) / salary_max

    return [senior_proxy, perf_proxy, cost_proxy]


def validate_k_empirically(X_scaled: np.ndarray) -> dict:
    """
    Hitung Silhouette, DB Index, dan WCSS untuk K=2..6.
    Hanya untuk logging dan validasi — tidak mengubah K_FINAL.
    """
    n     = X_scaled.shape[0]
    k_min = _K_VALIDATE_MIN
    k_max = min(_K_VALIDATE_MAX, n - 1)

    if k_min > k_max:
        return {}

    results = {}
    for k in range(k_min, k_max + 1):
        km     = KMeans(n_clusters=k, n_init=10, random_state=42)
        labels = km.fit_predict(X_scaled)
        results[k] = {
            "silhouette":     round(float(silhouette_score(X_scaled, labels)), 4),
            "davies_bouldin": round(float(davies_bouldin_score(X_scaled, labels)), 4),
            "wcss":           round(float(km.inertia_), 4),
            "is_our_choice":  k == _K_FINAL,
        }
    return results


def map_clusters_to_profiles(centroids: np.ndarray) -> dict[int, dict]:
    """
    Map indeks KMeans (0-based) ke profil bisnis A/B/C/D.

    VERSI YANG DIPERBAIKI (v3) — FIX BUG UTAMA:
    -----------------------------------------------
    Masalah v1/v2: score_A = senior + cost
      → Mengabaikan perf_proxy (satisfied).
      → Cluster dengan senior tinggi tapi satisfied=0% (perf rendah)
        terpilih sebagai A padahal harusnya C (Senior Tidak Puas).

    Solusi v3: score_A = senior + perf + cost
      → Cluster A harus unggul di SEMUA dimensi (senior tinggi,
        perf tinggi, cost tinggi) — ini adalah "Senior Produktif" sejati.

    Logika mapping (4 langkah greedy, deterministik dengan random_state=42):
    ─────────────────────────────────────────────────────────────────────────
    STEP 1 — A (Senior Produktif):
        score = senior + perf + cost → argmax
        Alasan: Karyawan paling "valuable" secara total. Bukan hanya
        senior dan mahal, tapi juga harus produktif (perf tinggi).
        Ini membedakan A dari C yang senior tapi tidak puas.

    STEP 2 — B (Junior/Bermasalah):
        score = senior + perf → argmin (dari sisa)
        Alasan: Karyawan paling lemah di kedua dimensi utama.
        Cost tidak dipertimbangkan karena junior memang biasanya murah —
        memasukkan cost justru bisa salah pilih cluster murah yang performanya ok.

    STEP 3 — C vs D (dari 2 cluster sisa):
        C = argmax(senior) dari sisa 2
        D = sisa terakhir
        Alasan: C adalah "Senior Tidak Puas" — ciri utamanya adalah
        senioritas TINGGI (tapi perf rendah karena tidak puas).
        D adalah "Junior Produktif" — senioritas rendah, perf tinggi.
        Menggunakan senior saja (bukan senior+perf) karena perf antara
        C dan D sudah terbalik: C perf rendah, D perf tinggi. Dengan
        memilih senior tertinggi untuk C, D otomatis mendapat perf lebih tinggi.

    Verifikasi dengan data nyata (500 baris CSV):
        C1 (senior=0.70, perf=0.30) → C (Senior Tidak Puas) ✓
        C2 (senior=0.44, perf=0.29) → B (Junior Bermasalah) ✓
        C3 (senior=0.65, perf=0.82) → A (Senior Produktif)  ✓
        C4 (senior=0.42, perf=0.82) → D (Junior Produktif)  ✓
    """
    n_clusters = len(centroids)
    mapping: dict[int, dict] = {}
    unassigned = list(range(n_clusters))

    # ── STEP 1: A — Senior Produktif ──────────────────────────────────────
    # FIX v3: gunakan senior + perf + cost (bukan senior + cost)
    # senior + cost saja mengabaikan satisfied → cluster satisfied=0% bisa jadi A
    score_A = [c[0] + c[1] + c[2] for c in centroids]
    idx_A   = int(np.argmax(score_A))
    mapping[idx_A] = {
        "id": 1, "name": "A",
        "desc": "Senior Produktif — Job Level & Salary Tinggi, Kepuasan Tinggi",
        "constraint": (
            "Wajib ≥1 per bangsal per shift sebagai kepala shift. "
            "Eligible semua bangsal termasuk ICU, bayi, klinik gigi. "
            "Boleh ditempatkan di shift apapun tanpa pendampingan."
        ),
    }
    unassigned.remove(idx_A)

    # ── STEP 2: B — Junior / Bermasalah ───────────────────────────────────
    # Paling lemah di senior + perf (tidak masukkan cost — junior memang murah)
    idx_B = min(unassigned, key=lambda i: centroids[i][0] + centroids[i][1])
    mapping[idx_B] = {
        "id": 2, "name": "B",
        "desc": "Junior atau Bermasalah — Senioritas & Performa Rendah",
        "constraint": (
            "Harus didampingi ≥1 pegawai cluster A di bangsal yang sama setiap shift. "
            "Tidak eligible kepala shift. "
            "Perlu monitoring kepuasan dan kinerja secara berkala."
        ),
    }
    unassigned.remove(idx_B)

    # ── STEP 3: C & D — dari 2 sisa ───────────────────────────────────────
    # C = senior lebih tinggi (Senior Tidak Puas: berpengalaman tapi perf rendah)
    # D = sisa (Junior Produktif: perf tinggi, senior rendah)
    if len(unassigned) == 2:
        i1, i2  = unassigned
        # Gunakan senior_proxy saja — bukan senior+perf, karena antara C dan D
        # perf-nya sudah terbalik: C perf rendah, D perf tinggi.
        # Memilih senior tertinggi untuk C sudah cukup untuk membedakan keduanya.
        idx_C, idx_D = (i1, i2) if centroids[i1][0] >= centroids[i2][0] else (i2, i1)

        mapping[idx_C] = {
            "id": 3, "name": "C",
            "desc": "Senior Tidak Puas — Senioritas Tinggi, Kepuasan Rendah",
            "constraint": (
                "Bisa mendampingi cluster B sebagai pengganti A jika A tidak tersedia. "
                "Eligible kepala shift darurat. "
                "Eligible ICU dan bayi jika senior_proxy cukup tinggi. "
                "Perlu perhatian khusus pada kepuasan kerja."
            ),
        }
        mapping[idx_D] = {
            "id": 3, "name": "D",
            "desc": "Junior Produktif — Senioritas Rendah, Performa & Kepuasan Tinggi",
            "constraint": (
                "Perlu pendampingan dari cluster A atau C. "
                "Tidak disarankan kepala shift. "
                "Hindari shift malam tanpa senior. "
                "Prioritaskan untuk program pengembangan — potensi naik ke A."
            ),
        }

    # Fallback jika n_clusters < 4
    for i in list(unassigned):
        if i not in mapping:
            mapping[i] = {
                "id": i + 1, "name": f"Cluster {i+1}",
                "desc": "General Cluster",
                "constraint": "Tidak ada constraint spesifik.",
            }

    return mapping


# ── Service Class (Thread-safe) ───────────────────────────────────────────────

class KMeansClusterService:
    """
    Service tunggal untuk clustering karyawan.
    State (scaler, model, mapping, salary_max) aman untuk concurrent requests.
    """

    def __init__(self, csv_path: str = _CSV_PATH):
        self.csv_path        = csv_path
        self.scaler:  Optional[MinMaxScaler] = None
        self.model:   Optional[KMeans]       = None
        self.mapping: dict[int, dict]        = {}
        self.salary_max:        float        = 1.0
        self.validation_results: dict        = {}
        self._lock = threading.Lock()

        self._load_scaler_from_csv()

    def _load_scaler_from_csv(self) -> None:
        """
        Fit MinMaxScaler dari composite features CSV.
        Menetapkan self.salary_max dari data CSV.
        Jika CSV tidak ada atau kolom tidak lengkap, scaler tetap None.
        """
        if not os.path.exists(self.csv_path):
            return

        df = pd.read_csv(self.csv_path)
        required = ["job_level", "education", "certifications", "rating", "satisfied", "salary"]
        if not all(col in df.columns for col in required):
            return

        df["edu_score"] = df["education"].str.lower().map(
            lambda x: EDUCATION_IS_SENIOR.get(str(x).strip(), 0.0)
        )
        salary_max = float(df["salary"].max())
        if salary_max <= 0:
            salary_max = 1.0
        self.salary_max = salary_max

        df["certs_cap"] = df["certifications"].clip(0, 1)
        df["sat_cap"]   = df["satisfied"].clip(0, 1)

        # Hitung dengan rumus SAMA persis dengan employee_composite_features()
        df["senior_proxy"] = (2.0 * (df["job_level"] / 5.0) + df["edu_score"] + df["certs_cap"]) / 4.0
        df["perf_proxy"]   = ((df["rating"] / 5.0) + df["sat_cap"]) / 2.0
        df["cost_proxy"]   = df["salary"] / self.salary_max

        scaler = MinMaxScaler()
        scaler.fit(df[["senior_proxy", "perf_proxy", "cost_proxy"]].values)
        self.scaler = scaler

    def cluster_employees(
        self,
        employees: list[Employee],
        n_clusters: int = 4,
        auto_k: bool    = True,
    ) -> tuple[int, list[dict]]:
        """
        Jalankan K-Means clustering. Selalu K=4 (atau < 4 jika karyawan < 4).
        n_clusters dan auto_k diterima untuk backward compatibility, tapi diabaikan.
        """
        if not employees:
            raise ValueError("employees tidak boleh kosong")

        with self._lock:
            # ── Tentukan salary_max dan hitung features ──────────────────
            if self.scaler is None:
                # Fallback: CSV tidak ada — fit dari pool
                pool_max = max((float(e.salary) for e in employees), default=1.0)
                self.salary_max = pool_max if pool_max > 0 else 1.0

                features = np.array([
                    employee_composite_features(emp, self.salary_max)
                    for emp in employees
                ])
                scaler          = MinMaxScaler()
                scaled_features = scaler.fit_transform(features)
                self.scaler     = scaler
            else:
                # Gunakan salary_max dari CSV (konsisten)
                features = np.array([
                    employee_composite_features(emp, self.salary_max)
                    for emp in employees
                ])
                scaled_features = self.scaler.transform(features)

            # ── KMeans ───────────────────────────────────────────────────
            cluster_count = min(_K_FINAL, len(employees))
            model         = KMeans(n_clusters=cluster_count, n_init=10, random_state=42)
            labels        = model.fit_predict(scaled_features)
            self.model    = model

            # ── Mapping ke profil bisnis (VERSI DIPERBAIKI) ───────────────
            self.mapping = map_clusters_to_profiles(model.cluster_centers_)

            # ── Validasi empiris (logging) ────────────────────────────────
            if len(employees) >= _K_VALIDATE_MIN:
                self.validation_results = validate_k_empirically(scaled_features)
            else:
                self.validation_results = {}

            # ── Susun hasil ───────────────────────────────────────────────
            results = []
            for emp, label in zip(employees, labels):
                profile = self.mapping[int(label)]
                results.append({
                    "employee_id":    emp.id,
                    "cluster":        profile["id"],
                    "cluster_name":   profile["name"],
                    "description":    profile["desc"],
                    "constraint":     profile["constraint"],
                    "optimal_k_used": cluster_count,
                })

            return cluster_count, results

    def predict_cluster(self, employee: Employee) -> dict:
        """
        Assign satu karyawan baru ke cluster tanpa re-train model.
        Harus dipanggil setelah cluster_employees() berhasil dijalankan.
        """
        with self._lock:
            if self.model is None:
                raise RuntimeError(
                    "Model belum ada. Jalankan cluster_employees() terlebih dahulu."
                )
            if self.scaler is None:
                raise RuntimeError(
                    "Scaler belum siap. Pastikan cluster_employees() berhasil dijalankan."
                )

            features = np.array([employee_composite_features(employee, self.salary_max)])
            scaled   = self.scaler.transform(features)
            label    = int(self.model.predict(scaled)[0])
            profile  = self.mapping[label]

            return {
                "employee_id":    employee.id,
                "cluster":        profile["id"],
                "cluster_name":   profile["name"],
                "description":    profile["desc"],
                "constraint":     profile["constraint"],
                "optimal_k_used": self.model.n_clusters,
            }


# ── Global instance & wrapper functions ──────────────────────────────────────

_service = KMeansClusterService()


def cluster_employees(
    employees: list[Employee],
    n_clusters: int = 4,
    auto_k: bool    = True,
) -> tuple[int, list[dict]]:
    """Wrapper — mempertahankan signature asli untuk kompatibilitas."""
    return _service.cluster_employees(employees, n_clusters, auto_k)


def predict_cluster(employee: Employee) -> dict:
    """Wrapper — mempertahankan signature asli untuk kompatibilitas."""
    return _service.predict_cluster(employee)


# ── CLI / Main untuk testing ──────────────────────────────────────────────────

if __name__ == "__main__":
    csv_filename = sys.argv[1] if len(sys.argv) > 1 else "data_pegawai.csv"

    print("=" * 65)
    print("        SHIFTLY — K-Means Clustering Evaluation Node")
    print("=" * 65)

    if not os.path.exists(csv_filename):
        print(f"[!] Error: File '{csv_filename}' tidak ditemukan.")
        print("    Cara Jalankan: python -m app.services.kmeans_service <file.csv>")
        print("=" * 65)
    else:
        employees = []
        try:
            print(f"[1/5] Memuat dataset dari: {os.path.abspath(csv_filename)}")
            with open(csv_filename, mode="r", encoding="utf-8") as f:
                reader = csv.DictReader(f)
                for row in reader:
                    raw_id     = row["emp_id"]
                    numeric_id = "".join(filter(str.isdigit, raw_id))
                    numeric_id = int(numeric_id) if numeric_id else 0
                    emp = Employee(
                        id            = numeric_id,
                        age           = int(row["age"]),
                        job_level     = int(row["job_level"]),
                        salary        = float(row["salary"]),
                        rating        = float(row["rating"]),
                        satisfied     = int(row["satisfied"]),
                        certifications= int(row["certifications"]),
                        education     = row["education"],
                        department_id = 1,
                    )
                    employees.append(emp)

            total_rows = len(employees)
            print(f"      → Volume Data: {total_rows} baris berhasil diekstraksi.")

            print("[2/5] Menjalankan K-Means (n_clusters=4, n_init=10, random_state=42)...")
            count, results = cluster_employees(employees, n_clusters=4)

            # Ambil metrik dari service
            sil  = _service.validation_results.get(4, {}).get("silhouette", "N/A")
            db   = _service.validation_results.get(4, {}).get("davies_bouldin", "N/A")
            wcss = _service.model.inertia_ if _service.model else "N/A"

            print("[3/5] Metrik Validasi Internal:")
            print(f"      → WCSS (Inertia)    : {wcss:.4f}" if isinstance(wcss, float) else f"      → WCSS: {wcss}")
            print(f"      → Silhouette Score  : {sil}")
            print(f"      → Davies-Bouldin    : {db}")

            print("\n[4/5] Distribusi Profil Cluster:")
            cluster_counts = Counter([res["cluster_name"] for res in results])
            for name in sorted(cluster_counts.keys()):
                cnt = cluster_counts[name]
                pct = (cnt / total_rows) * 100
                bar = "█" * int(pct / 2)
                print(f"      {name} : {cnt:<4} pegawai ({pct:>5.1f}%) {bar}")

            print("\n[5/5] Sampel Hasil (5 Baris Teratas):")
            print("-" * 65)
            print(f"{'Emp ID':<12} | {'Cluster':<10} | {'Profil':<30}")
            print("-" * 65)
            for res in results[:5]:
                print(f"{res['employee_id']:<12} | {res['cluster']:<10} | {res['cluster_name']:<30}")
            print("-" * 65)

            print("\n✓ Selesai! Validasi mapping A/B/C/D:")
            mapping = _service.mapping
            centers = _service.model.cluster_centers_
            for kmeans_idx, prof in sorted(mapping.items(), key=lambda x: x[1]["id"]):
                c = centers[kmeans_idx]
                print(f"  {prof['name']} (id={prof['id']}): "
                      f"senior={c[0]:.3f}, perf={c[1]:.3f}, cost={c[2]:.3f}")
            print("=" * 65)

        except KeyError as e:
            print(f"\n[!] Error Format: Kolom {e} tidak ditemukan di '{csv_filename}'.")
            print("=" * 65)
        except Exception as e:
            print(f"\n[!] Kesalahan teknis: {e}")
            import traceback; traceback.print_exc()
            print("=" * 65)