"""
rf_service.py
=============
Random Forest service untuk mengevaluasi kandidat jadwal GA.

ALUR KERJA:
  1. Model dimuat dari disk saat pertama kali dipakai (lazy loading, di-cache).
  2. Setiap kandidat jadwal (output GA) dikonversi ke fitur numerik.
     - Fitur diambil dari DATA ASLI PEGAWAI (employees_by_id) jika tersedia.
     - Fallback ke snapshot dari assignments jika data pegawai tidak dikirim.
  3. Model RF memprediksi estimated_daily_salary rata-rata per kandidat.
  4. rf_profit_score dihitung: makin rendah biaya + pelanggaran = skor makin tinggi.
  5. Kandidat diurutkan dari skor tertinggi.

Kenapa employees_by_id penting?
---------------------------------
  Tanpa data pegawai asli, fitur seperti age, job_level, Dept harus di-hardcode
  ke nilai default → prediksi kurang akurat untuk tiap user.
  Dengan employees_by_id dari input user, fitur dihitung dari DATA NYATA
  pegawai yang dijadwalkan → prediksi salary mencerminkan kondisi sesungguhnya.

Catatan untuk mahasiswa:
------------------------
  - Jalankan train_rf_model.py dulu sebelum server bisa dipakai.
  - rf_profit_score BUKAN gaji yang dibayarkan. Ini skor 0-100 untuk
    membandingkan antar kandidat: mana yang paling hemat dan efisien.
"""

from __future__ import annotations

import os
import numpy as np
import pandas as pd
import joblib

from app.schemas import Employee, EvaluatedCandidate, ScheduleCandidate
from app.services.salary_calculator import compute_candidate_salary_features

# ── Path model ────────────────────────────────────────────────────────────────

_BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
_MODEL_PATH = os.path.join(_BASE_DIR, "models", "rf_salary_model.joblib")
_FEATURE_NAMES_PATH = os.path.join(_BASE_DIR, "models", "rf_feature_names.joblib")

# Cache model agar tidak reload setiap request
_MODEL = None
_FEATURE_NAMES: list[str] | None = None


# ── Konstanta bisnis (sama dengan train_rf_model.py) ─────────────────────────

_EDUCATION_ENC = {"ug": 0, "pg": 1}
_LOCATION_ENC  = {"suburb": 0, "city": 1}
_DEPT_LIST = [
    "Anesthesiologist", "Cardiologist", "Emergency Physician",
    "General Practitioner", "Neurologist", "Nurse Assistant",
    "Nurse Practitioner", "Oncologist", "Orthopedic Surgeon",
    "Pediatrician", "Radiologist", "Registered Nurse", "Surgeon",
]
_RECRUITMENT_ENC = {"on-campus": 0, "recruitment agency": 1, "referral": 2, "walk-in": 3}


# ── Load model ────────────────────────────────────────────────────────────────

def _load_model():
    """
    Muat model RF dari disk ke memori (hanya sekali, lalu di-cache).

    Raise FileNotFoundError jika model belum ditraining.
    Solusi: jalankan `python train_rf_model.py` dari folder shiftly-ai.
    """
    global _MODEL, _FEATURE_NAMES

    if _MODEL is not None:
        return _MODEL, _FEATURE_NAMES

    if not os.path.exists(_MODEL_PATH):
        raise FileNotFoundError(
            f"Model belum ada di: {_MODEL_PATH}\n"
            "Jalankan dulu: python train_rf_model.py"
        )

    _MODEL = joblib.load(_MODEL_PATH)
    _FEATURE_NAMES = joblib.load(_FEATURE_NAMES_PATH)
    return _MODEL, _FEATURE_NAMES


# ── Feature extraction ────────────────────────────────────────────────────────

