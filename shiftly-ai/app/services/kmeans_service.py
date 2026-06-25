"""Service K-Means untuk segmentasi profil employee.

Output cluster dipakai Laravel untuk mengisi employees.cluster_label dan
dipakai GA sebagai salah satu sinyal agar distribusi jadwal lebih seimbang.
Department tidak dipakai sebagai fitur cluster karena department adalah batas
penjadwalan, bukan kemiripan profil pegawai.
"""

from __future__ import annotations

import numpy as np
from sklearn.cluster import KMeans
from sklearn.preprocessing import MinMaxScaler

from app.schemas import Employee


EDUCATION_IS_SENIOR = {
    "ug": 0.0,
    "pg": 1.0,
}


def education_to_senior_score(value: str | None) -> float:
    if not value:
        return 0

    return float(EDUCATION_IS_SENIOR.get(value.strip().lower(), 0.0))


def employee_features(employee: Employee) -> list[float]:
    # Urutan: 0:age, 1:job_level, 2:salary, 3:rating, 4:satisfied, 5:certifications, 6:education
    return [
            float(employee.age),
            float(employee.job_level),
            float(employee.salary),
            float(employee.rating),
            float(employee.satisfied),
            float(employee.certifications),
            education_to_senior_score(employee.education),
        ]

def map_clusters_to_profiles(centroids: np.ndarray) -> dict[int, dict]:
    """
    Mapping centroid K-Means murni ke label bisnis (A, B, C, D).
    Index: 1=job_level, 2=salary, 3=rating, 4=satisfied, 6=education
    """
    n_clusters = len(centroids)
    mapping = {}
    unassigned = list(range(n_clusters))
    
    # 1. Cluster A: Senior, Job Level & Salary tertinggi (Max dari edu + salary + job_level)
    score_A = [c[6] + c[2] + c[1] for c in centroids]
    idx_A = int(np.argmax(score_A))
    mapping[idx_A] = {"id": 1, "name": "A", "desc": "Senior (PG), Job Level & Salary Tinggi"}
    unassigned.remove(idx_A)
    
    # 2. Cluster B: Junior, Job Level & Salary terendah
    if unassigned:
        idx_B = min(unassigned, key=lambda i: centroids[i][6] + centroids[i][2] + centroids[i][1])
        mapping[idx_B] = {"id": 2, "name": "B", "desc": "Junior (UG), Job Level & Salary Rendah"}
        unassigned.remove(idx_B)

    # 3. Cluster C & D (Sisa)
    if len(unassigned) == 2:
        idx_1, idx_2 = unassigned
        # Bandingkan rating + satisfied
        if (centroids[idx_1][3] + centroids[idx_1][4]) < (centroids[idx_2][3] + centroids[idx_2][4]):
            idx_D, idx_C = idx_1, idx_2
        else:
            idx_D, idx_C = idx_2, idx_1
            
        mapping[idx_D] = {"id": 4, "name": "D", "desc": "Senior/Mid, Rating/Satisfied Rendah"}
        mapping[idx_C] = {"id": 3, "name": "C", "desc": "Mid-level, Rating/Satisfied Tinggi"}
    
    # Fallback
    for i in unassigned:
        if i not in mapping:
            mapping[i] = {"id": i+1, "name": f"Cluster {i+1}", "desc": "General Cluster"}

    return mapping

def cluster_employees(employees: list[Employee], n_clusters: int = 4) -> tuple[int, list[dict[str, int]]]:
    if not employees:
        raise ValueError("employees tidak boleh kosong")

    cluster_count = min(n_clusters, len(employees))
    features = np.array([employee_features(employee) for employee in employees])
    scaler = MinMaxScaler()
    scaled_features = scaler.fit_transform(features)

    model = KMeans(n_clusters=cluster_count, n_init=10, random_state=42)
    labels = model.fit_predict(scaled_features)

    # Menjalankan heuristik mapping
    profile_mapping = map_clusters_to_profiles(model.cluster_centers_)

    return cluster_count, [
            {
                "employee_id": emp.id, 
                "cluster": profile_mapping[int(label)]["id"],
                "cluster_name": profile_mapping[int(label)]["name"],
                "description": profile_mapping[int(label)]["desc"],
            }
            for emp, label in zip(employees, labels)
        ]
