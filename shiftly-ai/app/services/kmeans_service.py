from __future__ import annotations

import os
import threading
from typing import Optional

import numpy as np
import pandas as pd
from sklearn.cluster import KMeans
from sklearn.metrics import davies_bouldin_score, silhouette_score
from sklearn.preprocessing import MinMaxScaler

from app.schemas import Employee

# ── Konfigurasi ──────────────────────────────────────────────────────────────

_CSV_PATH = os.path.join(
    os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))),
    "Employee_Satisfaction_Index.csv",
)

_K_FINAL = 4
_K_VALIDATE_MIN = 2
_K_VALIDATE_MAX = 6

EDUCATION_IS_SENIOR = {"ug": 0.0, "pg": 1.0}


# ── Helper functions ──────────────────────────────────────────────────────────────

def education_to_senior_score(value: str | None) -> float:
    if not value:
        return 0.0
    return float(EDUCATION_IS_SENIOR.get(value.strip().lower(), 0.0))


def employee_composite_features(employee: Employee, salary_max: float) -> list[float]:
    edu = education_to_senior_score(employee.education)
    certs = min(float(employee.certifications), 1.0)
    sat = min(float(employee.satisfied), 1.0)

    senior_proxy = (2.0 * (float(employee.job_level) / 5.0) + edu + certs) / 4.0
    perf_proxy = ((float(employee.rating) / 5.0) + sat) / 2.0
    cost_proxy = float(employee.salary) / salary_max

    return [senior_proxy, perf_proxy, cost_proxy]


def map_clusters_to_profiles(centroids: np.ndarray) -> dict[int, dict]:

    n_clusters = len(centroids)
    mapping: dict[int, dict] = {}
    unassigned = list(range(n_clusters))

    # A — Senior Produktif
    score_A = [c[0] + c[2] for c in centroids] 
    # cara kerja score A: menentukan cluster A dengan menjumlahkan senior_proxy (c[0]) dan cost_proxy (c[2]) dari setiap centroid. Cluster dengan skor tertinggi akan dianggap sebagai cluster A, yang mewakili pegawai senior dan produktif dengan biaya tinggi.
    idx_A = int(np.argmax(score_A))
    mapping[idx_A] = {
        "id": 1,
        "name": "A",
        "desc": "Senior Produktif — Job Level & Salary Tinggi, Kepuasan Tinggi",
        "constraint": (
            "Wajib ≥1 per bangsal per shift sebagai kepala shift. "
            "Eligible semua bangsal termasuk ICU, bayi, klinik gigi. "
            "Boleh ditempatkan di shift apapun tanpa pendampingan."
        ),
    }
    unassigned.remove(idx_A)

    # B — Junior atau Bermasalah
    if unassigned:
        idx_B = min(unassigned, key=lambda i: centroids[i][0] + centroids[i][1])
        # cara kerja score B: menentukan cluster B dengan mencari indeks cluster yang memiliki jumlah senior_proxy (c[0]) dan perf_proxy (c[1]) terendah. Cluster ini mewakili pegawai yang lebih junior atau memiliki performa rendah, sehingga perlu pendampingan dari cluster A.
        mapping[idx_B] = {
            "id": 2,
            "name": "B",
            "desc": "Junior atau Bermasalah — Senioritas & Performa Rendah",
            "constraint": (
                "Harus didampingi ≥1 pegawai cluster A di bangsal yang sama setiap shift. "
                "Tidak eligible kepala shift. "
                "Perlu monitoring kepuasan dan kinerja secara berkala."
            ),
        }
        unassigned.remove(idx_B)

    # C & D — dua sisa, bedakan berdasarkan senior + perf
    if len(unassigned) == 2:
        i1, i2 = unassigned
        s1 = centroids[i1][0] + centroids[i1][1]
        s2 = centroids[i2][0] + centroids[i2][1]
        idx_C, idx_D = (i1, i2) if s1 >= s2 else (i2, i1)
        # cara kerja score C & D: dari dua cluster yang tersisa, cluster dengan jumlah senior_proxy (c[0]) dan perf_proxy (c[1]) lebih tinggi akan dianggap sebagai cluster C, sedangkan yang lebih rendah akan menjadi cluster D. Cluster
        mapping[idx_C] = {
            "id": 3,
            "name": "C",
            "desc": "Menengah Positif — Senioritas atau Performa Cukup",
            "constraint": (
                "Bisa mendampingi cluster B sebagai pengganti A jika A tidak tersedia. "
                "Eligible kepala shift darurat. "
                "Eligible ICU dan bayi jika senior_proxy cukup tinggi."
            ),
        }
        mapping[idx_D] = {
            "id": 4,
            "name": "D",
            "desc": "Menengah Perlu Perhatian — Senioritas atau Performa Belum Optimal",
            "constraint": (
                "Perlu pendampingan dari cluster A atau C. "
                "Tidak disarankan kepala shift. "
                "Hindari shift malam tanpa senior. "
                "Prioritaskan untuk program pengembangan."
            ),
        }

    # Fallback (n_clusters < 4)
    for i in list(unassigned):
        if i not in mapping:
            mapping[i] = {
                "id": i + 1,
                "name": f"Cluster {i+1}",
                "desc": "General Cluster",
                "constraint": "Tidak ada constraint spesifik.",
            }

    return mapping