def _extract_employee_avg_features(
    candidate: ScheduleCandidate,
    employees_by_id: dict[int, Employee],
) -> dict:
    """
    Hitung rata-rata fitur pegawai dari semua assignment aktif di kandidat ini.

    Menggunakan DATA ASLI PEGAWAI dari employees_by_id jika tersedia.
    Fallback ke salary_snapshot / is_senior_snapshot dari assignment.

    Kenapa rata-rata?
    -----------------
    Satu kandidat jadwal melibatkan banyak pegawai. Model RF ditraining
    per-pegawai (satu baris = satu pegawai). Untuk mengevaluasi satu
    kandidat (banyak pegawai), kita pakai rata-rata profil pegawai aktif.
    Ini sudah cukup karena target kita adalah biaya total kandidat.
    """
    assignments = candidate.assignments
    if not assignments:
        # Fallback ke nilai rata-rata dataset jika tidak ada assignment
        return {
            "age": 38.6, "job_level": 3.0, "rating": 3.0,
            "certifications": 0.5, "awards": 0.4, "onsite": 0.5, "satisfied": 0.5,
            "education_enc": 0.5, "Dept_enc": 6.0, "location_enc": 0.5,
            "recruitment_type_enc": 1.5,
        }

    # Kumpulkan atribut dari setiap pegawai aktif (deduplicate per employee_id)
    seen_emp_ids: set[int] = set()
    ages, job_levels, ratings, certs, awards, onsites, satisfieds = [], [], [], [], [], [], []
    edu_encs, dept_encs, loc_encs, rec_encs = [], [], [], []

    for assignment in assignments:
        emp_id = assignment.employee_id
        if emp_id in seen_emp_ids:
            continue
        seen_emp_ids.add(emp_id)

        emp = employees_by_id.get(emp_id)

        if emp is not None:
            # ── Pakai DATA ASLI pegawai dari input user ──────────────────────
            ages.append(float(emp.age))
            job_levels.append(float(emp.job_level))
            ratings.append(float(emp.rating))
            certs.append(float(getattr(emp, "certifications", 0)))
            awards.append(float(getattr(emp, "awards", 0)))
            onsites.append(float(getattr(emp, "onsite", 0)))
            satisfieds.append(float(emp.satisfied))

            edu_enc = _EDUCATION_ENC.get((emp.education or "").strip().lower(), 0)
            edu_encs.append(float(edu_enc))

            dept_name = (emp.department or "").strip()
            dept_enc = float(_DEPT_LIST.index(dept_name)) if dept_name in _DEPT_LIST else 6.0
            dept_encs.append(dept_enc)

            loc_encs.append(0.5)         # location tidak ada di schema Employee
            rec_encs.append(1.5)         # recruitment_type tidak ada di schema Employee

        else:
            # ── Fallback ke snapshot assignment ──────────────────────────────
            ages.append(35.0)
            job_levels.append(3.0)
            ratings.append(3.0)
            certs.append(1.0 if assignment.is_senior_snapshot else 0.0)
            awards.append(0.0)
            onsites.append(0.0)
            satisfieds.append(0.5)
            edu_encs.append(1.0 if assignment.is_senior_snapshot else 0.0)
            dept_encs.append(6.0)
            loc_encs.append(0.5)
            rec_encs.append(1.5)

    def _mean(lst): return float(np.mean(lst)) if lst else 0.0

    return {
        "age":                  _mean(ages),
        "job_level":            _mean(job_levels),
        "rating":               _mean(ratings),
        "certifications":       _mean(certs),
        "awards":               _mean(awards),
        "onsite":               _mean(onsites),
        "satisfied":            _mean(satisfieds),
        "education_enc":        _mean(edu_encs),
        "Dept_enc":             _mean(dept_encs),
        "location_enc":         _mean(loc_encs),
        "recruitment_type_enc": _mean(rec_encs),
    }


def _candidate_to_rf_features(
    candidate: ScheduleCandidate,
    employees_by_id: dict[int, Employee],
    feature_names: list[str],
) -> dict:
    """
    Konversi satu kandidat jadwal ke dict fitur untuk model RF.

    Menggabungkan:
    - Rata-rata atribut pegawai asli (dari employees_by_id)
    - Fitur shift engineering (is_nightshift, has_certification, night_to_morning_flag)
    - Fitur salary dari salary_calculator

    Fitur harus punya nama dan urutan SAMA dengan saat training.
    Ini dijaga oleh parameter feature_names dari rf_feature_names.joblib.
    """
    assignments = candidate.assignments
    total_assignments = max(len(assignments), 1)

    # ── Fitur shift dari assignments aktual ───────────────────────────────────
    night_count = sum(1 for a in assignments if a.shift == "Malam")
    cert_count  = sum(1 for a in assignments if a.is_senior_snapshot)

    is_nightshift_dominant = float((night_count / total_assignments) > 0.3)
    has_certification_any  = float(cert_count > 0)

    # Fitur salary dari salary_calculator (termasuk night_to_morning_count)
    salary_feats = compute_candidate_salary_features(candidate, employees_by_id)
    night_to_morning_flag = float(salary_feats["night_to_morning_count"] > 0)

    # ── Rata-rata atribut pegawai (dari data asli user) ───────────────────────
    emp_avg = _extract_employee_avg_features(candidate, employees_by_id)

    # ── Rakit dict fitur sesuai urutan feature_names ──────────────────────────
    feature_map = {
        # Atribut pegawai asli (rata-rata semua pegawai aktif di kandidat ini)
        "age":                      emp_avg["age"],
        "job_level":                emp_avg["job_level"],
        "rating":                   emp_avg["rating"],
        "certifications":           emp_avg["certifications"],
        "awards":                   emp_avg["awards"],
        "onsite":                   emp_avg["onsite"],
        "satisfied":                emp_avg["satisfied"],
        # Fitur rekayasa dari shift assignments
        "is_nightshift":            is_nightshift_dominant,
        "has_certification":        has_certification_any,
        "is_senior":                emp_avg["education_enc"],     # PG=1 = senior
        "night_to_morning_flag":    night_to_morning_flag,
        # Kategorikal (encoded, rata-rata dari semua pegawai aktif)
        "education_enc":            emp_avg["education_enc"],
        "Dept_enc":                 emp_avg["Dept_enc"],
        "location_enc":             emp_avg["location_enc"],
        "recruitment_type_enc":     emp_avg["recruitment_type_enc"],
    }

    return {fname: feature_map.get(fname, 0.0) for fname in feature_names}


