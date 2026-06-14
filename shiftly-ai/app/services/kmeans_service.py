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
    return [
        float(employee.age),
        float(employee.job_level),
        float(employee.salary),
        float(employee.rating),
        float(employee.certifications),
        education_to_senior_score(employee.education),
    ]


def cluster_employees(employees: list[Employee], n_clusters: int = 3) -> tuple[int, list[dict[str, int]]]:
    if not employees:
        raise ValueError("employees tidak boleh kosong")

    cluster_count = min(n_clusters, len(employees))
    features = np.array([employee_features(employee) for employee in employees])
    scaled_features = MinMaxScaler().fit_transform(features)

    model = KMeans(n_clusters=cluster_count, n_init=10, random_state=42)
    labels = model.fit_predict(scaled_features)

    return cluster_count, [
        {"employee_id": employee.id, "cluster": int(label)}
        for employee, label in zip(employees, labels)
    ]