def validate_k_empirically(X_scaled: np.ndarray) -> dict:
    """
    Hitung metrik untuk K=2..6 sebagai validasi.
    Hasil tidak mempengaruhi K final, hanya untuk logging.
    """
    n = X_scaled.shape[0]
    k_min = _K_VALIDATE_MIN
    k_max = min(_K_VALIDATE_MAX, n - 1)
    if k_min > k_max:
        return {}

    results = {}
    for k in range(k_min, k_max + 1):
        km = KMeans(n_clusters=k, n_init=10, random_state=42)
        labels = km.fit_predict(X_scaled)
        results[k] = {
            "silhouette": round(float(silhouette_score(X_scaled, labels)), 4),
            "davies_bouldin": round(float(davies_bouldin_score(X_scaled, labels)), 4),
            "wcss": round(float(km.inertia_), 2),
            "is_our_choice": k == _K_FINAL,
        }
    return results


# ── Service Class (Thread‑safe) ────────────────────────────────────────────

class KMeansClusterService:
    """
    Service tunggal untuk clustering, menyimpan state dan lock.
    """

    def __init__(self, csv_path: str = _CSV_PATH):
        self.csv_path = csv_path
        self.scaler: Optional[MinMaxScaler] = None
        self.model: Optional[KMeans] = None
        self.mapping: dict[int, dict] = {}
        self.salary_max: float = 1.0
        self.validation_results: dict = {}   # hasil validasi terakhir
        self._lock = threading.Lock()

        # Inisialisasi scaler dari CSV jika tersedia
        self._load_scaler_from_csv()

    def _load_scaler_from_csv(self) -> None:
        """
        Baca CSV, hitung composite features, fit MinMaxScaler.
        Set self.scaler dan self.salary_max dari CSV.
        Jika CSV tidak ada, scaler tetap None, salary_max tetap 1.0.
        """
        if not os.path.exists(self.csv_path):
            return

        df = pd.read_csv(self.csv_path)
        # Pastikan kolom yang dibutuhkan ada
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
        df["sat_cap"] = df["satisfied"].clip(0, 1)

        df["senior_proxy"] = (2.0 * (df["job_level"] / 5.0) + df["edu_score"] + df["certs_cap"]) / 4.0
        df["perf_proxy"] = ((df["rating"] / 5.0) + df["sat_cap"]) / 2.0
        df["cost_proxy"] = df["salary"] / self.salary_max

        scaler = MinMaxScaler()
        scaler.fit(df[["senior_proxy", "perf_proxy", "cost_proxy"]].values)
        self.scaler = scaler

    def cluster_employees(
        self,
        employees: list[Employee],
        n_clusters: int = 4,
        auto_k: bool = True,
    ) -> tuple[int, list[dict]]:
        """
        Jalankan clustering. Selalu gunakan K=4 (atau <4 jika pegawai <4).
        Parameter n_clusters dan auto_k diabaikan (backward compatibility).
        """
        if not employees:
            raise ValueError("employees tidak boleh kosong")

        with self._lock:
            # 1. Tentukan salary_max yang konsisten
            #    Jika scaler belum ada (CSV tidak ada), kita fit dari pool
            if self.scaler is None:
                # Fallback: fit scaler dari data pool dan set salary_max dari pool
                salaries = [float(e.salary) for e in employees]
                pool_salary_max = max(salaries) if salaries else 1.0
                if pool_salary_max <= 0:
                    pool_salary_max = 1.0
                self.salary_max = pool_salary_max

                # Hitung composite features dengan salary_max pool
                features = np.array([
                    employee_composite_features(emp, self.salary_max)
                    for emp in employees
                ])
                scaler = MinMaxScaler()
                scaled_features = scaler.fit_transform(features)
                self.scaler = scaler
            else:
                # Gunakan salary_max yang sudah ada (dari CSV)
                features = np.array([
                    employee_composite_features(emp, self.salary_max)
                    for emp in employees
                ])
                scaled_features = self.scaler.transform(features)

            # 2. Tentukan jumlah cluster
            cluster_count = min(_K_FINAL, len(employees))

            # 3. Jalankan KMeans
            model = KMeans(n_clusters=cluster_count, n_init=10, random_state=42)
            labels = model.fit_predict(scaled_features)
            self.model = model

            # 4. Mapping profil
            self.mapping = map_clusters_to_profiles(model.cluster_centers_)

            # 5. Validasi empiris (opsional, untuk logging)
            if len(employees) >= _K_VALIDATE_MIN:
                self.validation_results = validate_k_empirically(scaled_features)
            else:
                self.validation_results = {}

            # 6. Buat hasil
            results = []
            for emp, label in zip(employees, labels):
                profile = self.mapping[int(label)]
                results.append({
                    "employee_id": emp.id,
                    "cluster": profile["id"],
                    "cluster_name": profile["name"],
                    "description": profile["desc"],
                    "constraint": profile["constraint"],
                    "optimal_k_used": cluster_count,
                })

            return cluster_count, results

    def predict_cluster(self, employee: Employee) -> dict:
        """
        Assign satu pegawai baru ke cluster yang sudah ada.
        Harus sudah ada model (cluster_employees pernah dipanggil).
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

            features = np.array([
                employee_composite_features(employee, self.salary_max)
            ])
            scaled = self.scaler.transform(features)
            label = int(self.model.predict(scaled)[0])
            profile = self.mapping[label]

            return {
                "employee_id": employee.id,
                "cluster": profile["id"],
                "cluster_name": profile["name"],
                "description": profile["desc"],
                "constraint": profile["constraint"],
                "optimal_k_used": self.model.n_clusters,
            }


# ── Global service instance & exported functions ──────────────────────────

_service = KMeansClusterService()


def cluster_employees(
    employees: list[Employee],
    n_clusters: int = 4,
    auto_k: bool = True,
) -> tuple[int, list[dict]]:
    """
    Wrapper untuk KMeansClusterService.cluster_employees.
    Mempertahankan signature asli untuk kompatibilitas.
    """
    return _service.cluster_employees(employees, n_clusters, auto_k)


def predict_cluster(employee: Employee) -> dict:
    """
    Wrapper untuk KMeansClusterService.predict_cluster.
    """
    return _service.predict_cluster(employee)

if __name__ == "__main__":
    import sys
    import os
    import csv
    from collections import Counter
    
    # Menerima nama file CSV secara dinamis dari ketikan terminal
    csv_filename = sys.argv[1] if len(sys.argv) > 1 else "data_pegawai.csv"
    
    print("=" * 60)
    print("        SHIFTLY — K-Means Clustering Node Test")
    print("=" * 60)
    
    if not os.path.exists(csv_filename):
        print(f"[!] Error: File '{csv_filename}' tidak ditemukan.")
        print("    Cara Jalankan: python -m app.services.kmeans_service <nama_file.csv>")
        print("=" * 60)
    else:
        employees = []
        try:
            print(f"[1/4] Memuat data dari: {os.path.abspath(csv_filename)}")
            with open(csv_filename, mode='r', encoding='utf-8') as f:
                reader = csv.DictReader(f)
                for row in reader:
                    raw_id = row['emp_id']
                    numeric_id = ''.join(filter(str.isdigit, raw_id))
                    numeric_id = int(numeric_id) if numeric_id else 0

                    emp = Employee(
                        id=numeric_id,
                        age=int(row['age']),
                        job_level=int(row['job_level']),
                        salary=float(row['salary']),
                        rating=float(row['rating']),
                        satisfied=int(row['satisfied']),
                        certifications=int(row['certifications']),
                        education=row['education'],
                        department_id=1
                    )
                    employees.append(emp)
            
            total_rows = len(employees)
            print(f"      → Berhasil mengekstrak {total_rows} baris data pegawai.")
            
            print("[2/4] Mengeksekusi pipeline K-Means (n_clusters=4, n_init=10)...")
            count, results = cluster_employees(employees, n_clusters=4)
            
            # Menghitung distribusi cluster untuk statistik
            cluster_counts = Counter([res['cluster_name'] for res in results])
            
            print(f"[3/4] Clustering Selesai! Berhasil memetakan ke {count} Profil.")
            print("\n      ── Statistik Distribusi Klaster ──")
            for cluster_name in sorted(cluster_counts.keys()):
                cnt = cluster_counts[cluster_name]
                pct = (cnt / total_rows) * 100
                bar = "█" * int(pct / 2)
                print(f"      {cluster_name} : {cnt:<4} pegawai ({pct:>5.1f}%) {bar}")
            
            print("\n[4/4] Menyajikan Sampel Hasil Pemetaan (5 Baris Teratas):")
            print("-" * 60)
            print(f"{'Emp ID':<10} | {'Cluster ID':<10} | {'Operational Profile':<20}")
            print("-" * 60)
            
            for res in results[:5]:
                print(f"{res['employee_id']:<10} | {res['cluster']:<10} | {res['cluster_name']:<20}")
                
            print("-" * 60)
            print("✓ Berhasil! Output terstruktur siap diintegrasikan ke Laravel.")
            print("=" * 60)
                
        except KeyError as e:
            print(f"\n[!] Error Format: Kolom {e} tidak ditemukan di file '{csv_filename}'.")
            print("=" * 60)
        except Exception as e:
            print(f"\n[!] Terjadi kesalahan teknis: {e}")
            print("=" * 60)