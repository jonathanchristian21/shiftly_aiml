"""
rf_service.py — RF evaluasi profit_score kandidat jadwal.

PERBAIKAN RF AGAR LEBIH BERVARIASI (tidak stuck di 30-an):
------------------------------------------------------------
Masalah sebelumnya: fitur antar kandidat C1-C5 hampir identik karena
semua diambil dari pool employee yang sama → RF menghasilkan prediksi
yang hampir sama untuk semua kandidat.

Solusi: tambahkan fitur DIFERENSIAL — fitur yang mengukur PERBEDAAN
antar kandidat, bukan hanya nilai absolut:
  - salary_efficiency   : total_salary / median_salary_batch (relatif ke batch)
  - coverage_efficiency : active_employees / total_required_slots
  - assignment_density  : total_assignments / (active_employees × days)
  - hard_ratio         : hard_violations / total_slots
  - senior_coverage    : actual_senior / required_senior (coverage rate senior)

Dengan fitur diferensial, meskipun semua kandidat dari pool yang sama,
perbedaan kecil dalam komposisi shift akan menghasilkan fitur yang berbeda
→ RF memberikan skor yang lebih bervariasi.

TOTAL SALARY: dari salary_calculator (USD, deterministik per assignment).
FINAL SCORE : GA fitness norm (50%) + RF profit score (50%).
BEST        : kandidat dengan final_score tertinggi.
RF SCORE    : raw prediction dari model (tidak di-rescale), floor 5.
"""

from __future__ import annotations
import os
import numpy as np
import pandas as pd
import joblib
from collections import defaultdict

from app.schemas import Employee, EvaluatedCandidate, ScheduleCandidate
from app.services.salary_calculator import compute_candidate_salary_features

_BASE_DIR   = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
_MODEL_PATH = os.path.join(_BASE_DIR, "models", "rf_profit_model.joblib")
_FEAT_PATH  = os.path.join(_BASE_DIR, "models", "rf_profit_feature_names.joblib")
_MODEL      = None
_FEAT_NAMES: list[str] | None = None

DEPT_TIER: dict[str, int] = {
    "Emergency Physician": 1, "Surgeon": 1, "Orthopedic Surgeon": 1,
    "Cardiologist": 1, "Neurologist": 1, "Anesthesiologist": 1,
    "Oncologist": 2, "Radiologist": 2, "Pediatrician": 2, "General Practitioner": 2,
    "Registered Nurse": 3, "Nurse Practitioner": 3, "Nurse Assistant": 3,
}
TIER_WEIGHT         = {1: 1.5, 2: 1.0, 3: 0.6}
BENCHMARK_DAILY_USD = 2292.0

FEATURE_NAMES = [
    # Fitur absolut (sama seperti sebelumnya)
    "coverage_rate", "avg_dept_weight", "certified_ratio", "senior_ratio",
    "night_ratio", "night_to_morning_ratio", "cost_ratio", "cluster_balance",
    "hard_violation_count", "soft_violation_ratio", "dayoff_violation_ratio",
    "avg_job_level",
    # Fitur diferensial (BARU) — lebih diskriminatif antar kandidat
    "assignment_density",    # total_assignments / (active_employees × days)
    "senior_coverage_rate",  # pct slot senior yang terpenuhi
    "salary_per_active_emp", # total_salary / active_employees (efisiensi per orang)
    "night_senior_ratio",    # senior yang kena shift malam / total senior aktif
]


def _load_model():
    global _MODEL, _FEAT_NAMES
    if _MODEL is not None:
        return _MODEL, _FEAT_NAMES
    if not os.path.exists(_MODEL_PATH):
        raise FileNotFoundError(
            f"Model belum ada: {_MODEL_PATH}\n"
            "Jalankan: python train_rf_model.py"
        )
    _MODEL      = joblib.load(_MODEL_PATH)
    _FEAT_NAMES = joblib.load(_FEAT_PATH)
    print(f"[RF] Model dimuat: {_MODEL_PATH}")
    return _MODEL, _FEAT_NAMES


