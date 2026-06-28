"""
main.py — Entry point FastAPI Shiftly AI Service

Endpoint:
  POST /cluster                  → K-Means clustering employees
  POST /generate-schedules       → GA generate kandidat jadwal
  POST /evaluate-candidates      → Random Forest evaluasi kandidat
  POST /generate-and-evaluate    → GA + RF dalam satu pipeline (UTAMA)

LOGGING KE CONSOLE:
  Setiap request akan mencetak ringkasan hasil ke console (terminal FastAPI).
  Ini memudahkan monitoring saat development dan saat integrasi dengan shiftly-web.
  Log format: [ENDPOINT] detail metrik → tercetak di terminal uvicorn.
"""

import time
from fastapi import FastAPI, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware

from app.schemas import (
    ClusterRequest,
    ClusterResponse,
    EvaluateCandidatesRequest,
    EvaluateCandidatesResponse,
    GenerateScheduleRequest,
    GenerateScheduleResponse,
    GenerateAndEvaluateResponse,
)
from app.services.ga_engine import generate_candidates
from app.services.kmeans_service import cluster_employees
from app.services.rf_service import evaluate_candidates


app = FastAPI(
    title="Shiftly AI Service",
    description="K-Means, Genetic Algorithm, dan Random Forest service untuk Shiftly.",
    version="2.1.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost", "http://127.0.0.1:8000", "http://127.0.0.1:8001"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


# ── Helper logging ────────────────────────────────────────────────────────────

def _sep(char="─", width=60):
    print(char * width)


# ─────────────────────────────────────────────────────────────────────────────
# HEALTH CHECK
# ─────────────────────────────────────────────────────────────────────────────

@app.get("/")
def root() -> dict:
    return {"service": "Shiftly AI Service", "status": "running", "version": "2.1.0"}


@app.get("/health")
def health() -> dict:
    return {"status": "ok"}


# ─────────────────────────────────────────────────────────────────────────────
# ENDPOINT 1: K-MEANS CLUSTERING
# ─────────────────────────────────────────────────────────────────────────────

@app.post("/cluster", response_model=ClusterResponse)
def cluster(request: ClusterRequest) -> ClusterResponse:
    """
    Cluster employees menggunakan K-Means.

    Input : list employees + n_clusters
    Output: setiap employee mendapat label cluster (A/B/C/D)

    Logging ke console:
      - Jumlah employee per cluster
      - Distribusi cluster (%)
    """
    t0 = time.time()
    try:
        n_clusters, clusters = cluster_employees(request.employees, request.n_clusters)
    except ValueError as error:
        raise HTTPException(status_code=422, detail=str(error)) from error

    elapsed = time.time() - t0

    # ── Console log ───────────────────────────────────────────────────────────
    _sep("═")
    print("  [K-MEANS] Clustering Selesai")
    _sep()
    print(f"  Input      : {len(request.employees)} employees")
    print(f"  N Clusters : {n_clusters}")
    print(f"  Durasi     : {elapsed:.3f}s")
    print()

    # Hitung distribusi per cluster
    from collections import Counter
    dist = Counter(c["cluster_name"] for c in clusters)
    print("  Distribusi Cluster:")
    for name, count in sorted(dist.items()):
        pct = count / len(clusters) * 100
        bar = "█" * int(pct / 5)
        print(f"    Cluster {name}: {count:>3} employees ({pct:5.1f}%)  {bar}")
    _sep("═")

    return ClusterResponse(n_clusters=n_clusters, clusters=clusters)


# ─────────────────────────────────────────────────────────────────────────────
# ENDPOINT 2: GA GENERATE SCHEDULES
# ─────────────────────────────────────────────────────────────────────────────

@app.post("/generate-schedules", response_model=GenerateScheduleResponse)
def generate_schedules(request: GenerateScheduleRequest) -> GenerateScheduleResponse:
    """
    Generate kandidat jadwal optimal menggunakan Genetic Algorithm.

    Input : employees (dengan cluster), requirements, GA parameters, days
    Output: N kandidat jadwal (tanpa rf_profit_score)

    Untuk mendapatkan rf_profit_score, gunakan /generate-and-evaluate.
    """
    t0 = time.time()
    try:
        candidates = generate_candidates(request)
    except ValueError as error:
        raise HTTPException(status_code=422, detail=str(error)) from error

    elapsed = time.time() - t0

    # ── Console log ───────────────────────────────────────────────────────────
    params = request.ga_parameters
    _sep("═")
    print("  [GA] Generate Schedules Selesai")
    _sep()
    print(f"  Input Employees  : {len(request.employees)}")
    print(f"  Periode          : {request.days} hari ({request.start_date})")
    print(f"  Kandidat diminta : {request.candidates}")
    print(f"  Kandidat dihasil : {len(candidates)}")
    print(f"  Durasi           : {elapsed:.2f}s")
    print()
    print(f"  Parameter GA:")
    print(f"    Generasi       : {params.generations}")
    print(f"    Populasi       : {params.population_size}")
    print(f"    Elite Count    : {params.elite_count}")
    print(f"    Mutation Rate  : {params.mutation_rate}")
    print(f"    Tournament Size: {params.tournament_size}")
    print()

    print(f"  {'#':<4} {'Fitness':>8} {'HardVio':>8} {'SoftVio':>8} "
          f"{'DayOff':>7} {'Cluster':>8} {'Coverage':>9} {'TotalSalary':>13}")
    print(f"  {'-'*4} {'-'*8} {'-'*8} {'-'*8} {'-'*7} {'-'*8} {'-'*9} {'-'*13}")

    for i, cand in enumerate(candidates, 1):
        s = cand.summary
        cov  = round(getattr(s, 'coverage_ratio', 0) * 100, 1)
        print(f"  {i:<4} {s.ga_fitness:>8.2f} {s.hard_violation_count:>8} "
              f"{s.soft_violation_count:>8} {s.weekly_day_off_violations:>7} "
              f"{s.cluster_balance:>8.4f} {cov:>8.1f}% {s.total_salary:>13,.0f}")

    print()
    best = candidates[0].summary if candidates else None
    if best:
        print(f"  Best Candidate Fitness  : {best.ga_fitness:.2f}")
        print(f"  Best Hard Violations    : {best.hard_violation_count}")
        print(f"  Best Cluster Balance    : {best.cluster_balance:.4f}")
    _sep("═")

    return GenerateScheduleResponse(candidates=candidates)


# ─────────────────────────────────────────────────────────────────────────────
# ENDPOINT 3: RANDOM FOREST EVALUATE CANDIDATES
# ─────────────────────────────────────────────────────────────────────────────

@app.post("/evaluate-candidates", response_model=EvaluateCandidatesResponse)
def evaluate(request: EvaluateCandidatesRequest) -> EvaluateCandidatesResponse:
    """
    Evaluasi kandidat jadwal menggunakan Random Forest.

    Input : kandidat dari GA + data employees (WAJIB dikirim agar RF akurat)
    Output: kandidat dengan rf_profit_score, diurutkan dari terbaik

    PENTING: Selalu kirim field 'employees' bersamaan dengan 'candidates'.
    Tanpa employees, RF menggunakan nilai default → rf_profit_score tidak akurat.

    Untuk workflow otomatis (GA + RF sekaligus), gunakan /generate-and-evaluate.
    """
    t0 = time.time()
    try:
        # employees_by_id dibangun dari data asli yang dikirim client
        # Ini yang membuat RF bisa memprediksi salary secara akurat per kandidat
        employees_by_id = {emp.id: emp for emp in request.employees}
        candidates = evaluate_candidates(request.candidates, employees_by_id)
    except FileNotFoundError as error:
        raise HTTPException(status_code=503, detail=str(error)) from error
    except ValueError as error:
        raise HTTPException(status_code=422, detail=str(error)) from error

    elapsed = time.time() - t0

    # ── Console log ───────────────────────────────────────────────────────────
    _sep("═")
    print("  [RF] Evaluate Candidates Selesai")
    _sep()
    print(f"  Input Kandidat : {len(request.candidates)}")
    print(f"  Input Employees: {len(request.employees)}")
    print(f"  Durasi         : {elapsed:.3f}s")
    print()
    print(f"  {'Rank':<5} {'CandidateID':<14} {'RF Score':>9} {'HardVio':>8} "
          f"{'NightShift':>11} {'TotalSalary':>13}")
    print(f"  {'-'*5} {'-'*14} {'-'*9} {'-'*8} {'-'*11} {'-'*13}")

    for rank, cand in enumerate(candidates, 1):
        s = cand.summary
        nights = s.shift_counts.get("Malam", 0)
        print(f"  {rank:<5} {cand.candidate_id:<14} {cand.rf_profit_score:>9.2f} "
              f"{s.hard_violation_count:>8} {nights:>11} {s.total_salary:>13,.0f}")

    print()
    if candidates:
        best = candidates[0]
        print(f"  ✅ Kandidat Terbaik : {best.candidate_id}")
        print(f"     RF Profit Score  : {best.rf_profit_score}")
        print(f"     Hard Violations  : {best.summary.hard_violation_count}")
        print(f"     Predicted Salary (RF)     : Rp {best.summary.total_salary:,.0f}")
    _sep("═")

    return EvaluateCandidatesResponse(candidates=candidates)


# ─────────────────────────────────────────────────────────────────────────────
# ENDPOINT 4: GA + RANDOM FOREST PIPELINE (ENDPOINT UTAMA)
# ─────────────────────────────────────────────────────────────────────────────

@app.post("/generate-and-evaluate", response_model=GenerateAndEvaluateResponse)
def generate_and_evaluate(request: GenerateScheduleRequest) -> GenerateAndEvaluateResponse:
    """
    Pipeline GA + Random Forest dalam satu endpoint.

    Alur:
    1. GA generate N kandidat jadwal dari data employees + requirements
    2. Model RF langsung mengevaluasi setiap kandidat menggunakan data
       employees yang SAMA (age, job_level, education, salary, dll.)
    3. RF memprediksi estimated_daily_salary lalu menghitung rf_profit_score
    4. Kandidat dikembalikan sudah diurutkan dari rf_profit_score tertinggi

    Mengapa ini LEBIH BAIK dari dua panggilan terpisah (/generate-schedules
    lalu /evaluate-candidates)?
    ─────────────────────────────────────────────────────────────────────────
    Problem lama: /evaluate-candidates dipanggil tanpa menyertakan 'employees',
    sehingga RF terpaksa pakai nilai default (age=38.6, job_level=3.0, dll.)
    untuk SEMUA kandidat → rf_profit_score berbeda tipis antar kandidat
    (mendekati 0% karena semua input sama).

    Solusi: dalam endpoint ini, employees dikirim SEKALI dan langsung
    dipakai oleh RF → prediksi salary mencerminkan profil pegawai nyata
    (mis. kandidat dengan banyak Nurse Assistant lebih murah dari Surgeon)
    → rf_profit_score benar-benar membedakan kandidat.

    Input  : Sama persis dengan /generate-schedules
    Output : list EvaluatedCandidate (sudah berisi rf_profit_score)
    """
    t0_total = time.time()

    # ── Step 1: GA generate kandidat ─────────────────────────────────────────
    t0_ga = time.time()
    try:
        ga_candidates = generate_candidates(request)
    except ValueError as error:
        raise HTTPException(status_code=422, detail=str(error)) from error

    elapsed_ga = time.time() - t0_ga

    if not ga_candidates:
        raise HTTPException(status_code=422, detail="GA gagal menghasilkan kandidat jadwal.")

    # ── Step 2: RF evaluasi dengan data employees yang SAMA ──────────────────
    # employees_by_id dibangun dari request.employees — data asli yang sudah
    # dikirim di payload GA. Tidak perlu request terpisah, tidak ada fallback.
    t0_rf = time.time()
    try:
        employees_by_id = {emp.id: emp for emp in request.employees}
        evaluated_candidates = evaluate_candidates(ga_candidates, employees_by_id)
    except FileNotFoundError as error:
        raise HTTPException(
            status_code=503,
            detail=f"Model RF belum tersedia: {error}. Jalankan train_rf_model.py terlebih dahulu."
        ) from error
    except ValueError as error:
        raise HTTPException(status_code=422, detail=str(error)) from error

    elapsed_rf = time.time() - t0_rf
    elapsed_total = time.time() - t0_total

    # ── Console log ───────────────────────────────────────────────────────────
    params = request.ga_parameters
    _sep("═")
    print("  [GA+RF] Generate & Evaluate Pipeline Selesai")
    _sep()
    print(f"  Input Employees  : {len(request.employees)}")
    print(f"  Periode          : {request.days} hari ({request.start_date})")
    print(f"  Kandidat dihasil : {len(evaluated_candidates)}")
    print(f"  Durasi GA        : {elapsed_ga:.2f}s")
    print(f"  Durasi RF        : {elapsed_rf:.3f}s")
    print(f"  Durasi Total     : {elapsed_total:.2f}s")
    print()
    print(f"  Parameter GA:")
    print(f"    Generasi       : {params.generations}")
    print(f"    Populasi       : {params.population_size}")
    print(f"    Mutation Rate  : {params.mutation_rate}")
    print()

    # Tabel hasil — diurutkan berdasarkan rf_profit_score (sudah dari evaluate_candidates)
    print(f"  {'Rank':<5} {'CandidateID':<14} {'GA Fitness':>10} {'RF Score':>9} "
          f"{'HardVio':>8} {'SalaryEst':>12} {'TotalSalary':>13}")
    print(f"  {'-'*5} {'-'*14} {'-'*10} {'-'*9} {'-'*8} {'-'*12} {'-'*13}")

    for rank, cand in enumerate(evaluated_candidates, 1):
        s = cand.summary
        print(f"  {rank:<5} {cand.candidate_id:<14} {s.ga_fitness:>10.2f} "
              f"{cand.rf_profit_score:>9.2f} {s.hard_violation_count:>8} "
              f"{'(dari RF)':>12} {s.total_salary:>13,.0f}")

    print()
    if evaluated_candidates:
        best = evaluated_candidates[0]
        print(f"  ✅ Kandidat Terbaik (RF): {best.candidate_id}")
        print(f"     RF Profit Score       : {best.rf_profit_score}")
        print(f"     GA Fitness            : {best.summary.ga_fitness:.2f}")
        print(f"     Hard Violations       : {best.summary.hard_violation_count}")
        print(f"     Total Salary (GA)     : Rp {best.summary.total_salary:,.0f}")
    _sep("═")

    # Kembalikan kandidat yang sudah berisi rf_profit_score dan diurutkan
    return GenerateAndEvaluateResponse(candidates=evaluated_candidates)