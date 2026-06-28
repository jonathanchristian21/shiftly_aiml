"""
train_rf_model.py
=================
Training pipeline Random Forest untuk memprediksi PROFIT SCORE jadwal rumah sakit.

RF tidak lagi memprediksi salary — RF langsung memprediksi seberapa profitable
suatu kandidat jadwal berdasarkan kombinasi fitur operasional dan finansial.

KENAPA PENDEKATAN INI LEBIH BAIK:
-----------------------------------
Versi sebelumnya: RF prediksi salary → dikonversi ke skor
  Masalah: salary bisa dihitung deterministik dari salary_calculator,
  RF tidak menambah informasi baru.

Versi baru: RF langsung prediksi profit_score dari fitur jadwal
  Keuntungan: RF belajar pola INTERAKSI antar fitur yang tidak bisa
  di-capture formula sederhana. Misalnya: jadwal dengan cluster_balance
  tinggi + sedikit shift malam + pegawai bersertifikasi tinggi
  menghasilkan kombinasi cost-quality yang lebih profitable daripada
  sekadar menjumlahkan komponen-komponennya.

ALUR PIPELINE:
--------------
  1. Load Employee_Satisfaction_Index.csv
  2. Jalankan GA berkali-kali dengan variasi employee dan requirements
  3. Untuk setiap kandidat jadwal, hitung profit_score dengan formula bisnis
  4. Kumpulkan: fitur jadwal (X) + profit_score (y) → dataset training
  5. Train/test split 80/20
  6. K-Fold CV (k=5) di train set
  7. RandomizedSearchCV untuk hyperparameter tuning
  8. Evaluasi MAE, RMSE, R² di training dan test set
  9. Simpan model ke models/rf_profit_model.joblib

PROFIT SCORE FORMULA (0-100):
------------------------------
  profit_score = revenue_proxy - cost_proxy - risk_proxy

  revenue_proxy  = coverage_rate × dept_weight_avg × 40
                 (seberapa baik shift terpenuhi × pentingnya dept)

  cost_proxy     = (total_salary / benchmark_salary) × 30
                 (seberapa mahal dibanding benchmark)

  risk_proxy     = hard_violations × 8
                 + soft_violation_ratio × 10
                 + dayoff_violation_ratio × 7
                 + night_to_morning_ratio × 5

  Semua komponen dinormalisasi ke rasio (bukan nilai absolut) agar
  tidak terpengaruh ukuran jadwal (7 hari vs 14 hari, sedikit vs banyak pegawai).
"""

from __future__ import annotations

import os, sys, warnings
warnings.filterwarnings("ignore")
sys.path.insert(0, os.path.dirname(__file__))

import numpy as np
import pandas as pd
import joblib

from datetime import date
from sklearn.ensemble import RandomForestRegressor
from sklearn.model_selection import KFold, RandomizedSearchCV, train_test_split
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score

from app.schemas import (
    Employee, DepartmentShiftRequirement, GenerateScheduleRequest, GAParameters
)
from app.services.ga_engine import generate_candidates
from app.services.salary_calculator import compute_candidate_salary_features

# ── Path ──────────────────────────────────────────────────────────────────────
CSV_PATH    = os.path.join(os.path.dirname(__file__), "Employee_Satisfaction_Index.csv")
MODEL_DIR   = os.path.join(os.path.dirname(__file__), "models")
MODEL_PATH  = os.path.join(MODEL_DIR, "rf_profit_model.joblib")
FEAT_PATH   = os.path.join(MODEL_DIR, "rf_profit_feature_names.joblib")

# ── Konstanta bisnis ──────────────────────────────────────────────────────────
# Tier department: Emergency/Surgeon paling kritis, Nurse Assistant paling rendah
DEPT_TIER: dict[str, int] = {
    "Emergency Physician": 1, "Surgeon": 1, "Orthopedic Surgeon": 1,
    "Cardiologist": 1, "Neurologist": 1, "Anesthesiologist": 1,
    "Oncologist": 2, "Radiologist": 2, "Pediatrician": 2, "General Practitioner": 2,
    "Registered Nurse": 3, "Nurse Practitioner": 3, "Nurse Assistant": 3,
}
TIER_WEIGHT = {1: 1.5, 2: 1.0, 3: 0.6}