# ── Konversi prediksi salary → profit score ───────────────────────────────────

def _to_profit_score(
    predicted_salary: float,
    candidate: ScheduleCandidate,
    max_salary: float,
    min_salary: float,
) -> float:
    """
    Konversi prediksi estimated_daily_salary ke rf_profit_score (0–100).

    Skor tinggi = jadwal hemat + sedikit pelanggaran.

    Komponen:
    - 60%: salary score (makin rendah prediksi gaji, makin tinggi skor)
    - 40%: dikurangi penalti pelanggaran
        - Hard violation × 6.0 (kritis, wajib dipenuhi)
        - Soft violation × 0.5
        - Weekly day off violation × 1.0
    """
    salary_range  = max(max_salary - min_salary, 1.0)
    salary_score  = (1.0 - (predicted_salary - min_salary) / salary_range) * 100

    s = candidate.summary
    violation_penalty = (
        s.hard_violation_count * 6.0
        + s.soft_violation_count * 0.5
        + s.weekly_day_off_violations * 1.0
    )

    score = (salary_score * 0.60) - (violation_penalty * 0.40)
    return round(float(np.clip(score, 0.0, 100.0)), 2)


# ── Fungsi utama (dipanggil dari main.py) ─────────────────────────────────────

def evaluate_candidates(
    candidates: list[ScheduleCandidate],
    employees_by_id: dict[int, Employee] | None = None,
) -> list[EvaluatedCandidate]:
    """
    Evaluasi kandidat jadwal menggunakan model Random Forest terlatih.

    Parameter:
    ----------
    candidates       : list[ScheduleCandidate] dari output GA engine
    employees_by_id  : dict {employee_id: Employee} — data asli pegawai dari DB.
                       Jika None atau kosong, fitur dihitung dari snapshot saja.

    Return:
    -------
    list[EvaluatedCandidate] diurutkan dari rf_profit_score tertinggi (terbaik).

    Contoh alur:
    ------------
    User kirim 3 kandidat jadwal + 20 employees
    → Fitur tiap kandidat dihitung dari data 20 employees asli (bukan default)
    → Model prediksi estimasi salary harian
    → Kandidat termurah + paling sedikit pelanggaran → skor tertinggi
    """
    if not candidates:
        raise ValueError("candidates tidak boleh kosong")

    model, feature_names = _load_model()
    emp_map = employees_by_id or {}

    # Ekstrak fitur dari setiap kandidat menggunakan data pegawai asli
    features_list = [
        _candidate_to_rf_features(candidate, emp_map, feature_names)
        for candidate in candidates
    ]

    X = pd.DataFrame(features_list, columns=feature_names)
    predicted_salaries = model.predict(X)

    max_sal = float(predicted_salaries.max())
    min_sal = float(predicted_salaries.min())

    evaluated = [
        EvaluatedCandidate(
            **candidate.model_dump(),
            rf_profit_score=_to_profit_score(
                float(pred), candidate, max_sal, min_sal
            ),
        )
        for candidate, pred in zip(candidates, predicted_salaries)
    ]

    return sorted(evaluated, key=lambda c: c.rf_profit_score, reverse=True)
