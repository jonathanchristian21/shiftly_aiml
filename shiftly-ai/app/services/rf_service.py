"""Random Forest evaluator untuk membandingkan kandidat jadwal.

Saat model trained belum tersedia, file ini memakai fallback model sintetis
agar endpoint tetap berjalan. Nanti bisa diganti model joblib hasil training.
"""

from __future__ import annotations

import numpy as np
from sklearn.ensemble import RandomForestRegressor

from app.schemas import EvaluatedCandidate, ScheduleCandidate


_MODEL: RandomForestRegressor | None = None


def _get_fallback_model() -> RandomForestRegressor:
    global _MODEL

    if _MODEL is not None:
        return _MODEL

    rng = np.random.default_rng(42)
    rows = 400
    total_salary = rng.uniform(10, 250, rows)
    active_employees = rng.integers(3, 80, rows)
    total_assignments = rng.integers(10, 500, rows)
    ga_fitness = rng.uniform(0, 1000, rows)
    cluster_balance = rng.uniform(0, 1, rows)
    night_ratio = rng.uniform(0, 0.5, rows)
    hard_violations = rng.integers(0, 12, rows)
    soft_violations = rng.integers(0, 40, rows)
    weekly_day_off_violations = rng.integers(0, 60, rows)

    features = np.column_stack([
        total_salary,
        active_employees,
        total_assignments,
        ga_fitness,
        cluster_balance,
        night_ratio,
        hard_violations,
        soft_violations,
        weekly_day_off_violations,
    ])

    target = (
        100
        - total_salary * 0.18
        - active_employees * 0.32
        + ga_fitness * 0.035
        + cluster_balance * 18
        - night_ratio * 8
        - hard_violations * 6
        - soft_violations * 0.45
        - weekly_day_off_violations * 0.18
    )
    target = np.clip(target, 0, 100)

    _MODEL = RandomForestRegressor(n_estimators=80, random_state=42)
    _MODEL.fit(features, target)
    return _MODEL


def candidate_features(candidate: ScheduleCandidate) -> list[float]:
    summary = candidate.summary
    working_assignments = max(summary.total_assignments, 1)
    night_ratio = summary.shift_counts.get("Malam", 0) / working_assignments

    return [
        summary.total_salary / 1_000_000,
        float(summary.active_employees),
        float(summary.total_assignments),
        float(summary.ga_fitness),
        float(summary.cluster_balance),
        float(night_ratio),
        float(summary.hard_violation_count),
        float(summary.soft_violation_count),
        float(summary.weekly_day_off_violations),
    ]


def evaluate_candidates(candidates: list[ScheduleCandidate]) -> list[EvaluatedCandidate]:
    if not candidates:
        raise ValueError("candidates tidak boleh kosong")

    model = _get_fallback_model()
    features = np.array([candidate_features(candidate) for candidate in candidates])
    scores = model.predict(features)

    evaluated = [
        EvaluatedCandidate(
            **candidate.model_dump(),
            rf_profit_score=round(float(max(0, min(100, score))), 2),
        )
        for candidate, score in zip(candidates, scores)
    ]

    return sorted(evaluated, key=lambda candidate: candidate.rf_profit_score, reverse=True)