# Benchmark salary harian (USD) untuk cost_proxy normalization
# Dataset salary range 24076-86750 (anggap USD), daily = salary/22
# Mean daily = ~2292 USD, × avg_active_emps (~15-30) × days (7-14)
BENCHMARK_DAILY_PER_EMP_USD = 2292.0


# ── STEP 1: Load & prep employees dari CSV ────────────────────────────────────

def load_employees(csv_path: str) -> list[Employee]:
    """
    Load CSV → list[Employee] untuk dipakai GA.
    Kolom yang tidak relevan di-drop, Dept di-encode ke department_id via tier.
    """
    df = pd.read_csv(csv_path)
    employees = []
    for i, row in df.iterrows():
        dept = str(row.get("Dept", "Registered Nurse")).strip()
        tier = DEPT_TIER.get(dept, 3)
        employees.append(Employee(
            id=int(i) + 1,
            department_id=tier,
            department=dept,
            education=str(row.get("education", "UG")).strip().upper(),
            job_level=int(row.get("job_level", 2)),
            age=int(row.get("age", 30)),
            salary=float(row.get("salary", 40000)),
            rating=float(row.get("rating", 3.0)),
            satisfied=int(row.get("satisfied", 3)),
            certifications=int(row.get("certifications", 0)),
            awards=int(row.get("awards", 0)),
            onsite=int(row.get("onsite", 0)),
            cluster=None,
            is_senior=str(row.get("education", "UG")).strip().upper() == "PG",
        ))
    return employees


# ── STEP 2: Hitung profit_score per kandidat ──────────────────────────────────