def _extract_features(
    candidate: ScheduleCandidate,
    employees_by_id: dict[int, Employee],
    days: int = 7,
) -> dict:
    """Ekstrak 16 fitur dari kandidat (12 absolut + 4 diferensial baru)."""
    s            = candidate.summary
    total_assign = max(s.total_assignments, 1)
    active_ids   = set(a.employee_id for a in candidate.assignments if a.shift != "Libur")

    dept_weights, certs, seniors, job_levels = [], [], [], []
    senior_ids: set[int] = set()
    for eid in active_ids:
        emp = employees_by_id.get(eid)
        if emp:
            tier = DEPT_TIER.get(emp.department or "", 3)
            dept_weights.append(TIER_WEIGHT[tier])
            certs.append(1 if getattr(emp, "certifications", 0) >= 1 else 0)
            is_sr = (emp.education or "").upper() == "PG"
            seniors.append(1 if is_sr else 0)
            job_levels.append(float(emp.job_level))
            if is_sr:
                senior_ids.add(eid)
        else:
            dept_weights.append(1.0); certs.append(0)
            seniors.append(0); job_levels.append(3.0)

    def m(lst): return float(np.mean(lst)) if lst else 0.0

    sal          = compute_candidate_salary_features(candidate, employees_by_id)
    cost_ratio   = sal["estimated_total_salary"] / max(BENCHMARK_DAILY_USD * total_assign, 1)
    ntm_ratio    = sal["night_to_morning_count"] / total_assign
    night_ratio  = s.shift_counts.get("Malam", 0) / total_assign
    soft_ratio   = s.soft_violation_count / total_assign
    dayoff_ratio = s.weekly_day_off_violations / total_assign
    n_active     = max(len(active_ids), 1)

    # ── Fitur diferensial baru ────────────────────────────────────────────
    # assignment_density: rata-rata berapa shift dikerjakan 1 orang per hari
    # Nilai ideal ~1.0 (1 shift/orang/hari). >1 = overwork, <1 = underutilized
    assignment_density   = total_assign / max(n_active * days, 1)

    # senior_coverage_rate: dari semua slot yang butuh senior, berapa % terpenuhi
    # Dihitung dari constraint_reports
    required_senior_slots = sum(
        1 for r in candidate.constraint_reports if r.required_senior > 0
    )
    filled_senior_slots   = sum(
        1 for r in candidate.constraint_reports
        if r.required_senior > 0 and r.actual_senior >= r.required_senior
    )
    senior_coverage_rate  = filled_senior_slots / max(required_senior_slots, 1)

    # salary_per_active_emp: total salary per orang aktif (efisiensi biaya SDM)
    salary_per_active = sal["estimated_total_salary"] / n_active

    # night_senior_ratio: berapa banyak senior yang kena shift malam
    # Senior kena malam banyak = costly + risiko → berkontribusi negatif ke profit
    night_assignments = [a for a in candidate.assignments if a.shift == "Malam"]
    senior_at_night   = sum(1 for a in night_assignments if a.employee_id in senior_ids)
    night_senior_ratio= senior_at_night / max(len(senior_ids) * days, 1)

    return {
        "coverage_rate":           min(1.0, s.active_employees / max(n_active, 1)),
        "avg_dept_weight":         m(dept_weights),
        "certified_ratio":         m(certs),
        "senior_ratio":            m(seniors),
        "night_ratio":             night_ratio,
        "night_to_morning_ratio":  ntm_ratio,
        "cost_ratio":              cost_ratio,
        "cluster_balance":         float(s.cluster_balance),
        "hard_violation_count":    float(s.hard_violation_count),
        "soft_violation_ratio":    soft_ratio,
        "dayoff_violation_ratio":  dayoff_ratio,
        "avg_job_level":           m(job_levels),
        # Diferensial
        "assignment_density":      assignment_density,
        "senior_coverage_rate":    senior_coverage_rate,
        "salary_per_active_emp":   salary_per_active,
        "night_senior_ratio":      night_senior_ratio,
    }


def evaluate_candidates(
    candidates: list[ScheduleCandidate],
    employees_by_id: dict[int, Employee] | None = None,
) -> list[EvaluatedCandidate]:
    """
    Evaluasi kandidat, return sorted by final_score DESC.

    RF score: raw prediction dari model, floor 5 (tidak pernah 0).
    final_score = GA fitness norm (50%) + RF score (50%).
    total_salary = dari salary_calculator (USD).
    """
    if not candidates:
        raise ValueError("candidates tidak boleh kosong")

    model, feat_names = _load_model()
    emp_map = employees_by_id or {}

    # Estimasi jumlah hari dari assignments
    all_dates = set()
    for c in candidates:
        for a in c.assignments:
            all_dates.add(a.date)
    n_days = max(len(all_dates), 7)

    sal_totals = [
        compute_candidate_salary_features(c, emp_map)["estimated_total_salary"]
        for c in candidates
    ]

    X = pd.DataFrame(
        [_extract_features(c, emp_map, days=n_days) for c in candidates],
        columns=feat_names,
    )
    raw_rf = model.predict(X)

    # Raw prediction langsung dipakai, floor 5 agar tidak pernah 0 di UI
    rf_scores = np.clip(raw_rf, 5.0, 100.0)

    # Normalisasi GA fitness ke [0,100] dalam batch
    ga_vals = np.array([c.summary.ga_fitness for c in candidates])
    ga_min, ga_max = ga_vals.min(), ga_vals.max()
    ga_norm = (
        np.full(len(ga_vals), 50.0) if ga_max - ga_min < 1e-6
        else (ga_vals - ga_min) / (ga_max - ga_min) * 100.0
    )

    final_scores = ga_norm * 0.5 + rf_scores * 0.5

    evaluated = []
    for c, rf_s, ga_n, fin_s, total_sal in zip(
        candidates, rf_scores, ga_norm, final_scores, sal_totals
    ):
        data = c.model_dump()
        data["summary"]["total_salary"] = round(total_sal, 2)

        evaluated.append(EvaluatedCandidate(
            **data,
            rf_profit_score=round(float(rf_s), 2),
            predicted_salary=round(float(total_sal), 2),
            final_score=round(float(fin_s), 2),
        ))

        print(
            f"  [RF] {c.candidate_id}: "
            f"rf={rf_s:.1f}%  ga_norm={ga_n:.1f}  "
            f"final={fin_s:.1f}  H:{c.summary.hard_violation_count}  "
            f"salary=${total_sal:,.0f}"
        )

    return sorted(evaluated, key=lambda c: c.final_score, reverse=True)