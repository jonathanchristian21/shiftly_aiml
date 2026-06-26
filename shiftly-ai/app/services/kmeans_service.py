"""
kmeans_service.py
=================
Service K-Means untuk segmentasi profil employee Shiftly.

Peran dalam pipeline:
---------------------
  Data employee (dari DB Laravel)
        ↓
  K-Means Clustering (file ini)
        ↓
  Setiap employee mendapat label cluster (A/B/C/D)
        ↓
  Label cluster dikirim ke GA engine → populasi awal lebih cerdas
        ↓
  Output GA → kandidat jadwal → Random Forest

Kenapa K-Means dipakai di sini?
---------------------------------
  Kita ingin mengelompokkan pegawai berdasarkan profil kerja (senioritas,
  gaji, performa, dll.) agar GA bisa membuat jadwal yang lebih seimbang.
  Misal: tiap shift idealnya punya campuran pegawai Senior dan Junior.

Perubahan dari versi sebelumnya:
---------------------------------
  - Scaler sekarang di-FIT dari data CSV Employee_Satisfaction_Index.csv,
    bukan hanya dari data request. Ini memastikan skala normalisasi konsisten
    meskipun jumlah employee di request sedikit.
  - Jika CSV tidak ditemukan (misal saat testing), fallback ke fit dari data
    request saja (perilaku lama).

Catatan untuk mahasiswa:
------------------------
  - MinMaxScaler: mengubah semua nilai fitur ke rentang 0–1 agar fitur
    dengan skala besar (misal salary) tidak mendominasi clustering.
  - K-Means tidak bisa langsung tahu mana yang "Senior" — ia hanya melihat
    angka. Kita yang kemudian mapping centroid → label bisnis (A/B/C/D).
"""

from __future__ import annotations

import os
import numpy as np
import pandas as pd
from sklearn.cluster import KMeans
from sklearn.preprocessing import MinMaxScaler

from app.schemas import Employee


# ── Path CSV untuk fit scaler ─────────────────────────────────────────────────

_CSV_PATH = os.path.join(
    os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))),
    "Employee_Satisfaction_Index.csv",
)

# Cache scaler yang sudah di-fit dari CSV agar tidak dibaca berulang kali
_FITTED_SCALER: MinMaxScaler | None = None


# ── Mapping education ke skor senioritas ──────────────────────────────────────

EDUCATION_IS_SENIOR = {
    "ug": 0.0,   # Undergraduate → Junior
    "pg": 1.0,   # Postgraduate  → Senior
}


def education_to_senior_score(value: str | None) -> float:
    """Konversi string education ('PG'/'UG') ke angka 0 atau 1."""
    if not value:
        return 0.0
    return float(EDUCATION_IS_SENIOR.get(value.strip().lower(), 0.0))


def employee_features(employee: Employee) -> list[float]:
    """
    Ekstrak fitur numerik dari satu objek Employee untuk dipakai K-Means.

    Urutan fitur (penting, harus konsisten dengan CSV):
      Index 0: age
      Index 1: job_level
      Index 2: salary
      Index 3: rating
      Index 4: satisfied
      Index 5: certifications
      Index 6: education (0=UG, 1=PG)
    """
    return [
        float(employee.age),
        float(employee.job_level),
        float(employee.salary),
        float(employee.rating),
        float(employee.satisfied),
        float(employee.certifications),
        education_to_senior_score(employee.education),
    ]


def _get_fitted_scaler() -> MinMaxScaler:
    """
    Dapatkan MinMaxScaler yang sudah di-fit dari data CSV.

    Scaler di-fit dari CSV Employee_Satisfaction_Index.csv sehingga
    range normalisasi mencerminkan distribusi pegawai nyata (500 baris),
    bukan hanya dari pool kecil yang dikirim di request.

    Jika CSV tidak ada, fallback menggunakan scaler tanpa pre-fit.
    """
    global _FITTED_SCALER

    if _FITTED_SCALER is not None:
        return _FITTED_SCALER

    scaler = MinMaxScaler()

    if os.path.exists(_CSV_PATH):
        df = pd.read_csv(_CSV_PATH)
        # Konversi education ke skor numerik
        df["education_score"] = df["education"].str.lower().map(
            lambda x: EDUCATION_IS_SENIOR.get(str(x).strip(), 0.0)
        )
        # Ambil kolom yang sama dengan employee_features(), urutan sama
        csv_features = df[["age", "job_level", "salary", "rating", "satisfied",
                            "certifications", "education_score"]].values
        scaler.fit(csv_features)
    # else: scaler belum di-fit, akan di-fit dari data request saja

    _FITTED_SCALER = scaler
    return _FITTED_SCALER