def compute_profit_score(candidate, employees_by_id: dict, requirements: list) -> float:
    """
    Hitung profit_score (0-100) per kandidat jadwal.

    RECALIBRATION (v2): formula sebelumnya terlalu bergantung pada cluster_balance
    dan dept_weight yang range-nya sempit di data aktual → score stuck 27-31.

    Kalibrasi baru berdasarkan range AKTUAL kondisi GA dengan fitness terpisah:
      cluster_balance : 0.30 - 0.95  (lebih lebar, karena GA tanpa cluster reward)
      coverage_ratio  : 0.70 - 1.00  (seberapa baik slot terpenuhi)
      cost_ratio      : 0.50 - 1.20  (biaya vs benchmark)
      soft_ratio      : 0.10 - 0.30
      dayoff_ratio    : 0.20 - 0.55
      hard_violation  : 0 - 100+

    KOMPONEN BARU:
    --------------
    coverage_reward (0-40): KOMPONEN TERBESAR — seberapa baik semua shift terpenuhi.
                            Hard constraint terpenuhi = layanan medis berjalan = revenue.
                            Formula: ((coverage - 0.70) / 0.30) × 40
                            coverage 1.0 (sempurna) → +40, coverage 0.70 → 0.

    cluster_reward (0-25): distribusi cluster merata = tim lebih efektif.
                           Dikalibrasi ulang: baseline 0.30 (bukan 0.65).
                           Formula: ((cluster - 0.30) / 0.65) × 25

    hard_penalty (×8): setiap hard violation langsung potong 8 poin.
                       Lebih tinggi dari sebelumnya (×5) karena H-vio = revenue hilang.

    cost_penalty (0-15): cost_ratio di atas 1.0 (di atas benchmark) → profit turun.

    soft_penalty (0-10): soft violation mempengaruhi ergonomi dan kelelahan staf.

    dayoff_penalty (0-8): kekurangan libur → risiko burnout → produktivitas turun.

    ntm_penalty (0-5): malam→pagi = kelelahan akut → risiko medis.

    TARGET RANGE (dikalibrasi ulang):
      Ideal  (H:0, coverage=1.0, cluster≥0.80, cost rendah)    → 70-85
      Bagus  (H:0, coverage≥0.90, cluster≥0.60, cost sedang)   → 50-70
      Sedang (H:0, coverage≥0.80, cluster sedang)               → 35-50
      Rendah (H:0, coverage rendah atau cluster sangat rendah)  → 15-35
      Ada hard violation (H≥1)                                  →  0-15
    """
    s            = candidate.summary
    total_assign = max(s.total_assignments, 1)
    active_ids   = set(a.employee_id for a in candidate.assignments if a.shift != "Libur")

    # Coverage ratio: berapa slot requirement yang benar-benar terpenuhi
    # Estimasi dari hard_violation_count (tiap vio = 1 slot kurang)
    # Hitung dari requirements jika tersedia
    req_total = sum(r.get("required_staff", 0) for r in requirements) if requirements else 0
    if req_total > 0:
        days_est  = total_assign / max(len(active_ids), 1) / (5/7)  # estimasi hari
        req_slots = req_total * max(1, round(days_est))
        coverage_ratio = max(0.0, min(1.0, 1.0 - s.hard_violation_count / max(req_slots, 1)))
    else:
        # Fallback: pakai coverage dari summary jika ada, atau estimasi dari H-count
        coverage_ratio = max(0.0, 1.0 - (s.hard_violation_count * 0.01))

    # Rata-rata dept_weight pegawai aktif
    dept_weights = []
    for eid in active_ids:
        emp  = employees_by_id.get(eid)
        tier = DEPT_TIER.get(emp.department or "", 3) if emp else 3
        dept_weights.append(TIER_WEIGHT[tier])
    avg_dept_weight = float(np.mean(dept_weights)) if dept_weights else 0.6

    # Hitung komponen dari salary_calculator
    sal_feats    = compute_candidate_salary_features(candidate, employees_by_id)
    actual_total = sal_feats["estimated_total_salary"]
    cost_ratio   = actual_total / max(BENCHMARK_DAILY_PER_EMP_USD * total_assign, 1)
    ntm_ratio    = sal_feats["night_to_morning_count"] / total_assign
    soft_ratio   = s.soft_violation_count / total_assign
    dayoff_ratio = s.weekly_day_off_violations / total_assign
    cluster      = float(s.cluster_balance)

    # ── REWARD ────────────────────────────────────────────────────────────
    # coverage_reward: komponen terbesar (40 poin max)
    coverage_reward = float(np.clip(((coverage_ratio - 0.70) / 0.30) * 40, 0, 40))

    # cluster_reward: dikalibrasi ulang dari baseline 0.30 (bukan 0.65)
    cluster_reward  = float(np.clip(((cluster - 0.30) / 0.65) * 25, 0, 25))

    # dept_weight reward: bonus kecil jika ada spesialis (Tier1/2)
    dw_reward       = float(np.clip(((avg_dept_weight - 0.60) / 0.90) * 10, 0, 10))

    # ── PENALTY ───────────────────────────────────────────────────────────
    hard_penalty   = s.hard_violation_count * 8.0          # lebih tajam dari ×5
    cost_penalty   = float(np.clip(((cost_ratio - 0.80) / 0.40) * 15, 0, 15))
    soft_penalty   = float(np.clip(((soft_ratio - 0.10) / 0.20) * 10, 0, 10))
    dayoff_penalty = float(np.clip(((dayoff_ratio - 0.20) / 0.35) * 8, 0, 8))
    ntm_penalty    = float(np.clip((ntm_ratio / 0.03) * 5, 0, 5))

    # base = 25 (lebih rendah dari 30 sebelumnya karena reward lebih besar)
    score = (25.0 + coverage_reward + cluster_reward + dw_reward
             - hard_penalty - cost_penalty
             - soft_penalty - dayoff_penalty - ntm_penalty)
    return float(np.clip(score, 0.0, 100.0))


