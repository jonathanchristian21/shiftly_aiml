"""
rf_service.py — RF evaluator: prediksi profit_score langsung dari fitur jadwal.

PERAN RF:
  RF memprediksi profit_score (0-100) dari 12 fitur jadwal.
  RF tidak prediksi salary — total_salary tetap dari salary_calculator (USD).

FINAL SCORE & BEST:
  final_score = (ga_fitness_norm × 0.5) + (rf_profit_score × 0.5)
  BEST = kandidat dengan final_score tertinggi.

RF PROFIT SCORE TIDAK PERNAH 0:
  Rank-based rescale ke [10, 100] dalam batch.
"""

from __future__ import annotations
import os
import numpy as np
import pandas as pd
import joblib

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
    "coverage_rate", "avg_dept_weight", "certified_ratio", "senior_ratio",
    "night_ratio", "night_to_morning_ratio", "cost_ratio", "cluster_balance",
    "hard_violation_count", "soft_violation_ratio", "dayoff_violation_ratio",
    "avg_job_level",
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


def _extract_features(candidate: ScheduleCandidate, employees_by_id: dict) -> dict:
    s            = candidate.summary
    total_assign = max(s.total_assignments, 1)
    active_ids   = set(a.employee_id for a in candidate.assignments if a.shift != "Libur")

    dept_weights, certs, seniors, job_levels = [], [], [], []
    for eid in active_ids:
        emp = employees_by_id.get(eid)
        if emp:
            tier = DEPT_TIER.get(emp.department or "", 3)
            dept_weights.append(TIER_WEIGHT[tier])
            certs.append(1 if getattr(emp, "certifications", 0) >= 1 else 0)
            seniors.append(1 if (emp.education or "").upper() == "PG" else 0)
            job_levels.append(float(emp.job_level))
        else:
            dept_weights.append(1.0); certs.append(0); seniors.append(0); job_levels.append(3.0)

    def m(lst): return float(np.mean(lst)) if lst else 0.0

    sal          = compute_candidate_salary_features(candidate, employees_by_id)
    cost_ratio   = sal["estimated_total_salary"] / max(BENCHMARK_DAILY_USD * total_assign, 1)
    ntm_ratio    = sal["night_to_morning_count"] / total_assign
    night_ratio  = s.shift_counts.get("Malam", 0) / total_assign
    soft_ratio   = s.soft_violation_count / total_assign
    dayoff_ratio = s.weekly_day_off_violations / total_assign

    return {
        "coverage_rate":          min(1.0, s.active_employees / max(len(active_ids), 1)),
        "avg_dept_weight":        m(dept_weights),
        "certified_ratio":        m(certs),
        "senior_ratio":           m(seniors),
        "night_ratio":            night_ratio,
        "night_to_morning_ratio": ntm_ratio,
        "cost_ratio":             cost_ratio,
        "cluster_balance":        float(s.cluster_balance),
        "hard_violation_count":   float(s.hard_violation_count),
        "soft_violation_ratio":   soft_ratio,
        "dayoff_violation_ratio": dayoff_ratio,
        "avg_job_level":          m(job_levels),
    }


def evaluate_candidates(
    candidates: list[ScheduleCandidate],
    employees_by_id: dict[int, Employee] | None = None,
) -> list[EvaluatedCandidate]:
    """
    Evaluasi kandidat, return sorted by final_score DESC.

    RF profit score → rescale ke [10, 100] → tidak pernah 0
    GA fitness      → normalisasi ke [0, 100] dalam batch
    final_score     = GA_norm × 0.5 + RF_score × 0.5
    total_salary    = dari salary_calculator dalam USD
    BEST            = final_score tertinggi (index 0 setelah sort)
    """
    if not candidates:
        raise ValueError("candidates tidak boleh kosong")

    model, feat_names = _load_model()
    emp_map = employees_by_id or {}

    # Total salary dari salary_calculator (USD, deterministik)
    sal_totals = [
        compute_candidate_salary_features(c, emp_map)["estimated_total_salary"]
        for c in candidates
    ]

    # RF prediksi profit score
    X       = pd.DataFrame(
        [_extract_features(c, emp_map) for c in candidates],
        columns=feat_names,
    )
    raw_rf  = model.predict(X)

    # Pakai raw RF prediction langsung (tidak di-rescale)
    # Formula profit_score sudah dikalibrasi ke range 0-85 dari data aktual:
    #   Ideal (H:0, cluster tinggi, cost rendah) → ~70-85
    #   Bagus (H:0, cluster sedang)              → ~50-65
    #   Sedang                                   → ~30-50
    #   Ada hard violation                        → <10
    # Floor 5 ditambahkan agar tidak pernah tampil 0 di UI
    # (nilai 0 bisa menyesatkan — beda antara "buruk" dan "tidak terhitung")
    rf_scores = np.clip(raw_rf, 5.0, 100.0)

    # Normalisasi GA fitness ke [0, 100]
    ga_vals = np.array([c.summary.ga_fitness for c in candidates])
    ga_min, ga_max = ga_vals.min(), ga_vals.max()
    if ga_max - ga_min < 1e-6:
        ga_norm = np.full(len(ga_vals), 50.0)
    else:
        ga_norm = (ga_vals - ga_min) / (ga_max - ga_min) * 100.0

    # Final score
    final_scores = ga_norm * 0.5 + rf_scores * 0.5

    evaluated = []
    for i, (c, rf_s, ga_n, fin_s, total_sal) in enumerate(
        zip(candidates, rf_scores, ga_norm, final_scores, sal_totals)
    ):
        data = c.model_dump()
        data["summary"]["total_salary"] = round(total_sal, 2)

        evaluated.append(EvaluatedCandidate(
            **data,
            rf_profit_score=round(float(rf_s), 4),   # 4 desimal agar tidak kembar
            predicted_salary=round(float(total_sal), 2),
            final_score=round(float(fin_s), 4),       # 4 desimal untuk konsistensi
        ))

        print(
            f"  [RF] {c.candidate_id}: "
            f"rf={rf_s:.4f}%  ga_norm={ga_n:.2f}  "
            f"final={fin_s:.4f}  salary=${total_sal:,.0f}"
        )

    # Urutan kandidat TIDAK diubah — tetap C1, C2, C3 seperti dari GA.
    # Label BEST ditentukan di frontend berdasarkan final_score tertinggi,
    # bukan berdasarkan posisi baris (index 0).
    return evaluated