def map_clusters_to_profiles(centroids: np.ndarray) -> dict[int, dict]:
    """
    Mapping centroid K-Means ke label bisnis (A, B, C, D).

    Karena K-Means hanya menghasilkan angka cluster (0, 1, 2, 3),
    kita perlu menentukan mana yang "Senior", "Junior", dll. berdasarkan
    nilai centroid di tiap dimensi.

    Strategi mapping:
    -----------------
    A → Senior: education_score (idx 6) + salary (idx 2) + job_level (idx 1) tertinggi
    B → Junior: education_score + salary + job_level terendah
    C → Mid-level, performa baik: rating (idx 3) + satisfied (idx 4) tinggi dari sisa
    D → Mid-level, performa rendah: rating + satisfied rendah dari sisa

    Parameter:
    ----------
    centroids : np.ndarray shape (n_clusters, n_features)
        Nilai centroid dari setiap cluster dalam skala terscale (0–1).
    """
    n_clusters = len(centroids)
    mapping = {}
    unassigned = list(range(n_clusters))

    # Cluster A: Senior → edu + salary + job_level paling tinggi
    score_A = [c[6] + c[2] + c[1] for c in centroids]
    idx_A = int(np.argmax(score_A))
    mapping[idx_A] = {"id": 1, "name": "A", "desc": "Senior (PG), Job Level & Salary Tinggi"}
    unassigned.remove(idx_A)

    # Cluster B: Junior → edu + salary + job_level paling rendah
    if unassigned:
        idx_B = min(unassigned, key=lambda i: centroids[i][6] + centroids[i][2] + centroids[i][1])
        mapping[idx_B] = {"id": 2, "name": "B", "desc": "Junior (UG), Job Level & Salary Rendah"}
        unassigned.remove(idx_B)

    # Cluster C & D: dari sisa, pisahkan berdasarkan rating + satisfied
    if len(unassigned) == 2:
        idx_1, idx_2 = unassigned
        if (centroids[idx_1][3] + centroids[idx_1][4]) < (centroids[idx_2][3] + centroids[idx_2][4]):
            idx_D, idx_C = idx_1, idx_2
        else:
            idx_D, idx_C = idx_2, idx_1

        mapping[idx_D] = {"id": 4, "name": "D", "desc": "Senior/Mid, Rating/Satisfied Rendah"}
        mapping[idx_C] = {"id": 3, "name": "C", "desc": "Mid-level, Rating/Satisfied Tinggi"}

    # Fallback jika n_clusters < 4 (misal hanya 2 atau 3 cluster diminta)
    for i in unassigned:
        if i not in mapping:
            mapping[i] = {"id": i + 1, "name": f"Cluster {i + 1}", "desc": "General Cluster"}

    return mapping


def cluster_employees(
    employees: list[Employee],
    n_clusters: int = 4,
) -> tuple[int, list[dict]]:
    """
    Jalankan K-Means clustering pada list employee.

    Pipeline:
    ---------
    1. Ekstrak fitur dari setiap employee (7 fitur numerik)
    2. Normalisasi fitur ke 0–1 menggunakan scaler yang di-fit dari CSV
    3. Jalankan K-Means dengan n_clusters cluster
    4. Map indeks cluster ke label bisnis (A/B/C/D)
    5. Return hasil sebagai list dict {employee_id, cluster, cluster_name, description}

    Parameter:
    ----------
    employees  : list[Employee] — daftar pegawai yang akan di-cluster
    n_clusters : int — jumlah cluster yang diinginkan (1–10)

    Return:
    -------
    (cluster_count, results) di mana results adalah list dict per employee.
    """
    if not employees:
        raise ValueError("employees tidak boleh kosong")

    cluster_count = min(n_clusters, len(employees))
    features = np.array([employee_features(emp) for emp in employees])

    # Gunakan scaler yang sudah di-fit dari CSV
    scaler = _get_fitted_scaler()

    # Jika scaler belum di-fit (CSV tidak ada), fit dari data request
    if not hasattr(scaler, "data_min_") or scaler.data_min_ is None:
        scaler.fit(features)

    scaled_features = scaler.transform(features)

    # Jalankan K-Means (n_init=10: coba 10 inisialisasi berbeda, ambil terbaik)
    model = KMeans(n_clusters=cluster_count, n_init=10, random_state=42)
    labels = model.fit_predict(scaled_features)

    # Map indeks cluster ke label bisnis
    profile_mapping = map_clusters_to_profiles(model.cluster_centers_)

    return cluster_count, [
        {
            "employee_id":  emp.id,
            "cluster":      profile_mapping[int(label)]["id"],
            "cluster_name": profile_mapping[int(label)]["name"],
            "description":  profile_mapping[int(label)]["desc"],
        }
        for emp, label in zip(employees, labels)
    ]