# ── STEP 3: Ekstrak fitur jadwal untuk input RF ───────────────────────────────

def extract_schedule_features(candidate, employees_by_id: dict) -> dict:
    """
    Ekstrak 12 fitur dari kandidat jadwal.
    Semua fitur dinormalisasi ke rasio agar skala-invariant.

    FITUR:
    ------
    1.  coverage_rate          : shift terpenuhi / total shift dibutuhkan
    2.  avg_dept_weight        : rata-rata bobot tier dept pegawai aktif
    3.  certified_ratio        : rasio pegawai bersertifikasi dari aktif
    4.  senior_ratio           : rasio pegawai senior (PG) dari aktif
    5.  night_ratio            : shift malam / total assignment
    6.  night_to_morning_ratio : malam→pagi / total assignment
    7.  cost_ratio             : total_salary / benchmark
    8.  cluster_balance        : distribusi cluster (0-1)
    9.  hard_violation_count   : jumlah hard violation (absolut, bukan rasio)
    10. soft_violation_ratio   : soft violation / total assignment
    11. dayoff_violation_ratio : dayoff violation / total assignment
    12. avg_job_level          : rata-rata job_level pegawai aktif
    """
    s             = candidate.summary
    total_assign  = max(s.total_assignments, 1)
    active_ids    = set(a.employee_id for a in candidate.assignments if a.shift != "Libur")

    # Fitur dari pegawai aktif
    dept_weights, certs, seniors, job_levels = [], [], [], []
    for eid in active_ids:
        emp = employees_by_id.get(eid)
        if emp:
            tier = DEPT_TIER.get(emp.department or "", 3)
            dept_weights.append(TIER_WEIGHT[tier])
            certs.append(1 if getattr(emp, "certifications", 0) >= 1 else 0)
            seniors.append(1 if (emp.education or "").upper() == "PG" else 0)
            job_levels.append(float(emp.job_level))

    def m(lst): return float(np.mean(lst)) if lst else 0.0

    sal_feats    = compute_candidate_salary_features(candidate, employees_by_id)
    actual_total = sal_feats["estimated_total_salary"]
    benchmark    = BENCHMARK_DAILY_PER_EMP_USD * total_assign
    cost_ratio   = actual_total / max(benchmark, 1)
    ntm_ratio    = sal_feats["night_to_morning_count"] / total_assign
    night_ratio  = s.shift_counts.get("Malam", 0) / total_assign
    soft_ratio   = s.soft_violation_count / total_assign
    dayoff_ratio = s.weekly_day_off_violations / total_assign

    # Fitur diferensial baru
    n_active          = max(len(active_ids), 1)
    n_days_est        = max(total_assign // max(n_active, 1), 1)
    assignment_density= total_assign / max(n_active * n_days_est, 1)

    senior_ids        = set(eid for eid in active_ids
                           if employees_by_id.get(eid)
                           and (employees_by_id[eid].education or "").upper() == "PG")
    req_senior_slots  = sum(1 for r in candidate.constraint_reports if r.required_senior > 0)
    filled_sr_slots   = sum(1 for r in candidate.constraint_reports
                            if r.required_senior > 0 and r.actual_senior >= r.required_senior)
    senior_cov_rate   = filled_sr_slots / max(req_senior_slots, 1)

    salary_per_active = sal_feats["estimated_total_salary"] / n_active

    night_asgns       = [a for a in candidate.assignments if a.shift == "Malam"]
    sr_at_night       = sum(1 for a in night_asgns if a.employee_id in senior_ids)
    night_sr_ratio    = sr_at_night / max(len(senior_ids) * n_days_est, 1)

    return {
        "coverage_rate":           min(1.0, s.active_employees / n_active),
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
        "assignment_density":      assignment_density,
        "senior_coverage_rate":    senior_cov_rate,
        "salary_per_active_emp":   salary_per_active,
        "night_senior_ratio":      night_sr_ratio,
    }

FEATURE_NAMES = [
    # Fitur absolut
    "coverage_rate", "avg_dept_weight", "certified_ratio", "senior_ratio",
    "night_ratio", "night_to_morning_ratio", "cost_ratio", "cluster_balance",
    "hard_violation_count", "soft_violation_ratio", "dayoff_violation_ratio",
    "avg_job_level",
    # Fitur diferensial (lebih diskriminatif antar kandidat)
    "assignment_density",    # total_assignments / (active_employees x days)
    "senior_coverage_rate",  # pct slot senior yang terpenuhi
    "salary_per_active_emp", # total_salary / active_employees
    "night_senior_ratio",    # senior yang kena malam / total senior aktif
]


# ── STEP 4: Build training dataset dari hasil GA ──────────────────────────────

def build_dataset(all_employees: list[Employee], n_rounds: int = 60) -> tuple[np.ndarray, np.ndarray]:
    """
    Generate dataset training dengan menjalankan GA berkali-kali
    menggunakan variasi subset employee dan requirements.

    KENAPA variasi:
      Supaya RF belajar dari kondisi jadwal yang beragam:
      - Sedikit vs banyak pegawai (termasuk skenario 300-500 employee)
      - Dominasi Tier 1 vs Tier 3
      - Jadwal 7, 14, hingga 31 hari (sesuai kondisi produksi)
      Tanpa variasi skenario besar, RF tidak bisa menilai jadwal 31 hari
      dengan benar dan cenderung memberi skor rendah untuk semua kandidat.

    DISTRIBUSI ROUNDS (n_rounds=60):
      - 40% small  : 12–30% employee, 7–14 hari  (kondisi kecil, baseline)
      - 35% medium : 30–70% employee, 14–21 hari  (kondisi menengah)
      - 25% large  : 70–100% employee, 21–31 hari (kondisi produksi nyata)

    60 rounds × 3 kandidat = 180 baris — cukup untuk RF belajar pola besar.
    """
    rng      = np.random.default_rng(42)
    X_list, y_list = [], []
    total    = len(all_employees)

    # Distribusi skenario
    n_small  = int(n_rounds * 0.40)
    n_medium = int(n_rounds * 0.35)
    n_large  = n_rounds - n_small - n_medium

    scenarios = (
        [("small",  r) for r in range(n_small)]
        + [("medium", r) for r in range(n_medium)]
        + [("large",  r) for r in range(n_large)]
    )
    rng.shuffle(scenarios)

    print(f"\n[BUILD DATASET] {n_rounds} rounds × 3 kandidat "
          f"(small={n_small}, medium={n_medium}, large={n_large})...")

    for round_idx, (scenario, r) in enumerate(scenarios):
        seed = round_idx * 13 + 7

        # Tentukan ukuran subset dan jumlah hari berdasarkan skenario
        if scenario == "small":
            size_min = max(12, int(total * 0.12))
            size_max = max(size_min + 1, int(total * 0.30))
            day_choices = [7, 7, 14]           # lebih sering 7 hari
        elif scenario == "medium":
            size_min = max(20, int(total * 0.30))
            size_max = max(size_min + 1, int(total * 0.70))
            day_choices = [14, 14, 21]
        else:  # large
            size_min = max(30, int(total * 0.70))
            size_max = total
            day_choices = [21, 28, 31, 31]     # lebih sering 31 hari

        size    = int(rng.integers(size_min, size_max + 1))
        indices = rng.choice(total, size=size, replace=False)
        subset  = [all_employees[i] for i in indices]

        # Assign cluster sederhana berdasarkan job_level
        for emp in subset:
            emp.cluster = (emp.job_level - 1) % 4 + 1

        n_days = int(rng.choice(day_choices))

        # Requirements dari dept unik di subset
        # Untuk skenario besar, staff per shift lebih banyak
        dept_ids = list(set(e.department_id for e in subset))
        reqs     = []
        req_list = []
        for did in dept_ids:
            cnt = sum(1 for e in subset if e.department_id == did)
            if scenario == "large":
                staff = max(2, min(8, cnt // 6))
            elif scenario == "medium":
                staff = max(1, min(5, cnt // 5))
            else:
                staff = max(1, min(3, cnt // 4))
            senior = 1 if did == 1 else 0
            for sh in ["Pagi", "Sore", "Malam"]:
                reqs.append(DepartmentShiftRequirement(
                    department_id=did, shift=sh,
                    required_staff=staff, required_senior=senior,
                ))
                req_list.append({"department_id": did, "shift": sh,
                                 "required_staff": staff, "required_senior": senior})

        try:
            request = GenerateScheduleRequest(
                employees=subset,
                start_date=date(2025, 1, 6),
                days=n_days, candidates=3,
                requirements=reqs,
                ga_parameters=GAParameters(
                    population_size=25, generations=35,
                    elite_count=2, tournament_size=3,
                    crossover_parent_one_rate=0.8, mutation_rate=0.08,
                ),
                seed=seed,
            )
            candidates = generate_candidates(request)
            emp_map    = {e.id: e for e in subset}

            scores = [compute_profit_score(c, emp_map, req_list) for c in candidates]
            print(f"  [{scenario:6s}] Round {round_idx+1:2d}/{n_rounds}: "
                  f"{size:4d} emps | {n_days:2d}d | scores={[round(s,1) for s in scores]}")

            for c, score in zip(candidates, scores):
                feats = extract_schedule_features(c, emp_map)
                X_list.append([feats[fn] for fn in FEATURE_NAMES])
                y_list.append(score)

        except Exception as e:
            print(f"  [{scenario:6s}] Round {round_idx+1:2d}/{n_rounds}: SKIP — {e}")

    X = np.array(X_list)
    y = np.array(y_list)
    print(f"\n[BUILD DATASET] Selesai: {len(X)} baris")
    print(f"  profit_score: min={y.min():.1f} max={y.max():.1f} mean={y.mean():.1f} std={y.std():.1f}")
    return X, y


# ── STEP 5: Training RF ───────────────────────────────────────────────────────

def train(X: np.ndarray, y: np.ndarray) -> None:
    os.makedirs(MODEL_DIR, exist_ok=True)

    print(f"\n[TRAINING] Dataset: {X.shape[0]} baris × {X.shape[1]} fitur")

    # Train/test split 80/20 — test disisihkan dulu
    X_tr, X_te, y_tr, y_te = train_test_split(X, y, test_size=0.2, random_state=42)
    print(f"  Train={len(X_tr)} | Test={len(X_te)}")

    # K-Fold baseline
    print(f"\n[K-FOLD BASELINE] k=5 di train set...")
    kf        = KFold(n_splits=5, shuffle=True, random_state=42)
    base_rf   = RandomForestRegressor(n_estimators=100, random_state=42, n_jobs=-1)
    mae_folds, rmse_folds, r2_folds = [], [], []

    for fold, (tr_i, val_i) in enumerate(kf.split(X_tr), 1):
        m = RandomForestRegressor(n_estimators=100, random_state=42, n_jobs=-1)
        m.fit(X_tr[tr_i], y_tr[tr_i])
        pred    = m.predict(X_tr[val_i])
        mae_folds.append(mean_absolute_error(y_tr[val_i], pred))
        rmse_folds.append(mean_squared_error(y_tr[val_i], pred) ** 0.5)
        r2_folds.append(r2_score(y_tr[val_i], pred))
        print(f"  Fold {fold}: MAE={mae_folds[-1]:.3f} RMSE={rmse_folds[-1]:.3f} R²={r2_folds[-1]:.4f}")

    print(f"  Avg: MAE={np.mean(mae_folds):.3f} RMSE={np.mean(rmse_folds):.3f} R²={np.mean(r2_folds):.4f}")

    base_rf.fit(X_tr, y_tr)

    # Hyperparameter tuning
    print(f"\n[TUNING] RandomizedSearchCV (n_iter=40, cv=5) di train set...")
    param_dist = {
        "n_estimators":      [50, 100, 150, 200, 300],
        "max_depth":         [None, 5, 10, 15, 20],
        "min_samples_split": [2, 4, 6, 10],
        "min_samples_leaf":  [1, 2, 4],
        "max_features":      ["sqrt", "log2", 0.5, 0.7],
        "bootstrap":         [True, False],
    }
    search = RandomizedSearchCV(
        RandomForestRegressor(random_state=42, n_jobs=-1),
        param_dist, n_iter=40, cv=5,
        scoring="neg_mean_absolute_error",
        random_state=42, n_jobs=-1, verbose=0,
    )
    search.fit(X_tr, y_tr)
    tuned_rf = search.best_estimator_
    print(f"  Best params: {search.best_params_}")
    print(f"  Best CV MAE: {-search.best_score_:.3f}")

    # Evaluasi final di test set
    print(f"\n{'='*55}")
    print(f"EVALUASI METRIK FINAL")
    print(f"{'='*55}")

    def _eval(model, X, y, label):
        pred = model.predict(X)
        mae  = mean_absolute_error(y, pred)
        rmse = mean_squared_error(y, pred) ** 0.5
        r2   = r2_score(y, pred)
        print(f"\n  [{label}]")
        print(f"    MAE  : {mae:.4f}")
        print(f"    RMSE : {rmse:.4f}  (selalu ≥ MAE, ini normal)")
        print(f"    R²   : {r2:.4f}")
        return mae, rmse, r2

    print("\n  -- Training Set --")
    _eval(base_rf,  X_tr, y_tr, "Baseline - Train")
    _eval(tuned_rf, X_tr, y_tr, "Tuned    - Train")
    print("\n  -- Test Set (belum pernah dilihat model) --")
    b_mae, _, _  = _eval(base_rf,  X_te, y_te, "Baseline - Test")
    t_mae, _, _  = _eval(tuned_rf, X_te, y_te, "Tuned    - Test")

    best_model = tuned_rf if t_mae <= b_mae else base_rf
    label      = "TUNED" if t_mae <= b_mae else "BASELINE"
    print(f"\n  → Model {label} dipilih (MAE test lebih rendah)")

    # Feature importance
    print(f"\n  Feature Importance (top 5):")
    imp = pd.Series(best_model.feature_importances_, index=FEATURE_NAMES).sort_values(ascending=False)
    for feat, val in imp.head(5).items():
        print(f"    {feat:30s} {val:.4f}")

    # Simpan
    joblib.dump(best_model, MODEL_PATH)
    joblib.dump(FEATURE_NAMES, FEAT_PATH)
    print(f"\n✅ Model disimpan: {MODEL_PATH}")
    print(f"{'='*55}")


# ── MAIN ──────────────────────────────────────────────────────────────────────

def main():
    print("=" * 55)
    print("  SHIFTLY — RF Profit Score Training Pipeline")
    print("=" * 55)

    print("\n[STEP 1] Load employees dari CSV...")
    employees = load_employees(CSV_PATH)
    print(f"  {len(employees)} employees dimuat")

    # Cluster sederhana untuk seluruh pool
    for emp in employees:
        emp.cluster = (emp.job_level - 1) % 4 + 1

    print("\n[STEP 2-3] Build training dataset dari GA...")
    X, y = build_dataset(employees, n_rounds=60)

    if len(X) < 50:
        print("Dataset terlalu kecil, tambah n_rounds")
        sys.exit(1)

    print("\n[STEP 4] Training Random Forest...")
    train(X, y)

    print("\n✅ Selesai! Restart FastAPI untuk load model baru.")


if __name__ == "__main__":
    main()