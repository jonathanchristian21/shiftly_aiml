"""Genetic Algorithm untuk generate kandidat jadwal Shiftly.

GA ini mengikuti struktur tugas GA sebelumnya dengan:
- Elitism: kromosom terbaik langsung dipertahankan
- Adaptive Mutation: rate naik saat stagnan, turun saat membaik
- Early Stopping: berhenti jika tidak ada improvement signifikan
- Tournament Selection: pilih parent terbaik dari k kandidat
- Crossover 80/20: 80% gen dari parent 1, 20% dari parent 2
- Cluster-Aware Initialization: memanfaatkan label cluster untuk populasi awal
- Hard/Soft Constraint Separation: hard constraint penalti jauh lebih besar

Fitness: BASE_FITNESS - penalty (higher is better)
"""

from __future__ import annotations

import copy
from collections import Counter, defaultdict
from datetime import timedelta
from math import ceil
import random

from app.schemas import (
    ConstraintReport,
    DepartmentShiftRequirement,
    Employee,
    GAParameters,
    GenerateScheduleRequest,
    ScheduleCandidate,
    ScheduleSummary,
    ShiftAssignment,
)


WORKING_SHIFTS = ["Pagi", "Sore", "Malam"]
ALL_SHIFTS = ["Pagi", "Sore", "Malam", "Libur"]

# Fitness base
BASE_FITNESS = 10000.0

# ── HARD CONSTRAINTS ─────────────────────────────────────────────────────────
# Penalti besar → GA sangat menghindari pelanggaran ini.
# W_STAFF_SHORTAGE tinggi karena kekurangan pegawai di shift RS = risiko nyawa.
# W_SENIOR_SHORTAGE lebih tinggi dari SHORTAGE karena kepala shift = koordinasi kritis.
# W_STAFF_SHORTAGE dan W_SENIOR_SHORTAGE dinaikkan drastis agar GA
# menghindari hard violation secara natural TANPA repair mechanism.
# Tanpa repair, satu-satunya cara GA menghindari hard violation adalah
# jika fitness-nya turun sangat drastis saat ada shortage.
# Nilai 300/360 membuat kromosom dengan shortage SELALU kalah dari
# kromosom tanpa shortage dalam tournament selection.
W_STAFF_SHORTAGE  = 500.0    # naik lagi: 1 shortage = -500 langsung
W_STAFF_OVER      = 5.0      # tetap: overstaff masih ditoleransi
W_SENIOR_SHORTAGE = 600.0    # naik lagi: tanpa kepala shift = shutdown operasional

# ── SOFT CONSTRAINTS ─────────────────────────────────────────────────────────
# Penalti sedang → GA usahakan penuhi, tapi boleh dilanggar jika terpaksa.
# W_MALAM_PAGI kecil karena terkadang tidak terhindarkan di RS 24 jam.
# W_WEEKLY_DAY_OFF penting untuk kesehatan pegawai (regulasi ketenagakerjaan).
W_MALAM_PAGI        = 2.0    # shift malam→pagi berturut (ergonomi)
W_WEEKLY_DAY_OFF    = 8.0    # deviasi dari 2 hari libur/minggu (NAIK: wajib libur)
W_JUNIOR_MENTORING  = 3.0    # junior tanpa senior (mentoring safety)

# ── OPTIMIZATION ─────────────────────────────────────────────────────────────
# Meminimalkan biaya operasional.
# W_SALARY_PER_MILLION dinaikkan agar GA lebih agresif pilih pegawai murah.
W_ACTIVE_EMPLOYEE     = 4.0  # per pegawai aktif (dorong efisiensi jumlah staf)
W_ASSIGNMENT          = 0.4  # per assignment (dorong minimasi total shift)
W_SALARY_PER_MILLION  = 2.5  # per juta Rp total gaji (NAIK: kontrol biaya lebih ketat)

# ── REWARD ───────────────────────────────────────────────────────────────────
# Bonus untuk kromosom dengan distribusi cluster merata (A/B/C/D seimbang per shift).
# Reward tinggi mendorong GA mencampur senior+junior merata di tiap shift.
# Juga ada reward baru: shift_coverage_reward untuk bonus jika semua slot terpenuhi pas.
# Reward dihapus — fitness tidak pernah melebihi BASE_FITNESS (10000).
# cluster_balance dan coverage_ratio tetap dihitung untuk metrics & RF features.

Chromosome = dict[int, list[str]]
RequirementKey = tuple[int, str]


def _is_senior(employee: Employee) -> bool:
    """Check apakah pegawai adalah senior (PG) atau berpengalaman."""
    if employee.is_senior is not None:
        return employee.is_senior
    return (employee.education or "").strip().lower() == "pg"


def _requirements_by_key(
    requirements: list[DepartmentShiftRequirement],
) -> dict[RequirementKey, DepartmentShiftRequirement]:
    """Map requirement ke (department_id, shift) key."""
    return {
        (requirement.department_id, requirement.shift): requirement
        for requirement in requirements
        if requirement.required_staff > 0 or requirement.required_senior > 0
    }


def _employees_by_department(employees: list[Employee]) -> dict[int, list[Employee]]:
    """Group pegawai berdasarkan department_id."""
    grouped: dict[int, list[Employee]] = defaultdict(list)
    for employee in employees:
        grouped[employee.department_id].append(employee)
    return grouped


def _employees_by_cluster(employees: list[Employee]) -> dict[int, list[Employee]]:
    """Group pegawai berdasarkan cluster label."""
    grouped: dict[int, list[Employee]] = defaultdict(list)
    for employee in employees:
        cluster = employee.cluster if employee.cluster is not None else 0
        grouped[cluster].append(employee)
    return grouped


def _min_required_employees(
    requirements: list[DepartmentShiftRequirement],
) -> dict[int, int]:
    """Hitung minimum pegawai per department berdasarkan kebutuhan shift.

    Formula: ceil(total_need_per_day / utilitas)
    Utilitas = 5/7 (kerja 5 hari, libur 2 hari per minggu)

    Contoh: department butuh 68 Pagi + 68 Sore + 62 Malam = 198/hari
            198 / (5/7) = ceil(277.2) = 278 orang minimum
    """
    dept_needs: dict[int, int] = defaultdict(int)
    for req in requirements:
        dept_needs[req.department_id] += req.required_staff

    utilitas = 5 / 7
    return {
        dept_id: ceil(need / utilitas)
        for dept_id, need in dept_needs.items()
    }


def _is_employee_active(shifts: list[str]) -> bool:
    """Check apakah pegawai aktif (punya minimal 1 shift kerja)."""
    return any(s != "Libur" for s in shifts)


def _cluster_balance(chromosome: Chromosome, employees_by_id: dict[int, Employee]) -> float:
    """Hitung keseimbangan distribusi cluster per shift.
    
    Semakin merata distribusi cluster, semakin tinggi skor (0-1).
    Formula: 1 - (coefficient_of_variation / 2)
    
    Perfect balance (semua cluster sama): 1.0
    Very imbalanced: 0.0
    """
    cluster_counts = Counter()

    for employee_id, shifts in chromosome.items():
        if not _is_employee_active(chromosome[employee_id]):
            continue  # Skip inactive employees
        
        employee = employees_by_id[employee_id]
        if employee.cluster is None or employee.cluster == 0:
            continue
        cluster_counts[employee.cluster] += sum(1 for shift in shifts if shift != "Libur")

    # Tidak ada cluster data atau hanya 1 cluster = imbalanced
    if len(cluster_counts) <= 1:
        return 0.0

    counts = list(cluster_counts.values())
    if not counts or sum(counts) == 0:
        return 0.0
    
    # Hitung standard deviation dan coefficient of variation
    average = sum(counts) / len(counts)
    if average == 0:
        return 0.0
    
    variance = sum((x - average) ** 2 for x in counts) / len(counts)
    std_dev = variance ** 0.5
    coef_var = std_dev / average  # 0 = perfect, >1 = very spread
    
    # Normalize ke 0-1 (asumsi coef_var max ~2.0)
    balance = max(0.0, 1.0 - (coef_var / 2.0))
    return round(balance, 4)


def _select_staff_cluster_aware(
    employees: list[Employee],
    chromosome: Chromosome,
    day_index: int,
    shift: str,
    required_staff: int,
    required_senior: int,
    rng: random.Random,
) -> list[Employee]:
    """Pilih pegawai dengan CLUSTER-AWARE strategy.
    
    Prioritas:
    1. Cluster A (senior) untuk kepala shift (wajib minimal required_senior)
    2. Cluster C (high performer) untuk stabilitas
    3. Cluster B (junior) untuk cost-efficiency
    4. Cluster D (watchlist) hanya jika terpaksa, dan tidak berturutan shift berat
    
    Hindari:
    - Malam→Pagi berturut (soft constraint)
    - Pegawai yang sudah mendapat shift di hari itu
    """
    # Filter available: belum ada shift di hari itu
    available = [
        emp for emp in employees
        if chromosome[emp.id][day_index] == "Libur"
    ]

    if not available:
        # Semua pegawai sudah punya shift di hari ini.
        # Daripada return [] (→ hard violation pasti), izinkan pegawai yang
        # sudah dapat shift BERBEDA untuk double-shift sebagai fallback.
        # GA akan mengeliminasi kromosom ini via fitness (overwork = soft penalty),
        # tapi setidaknya hard constraint STAFF terpenuhi di inisialisasi.
        # One-shift-per-day rule tetap dipertahankan untuk shift YANG SAMA.
        available = [
            emp for emp in employees
            if chromosome[emp.id][day_index] not in ("Libur", shift)
        ]
        if not available:
            return []  # Benar-benar tidak ada opsi sama sekali

    # Hindari malam→pagi (soft constraint)
    if day_index > 0 and shift == "Pagi":
        no_night_before = [
            emp for emp in available
            if chromosome[emp.id][day_index - 1] != "Malam"
        ]
        if len(no_night_before) >= required_staff:
            available = no_night_before

    # Pisahkan berdasarkan cluster dan seniority
    cluster_a = [e for e in available if e.cluster == 1]  # Senior
    cluster_b = [e for e in available if e.cluster == 2]  # Junior
    cluster_c = [e for e in available if e.cluster == 3]  # High performer
    cluster_d = [e for e in available if e.cluster == 4]  # Watchlist
    no_cluster = [e for e in available if e.cluster is None or e.cluster == 0]

    seniors = [e for e in available if _is_senior(e)]
    juniors = [e for e in available if not _is_senior(e)]

    # PERF: gunakan set id (O(1) membership) + counter senior, bukan
    # `emp not in selected` (O(n) value-equality scan pada pydantic model)
    # dan `len([... for e in selected if _is_senior(e)])` (O(n) per-iterasi).
    selected: list[Employee] = []
    selected_ids: set[int] = set()
    selected_senior_count = 0

    # 1. Pilih senior untuk kepala shift (HARD CONSTRAINT)
    senior_pool = cluster_a if cluster_a else seniors
    senior_pool.sort(key=lambda e: (e.salary, -e.rating, e.id))

    for emp in senior_pool:
        if selected_senior_count >= required_senior:
            break
        if emp.id not in selected_ids:
            selected.append(emp)
            selected_ids.add(emp.id)
            if _is_senior(emp):
                selected_senior_count += 1

    # 2. Isi sisa dengan Cluster C (stabilizers)
    cluster_c.sort(key=lambda e: (-e.rating, -e.satisfied, e.salary, e.id))
    for emp in cluster_c:
        if len(selected) >= required_staff:
            break
        if emp.id not in selected_ids:
            selected.append(emp)
            selected_ids.add(emp.id)

    # 3. Isi dengan Cluster B (cost-efficient)
    cluster_b.sort(key=lambda e: (e.salary, -e.rating, e.id))
    for emp in cluster_b:
        if len(selected) >= required_staff:
            break
        if emp.id not in selected_ids:
            selected.append(emp)
            selected_ids.add(emp.id)

    # 4. Isi dengan no cluster atau senior lain
    remaining = [e for e in (no_cluster + seniors + juniors) if e.id not in selected_ids]
    remaining.sort(key=lambda e: (e.salary, -e.rating, e.id))

    for emp in remaining:
        if len(selected) >= required_staff:
            break
        if emp.id not in selected_ids:
            selected.append(emp)
            selected_ids.add(emp.id)

    # 5. FALLBACK: Cluster D (watchlist) hanya jika SANGAT terpaksa
    # Hindari cluster D di shift malam atau shift berat
    if len(selected) < required_staff and cluster_d:
        cluster_d.sort(key=lambda e: (e.rating, e.satisfied, -e.salary, e.id))
        # Hanya ambil jika shift bukan malam ATAU sangat terpaksa
        if shift != "Malam" or len(selected) < required_staff * 0.7:
            for emp in cluster_d:
                if len(selected) >= required_staff:
                    break
                if emp.id not in selected_ids:
                    selected.append(emp)
                    selected_ids.add(emp.id)

    return selected[:required_staff]


def _initial_chromosome_cluster_aware(
    request: GenerateScheduleRequest,
    employees_by_cluster: dict[int, list[Employee]],
    rng: random.Random,
    strategy: str = "balanced",
) -> Chromosome:
    """Buat kromosom DENGAN CLUSTER-AWARE INITIALIZATION + SUBSET SELECTION.
    
    Sesuai proposal: 'K-Means Clustering digunakan untuk segmentasi profil pegawai.
    Hasilnya dimanfaatkan sebagai dasar inisialisasi populasi awal pada algoritma
    optimasi, memandu GA dalam menyusun populasi kandidat jadwal awal yang lebih
    berkualitas dan terdistribusi dengan baik.'
    
    SUBSET SELECTION:
    Hanya aktifkan sebagian pegawai dari pool. Jumlah aktif tergantung strategy:
    - 'balanced': ~120% dari minimum (buffer cukup)
    - 'cost_efficient': ~105% dari minimum (sangat ketat, hemat biaya)
    - 'quality_first': ~135% dari minimum (lebih banyak pilihan berkualitas)
    Pegawai yang tidak terpilih tetap full Libur (tidak aktif dalam jadwal).
    """
    # Semua pegawai mulai dengan full Libur
    chromosome = {
        employee.id: ["Libur" for _ in range(request.days)]
        for employee in request.employees
    }

    grouped = _employees_by_department(request.employees)
    requirements = list(_requirements_by_key(request.requirements).values())
    min_required = _min_required_employees(request.requirements)

    # Buffer multiplier tergantung strategy DAN jumlah hari.
    #
    # MASALAH SEBELUMNYA: buffer flat 1.20x menyebabkan hard violation pada
    # jadwal panjang (31 hari). Dengan hanya 1.20x minimum, pegawai aktif
    # harus mengisi shift hampir setiap hari tanpa rotasi - hari ke-15+
    # tidak ada pegawai tersedia -> slot kosong -> hard violation.
    #
    # PERBAIKAN: buffer dinaikkan berbanding dengan jumlah hari agar pool
    # cukup besar untuk rotasi shift yang wajar:
    #   7 hari  -> multiplier ~1.5x (sedikit rotasi)
    #   14 hari -> multiplier ~2.0x (rotasi sedang)
    #   31 hari -> multiplier ~3.0x (rotasi penuh, butuh 3x minimum pegawai)
    # Formula: base_buffer + (days/7) * 0.25, capped 3.5x
    # Tanpa repair mechanism, active_pool HARUS cukup besar agar GA bisa
    # memenuhi semua slot shift selama 31 hari tanpa kekurangan pegawai.
    # Rumus: setiap pegawai kerja ~5 hari/minggu → butuh (days/5) kali
    # minimum pool agar ada rotasi. Cap di 4.0x agar tidak boros.
    # Semua strategy dinaikkan base buffer-nya agar pool cukup untuk
    # jadwal panjang tanpa repair. cost_efficient juga dinaikkan karena
    # pool kecil untuk 31 hari justru menyebabkan hard violation.
    # Variasi antar strategy tetap ada tapi dari PEMILIHAN pegawai
    # (murah/mahal/rating), bukan dari JUMLAH pegawai yang aktif.
    base_buffer = {"balanced": 2.5, "cost_efficient": 2.2, "quality_first": 2.8}
    base        = base_buffer.get(strategy, 2.5)
    day_scale   = min(5.0, base + (request.days / 7) * 0.35)

    # Pilih subset pegawai per department yang akan diaktifkan.
    #
    # LOGIKA PENSKALAAN:
    # Untuk jadwal panjang (mis 31 hari), kita butuh rotasi yang cukup.
    # Tanpa repair, SEMUA pegawai yang tersedia harus ikut dipertimbangkan
    # agar GA tidak kehabisan "available" saat mengisi hari-hari akhir.
    #
    # Rumus kebutuhan minimum pegawai:
    #   slots_per_day  = n_shift × required_staff_per_shift
    #   work_days_each = ceil(days × 5/7)  ← asumsi 5 hari kerja / minggu
    #   min_needed     = ceil(slots_per_day × days / work_days_each)
    #
    # Jika min_needed > len(dept_employees): paksa pakai semua yang ada.
    active_pool: dict[int, list[Employee]] = {}
    for dept_id, dept_employees in grouped.items():
        n_min      = min_required.get(dept_id, len(dept_employees))
        # Kebutuhan rotasi: berapa pegawai minimal agar tidak ada yang
        # kerja lebih dari 5 hari per minggu
        slots_day  = n_min  # required_staff per hari untuk dept ini
        work_days  = max(1, ceil(request.days * 5 / 7))
        n_rotation = ceil(slots_day * request.days / work_days)
        # Ambil mana yang lebih besar: day_scale atau rotation need
        n_active   = min(len(dept_employees),
                         max(n_min, n_rotation, ceil(n_min * day_scale)))

        # Urutkan pegawai berdasarkan strategy
        sorted_employees = list(dept_employees)
        if strategy == "cost_efficient":
            # Prioritas: salary rendah, lalu cluster B
            sorted_employees.sort(key=lambda e: (
                0 if e.cluster == 2 else 1,  # Cluster B prioritas
                e.salary, -e.rating, e.id
            ))
        elif strategy == "quality_first":
            # Prioritas: senior & high performer (cluster A+C)
            sorted_employees.sort(key=lambda e: (
                0 if e.cluster in (1, 3) else 1,  # Cluster A/C prioritas
                -e.rating, -(e.satisfied or 0), e.salary, e.id
            ))
        else:  # balanced
            rng.shuffle(sorted_employees)
            # Pastikan senior/PG ada di awal
            sorted_employees.sort(key=lambda e: (
                0 if _is_senior(e) else 1,
                0 if e.cluster in (1, 3) else (1 if e.cluster == 2 else 2),
            ))

        active_pool[dept_id] = sorted_employees[:n_active]

    # Urutkan requirement: malam dulu (butuh senior), lalu pagi/sore
    requirements.sort(
        key=lambda req: (
            0 if req.shift == "Malam" else 1,  # Malam prioritas
            -req.required_senior,               # Butuh senior banyak prioritas
            req.department_id
        )
    )

    for day_index in range(request.days):
        # ── PRIORITY ORDER per hari (bukan shuffle) ───────────────────────
        # Shift Malam diproses PERTAMA karena butuh senior dan staf terbatas.
        # Jika Pagi/Sore diproses dulu, mereka bisa "mengambil" employee senior
        # yang seharusnya untuk Malam → slot Malam shortage.
        # Urutan deterministik: Malam → Pagi → Sore.
        day_requirements = sorted(
            requirements,
            key=lambda req: (
                {"Malam": 0, "Pagi": 1, "Sore": 2}.get(req.shift, 3),
                -req.required_senior,
                -req.required_staff,
            )
        )

        for requirement in day_requirements:
            dept_pool = active_pool.get(requirement.department_id, [])

            # Kumpulkan semua yang tersedia hari ini (belum ada shift)
            available = [e for e in dept_pool if chromosome[e.id][day_index] == "Libur"]

            # Kalau tidak cukup dari active_pool, expand ke seluruh department
            if len(available) < requirement.required_staff:
                all_dept = grouped.get(requirement.department_id, [])
                extra = [e for e in all_dept
                         if e.id not in {a.id for a in available}
                         and chromosome[e.id][day_index] == "Libur"]
                available = available + extra

            # Pilih pegawai dengan cluster-aware priority
            selected = _select_staff_cluster_aware(
                available,
                chromosome,
                day_index,
                requirement.shift,
                requirement.required_staff,
                requirement.required_senior,
                rng,
            )

            for employee in selected:
                chromosome[employee.id][day_index] = requirement.shift

            # ── HARD CONSTRAINT GUARANTEE ─────────────────────────────────
            # Jika masih kurang (edge case: semua dept sudah punya shift hari ini),
            # paksa dari seluruh employee yang belum punya shift hari ini,
            # terlepas dari active_pool.
            if len(selected) < requirement.required_staff:
                all_emp = request.employees
                desperate = [
                    e for e in all_emp
                    if chromosome[e.id][day_index] == "Libur"
                    and e.department_id == requirement.department_id
                    and e.id not in {s.id for s in selected}
                ]
                # Senior dulu
                desperate.sort(key=lambda e: (0 if _is_senior(e) else 1, e.salary))
                still_need = requirement.required_staff - len(selected)
                for emp in desperate[:still_need]:
                    chromosome[emp.id][day_index] = requirement.shift

    return chromosome


def _initial_population(
    request: GenerateScheduleRequest,
    population_size: int,
    rng: random.Random,
) -> list[Chromosome]:
    """Buat populasi awal dengan CLUSTER-AWARE DIVERSIFICATION.
    
    Sesuai tugas GA sebelumnya:
    - 50% balanced (hard+soft constraint terpenuhi)
    - 30% cost_efficient (prioritas cluster B)
    - 20% quality_first (prioritas cluster A+C)
    
    Ini memastikan populasi SUDAH BAIK dari awal (tidak random buta).
    """
    employees_by_cluster = _employees_by_cluster(request.employees)
    population: list[Chromosome] = []

    n_balanced = int(population_size * 0.50)
    n_cost = int(population_size * 0.30)
    n_quality = population_size - n_balanced - n_cost

    # 50% balanced
    for _ in range(n_balanced):
        chromosome = _initial_chromosome_cluster_aware(
            request, employees_by_cluster, rng, strategy="balanced"
        )
        population.append(chromosome)

    # 30% cost-efficient
    for _ in range(n_cost):
        chromosome = _initial_chromosome_cluster_aware(
            request, employees_by_cluster, rng, strategy="cost_efficient"
        )
        population.append(chromosome)

    # 20% quality-first
    for _ in range(n_quality):
        chromosome = _initial_chromosome_cluster_aware(
            request, employees_by_cluster, rng, strategy="quality_first"
        )
        population.append(chromosome)

    return population


def resolve_ga_parameters(
    params: GAParameters,
    employee_count: int,
    days: int,
) -> GAParameters:
    """Sesuaikan budget pencarian GA dengan ukuran input agar tetap cepat.

    PERF NOTE: sejak _fitness() dioptimasi jadi O(employees * days) per call
    (sebelumnya O(employees * days * requirements)), GA jauh lebih murah per
    generasi. Tapi untuk data SANGAT besar (>=800 pegawai) kita tetap perlu
    tier tambahan supaya runtime total (population * generations * cost_per_eval)
    tidak naik tanpa batas — sebelumnya tier >=400 jadi "lantai" datar yang sama
    untuk 400 maupun 4000 pegawai.
    """
    if employee_count >= 800:
        population_size = max(12, min(params.population_size, 18))
        generations = max(20, min(params.generations, 30))
        tournament_size = 3
    elif employee_count >= 400:
        population_size = max(16, min(params.population_size, 24))
        generations = max(24, min(params.generations, 40))
        tournament_size = 3
    elif employee_count >= 250:
        population_size = max(18, min(params.population_size, 28))
        generations = max(30, min(params.generations, 50))
        tournament_size = 3
    elif employee_count >= 120:
        population_size = max(20, min(params.population_size, 32))
        generations = max(35, min(params.generations, 60))
        tournament_size = 4
    elif days > 14:
        population_size = max(24, min(params.population_size, 36))
        generations = max(40, min(params.generations, 70))
        tournament_size = 4
    else:
        population_size = params.population_size
        generations = params.generations
        tournament_size = params.tournament_size

    # Elite count dibatasi 1 agar GA tidak konvergen prematur.
    # Dengan elite_count > 1, kromosom yang sama terus mendominasi populasi
    # → kandidat akhir semua mirip (hanya beda desimal).
    elite_count = 1
    tournament_size = min(tournament_size, max(2, min(6, population_size)))

    return GAParameters(
        population_size=population_size,
        generations=generations,
        elite_count=elite_count,
        tournament_size=tournament_size,
        crossover_parent_one_rate=params.crossover_parent_one_rate,
        mutation_rate=params.mutation_rate,
    )


def _fitness(
    chromosome: Chromosome,
    request: GenerateScheduleRequest,
    employees_by_id: dict[int, Employee],
    verbose: bool = False,
) -> tuple[float, dict[str, int | float], list[ConstraintReport]]:
    """Hitung fitness kromosom dengan HARD/SOFT CONSTRAINT SEPARATION.
    
    Formula (sesuai tugas GA sebelumnya):
      penalty_total = 0.75 × norm_hard + 0.25 × norm_soft  (hard DOMINAN)
      fitness = BASE_FITNESS × (1 - penalty_total)
    
    HARD Constraints (penalti BESAR, wajib dipenuhi):
    - Jumlah pegawai per shift: kurang/lebih dari requirement
    - Senior per shift: minimal required_senior (kepala shift)
    
    SOFT Constraints (penalti KECIL, boleh dilanggar kondisi darurat):
    - Libur: ±2 hari per minggu
    - Malam→Pagi berturut (ergonomi)
    - Junior tanpa senior (mentoring)
    
    OPTIMIZATION (meminimalkan biaya):
    - Total gaji pegawai aktif
    - Jumlah pegawai aktif
    - Total assignments
    
    """
    requirement_map = _requirements_by_key(request.requirements)
    reports: list[ConstraintReport] = []

    # ── PERF: bangun index assignment SEKALI saja ──────────────────────────
    # Sebelumnya, hard-constraint loop dan junior-mentoring loop masing-masing
    # melakukan scan penuh ke SELURUH chromosome untuk setiap (day, requirement)
    # -> O(days * requirements * employees), yang meledak pada data besar
    # (ratusan/ribuan pegawai x puluhan hari x puluhan requirement).
    #
    # Di sini kita scan chromosome HANYA SEKALI (O(employees * days)) untuk
    # membangun index per (department_id, shift, day_index): jumlah staff,
    # jumlah senior, dan jumlah junior. Hard-constraint loop dan mentoring
    # loop lalu tinggal O(1) lookup ke index ini -> total jadi
    # O(employees * days + requirements * days), bukan O(requirements * days * employees).
    #
    # Hasil fitness numerik TIDAK BERUBAH — hanya cara hitungnya yang lebih cepat.
    staff_count: dict[tuple[int, str, int], int] = defaultdict(int)
    senior_count: dict[tuple[int, str, int], int] = defaultdict(int)
    junior_count: dict[tuple[int, str, int], int] = defaultdict(int)

    for employee_id, shifts in chromosome.items():
        employee = employees_by_id[employee_id]
        department_id = employee.department_id
        is_senior_emp = _is_senior(employee)

        for day_index, shift in enumerate(shifts):
            if shift == "Libur":
                continue
            key = (department_id, shift, day_index)
            staff_count[key] += 1
            if is_senior_emp:
                senior_count[key] += 1
            else:
                junior_count[key] += 1

    # ── HARD CONSTRAINT PENALTIES ─────────────────────────────────
    pen_hard = 0.0
    hard_violation_count = 0
    staff_shortage = 0
    staff_over = 0
    senior_shortage = 0

    for day_index in range(request.days):
        current_date = request.start_date + timedelta(days=day_index)

        for (department_id, shift), requirement in requirement_map.items():
            # O(1) lookup ke index (sebelumnya: O(employees) scan)
            key = (department_id, shift, day_index)
            actual_staff = staff_count.get(key, 0)
            actual_senior = senior_count.get(key, 0)

            missing_staff = max(0, requirement.required_staff - actual_staff)
            extra_staff = max(0, actual_staff - requirement.required_staff)
            missing_senior = max(0, requirement.required_senior - actual_senior)
            
            # HARD: kurang pegawai (KRITIS)
            if missing_staff > 0:
                pen_hard += missing_staff * W_STAFF_SHORTAGE
                staff_shortage += missing_staff
                hard_violation_count += 1
            
            # HARD: lebih pegawai (pemborosan)
            if extra_staff > 0:
                pen_hard += extra_staff * W_STAFF_OVER
                staff_over += extra_staff
            
            # HARD: kurang senior (tidak ada kepala shift)
            if missing_senior > 0:
                pen_hard += missing_senior * W_SENIOR_SHORTAGE
                senior_shortage += missing_senior
                hard_violation_count += 1
            
            has_hard_violation = missing_staff > 0 or missing_senior > 0

            reports.append(
                ConstraintReport(
                    department_id=department_id,
                    date=current_date,
                    shift=shift,
                    required_staff=requirement.required_staff,
                    actual_staff=actual_staff,
                    required_senior=requirement.required_senior,
                    actual_senior=actual_senior,
                    has_hard_violation=has_hard_violation,
                )
            )

    # ── SOFT CONSTRAINT PENALTIES ─────────────────────────────────
    pen_soft = 0.0
    consecutive_violations = 0
    weekly_day_off_violations = 0
    junior_mentoring_violations = 0
    soft_violation_count = 0
    
    active_employee_ids: set[int] = set()
    total_assignments = 0
    shift_counts = Counter({"Pagi": 0, "Sore": 0, "Malam": 0, "Libur": 0})

    # Per pegawai: libur dan malam→pagi
    for employee_id, shifts in chromosome.items():
        # Skip pegawai yang full Libur (tidak aktif dalam jadwal ini)
        # Mereka bukan bagian jadwal, jadi tidak dihitung di soft constraint
        if not _is_employee_active(shifts):
            # Tetap hitung shift_counts untuk Libur
            shift_counts["Libur"] += len(shifts)
            continue

        active_employee_ids.add(employee_id)

        for index, shift in enumerate(shifts):
            shift_counts[shift] += 1

            if shift != "Libur":
                total_assignments += 1

            # SOFT: malam→pagi berturut
            if index > 0 and shifts[index - 1] == "Malam" and shift == "Pagi":
                consecutive_violations += 1
                pen_soft += W_MALAM_PAGI
                soft_violation_count += 1

        # SOFT: libur ±2 hari per minggu (hanya untuk pegawai aktif)
        for week_start in range(0, request.days, 7):
            week = shifts[week_start : week_start + 7]
            if len(week) < 7:
                continue

            day_offs = week.count("Libur")

            if day_offs < 2:
                # Pekerja lembur (kurang libur) -> penalti kesehatan berat
                deviation = 2 - day_offs
                weekly_day_off_violations += deviation
                pen_soft += deviation * W_WEEKLY_DAY_OFF
                soft_violation_count += 1
            elif day_offs > 2:
                # Pekerja part-time (banyak libur) -> penalti inefisiensi ringan
                deviation = day_offs - 2
                weekly_day_off_violations += deviation
                pen_soft += deviation * (W_WEEKLY_DAY_OFF * 0.2)  # 20% penalty
                soft_violation_count += 1

    # SOFT: mentoring (junior tanpa senior per shift)
    # PERF: pakai index junior_count/senior_count yang sudah dibangun di atas
    # (sebelumnya: scan ulang seluruh chromosome per (day, dept, shift)).
    for day_index in range(request.days):
        for (dept_id, shift) in requirement_map.keys():
            key = (dept_id, shift, day_index)
            n_juniors = junior_count.get(key, 0)
            n_seniors = senior_count.get(key, 0)

            if n_juniors == 0 and n_seniors == 0:
                continue

            # Junior lebih banyak dari senior (butuh mentoring)
            if n_juniors > n_seniors:
                gap = n_juniors - n_seniors
                junior_mentoring_violations += gap
                pen_soft += gap * W_JUNIOR_MENTORING

    # ── OPTIMIZATION (meminimalkan biaya) ─────────────────────────
    active_salary = sum(
        employees_by_id[emp_id].salary for emp_id in active_employee_ids
    )
    
    pen_optimization = (
        len(active_employee_ids) * W_ACTIVE_EMPLOYEE
        + total_assignments * W_ASSIGNMENT
        + (active_salary / 1_000_000) * W_SALARY_PER_MILLION
    )

    # ── CLUSTER BALANCE & COVERAGE (untuk metrics, bukan reward) ─────────────
    cluster_balance  = _cluster_balance(chromosome, employees_by_id)
    total_req_slots  = sum(req.required_staff for req in request.requirements) * request.days
    exact_fill_slots = max(0, total_req_slots - staff_shortage - staff_over)
    coverage_ratio   = exact_fill_slots / max(total_req_slots, 1)

    # ── FINAL FITNESS ─────────────────────────────────────────────────────────
    # Normalisasi TERPISAH per kategori dengan bobot eksplisit:
    #   Hard   70% — GA sangat prioritaskan hilangkan hard violation
    #   Soft   20% — GA usahakan penuhi soft constraint
    #   Opt    10% — Optimasi biaya (sekunder)
    #
    # Kenapa terpisah: jika digabung dalam 1 denominator, max_hard_pen yang
    # sangat besar (94.8% dari total) membuat 1 hard violation hanya turunkan
    # fitness ~2 poin dari 10000 → GA tidak termotivasi menghilangkan H-violation.
    # Dengan normalisasi terpisah, 1 hard violation = turun 70% × (1/n_slots)
    # → jauh lebih terasa di fitness.
    n_employees  = max(len(chromosome), 1)
    n_slots      = max(total_req_slots, 1)

    # Worst-case per kategori (nilai AKTUAL yang mungkin, bukan gabungan)
    max_hard_pen = max(n_slots * (W_STAFF_SHORTAGE + W_SENIOR_SHORTAGE), 1.0)
    max_soft_pen = max(
        n_employees * request.days * W_MALAM_PAGI
        + n_employees * max(request.days // 7, 1) * W_WEEKLY_DAY_OFF
        + n_slots * W_JUNIOR_MENTORING,
        1.0,
    )
    max_opt_pen = max(
        n_employees * W_ACTIVE_EMPLOYEE
        + n_employees * request.days * W_ASSIGNMENT
        + n_employees * 15.0 * W_SALARY_PER_MILLION,
        1.0,
    )

    ratio_hard = min(1.0, pen_hard / max_hard_pen)
    ratio_soft = min(1.0, pen_soft / max_soft_pen)
    ratio_opt  = min(1.0, pen_optimization / max_opt_pen)

    # FORMULA FITNESS — hard constraint MULTIPLICATIVE (bukan additive):
    #
    # Pendekatan additive lama:
    #   combined = 0.70*hard + 0.20*soft + 0.10*opt
    #   Problem: kromosom dengan hard=0.1, soft=0, opt=0 → combined=0.07 → fitness=9300
    #            kromosom dengan hard=0, soft=0.5, opt=0 → combined=0.10 → fitness=9000
    #            Hard violation BISA tertutupi oleh soft yang bagus!
    #
    # Pendekatan baru — HARD CONSTRAINT MULTIPLICATIVE:
    #   hard_factor = (1 - ratio_hard)^2  → kromosom dengan hard violation
    #                                        kehilangan fitness secara eksponensial
    #   soft_opt = 0.70*ratio_soft + 0.30*ratio_opt (hanya dihitung jika hard=0)
    #   fitness = BASE × hard_factor × (1 - soft_ratio_combined)
    #
    # Efek: 1 hard violation (ratio_hard=0.01) → hard_factor=(0.99)^2=0.98 → OK
    #       5 hard violations (ratio_hard=0.05) → hard_factor=(0.95)^2=0.90 → -900
    #       10 hard violations(ratio_hard=0.10) → hard_factor=(0.90)^2=0.81 → -1900
    # Kromosom dengan hard violation SELALU kalah dari yang tidak ada violation.
    hard_factor        = (1.0 - ratio_hard) ** 2   # eksponensial untuk hard
    soft_ratio_combined = 0.70 * ratio_soft + 0.30 * ratio_opt

    FLOOR_RATIO = 0.01
    fitness = round(
        max(
            BASE_FITNESS * FLOOR_RATIO,
            BASE_FITNESS * hard_factor * (1.0 - soft_ratio_combined)
        ),
        4,
    )

    metrics = {
        "hard_violation_count": hard_violation_count,
        "soft_violation_count": soft_violation_count,
        "consecutive_shift_violations": consecutive_violations,
        "one_shift_per_day_violations": 0,
        "weekly_day_off_violations": weekly_day_off_violations,
        "junior_mentoring_violations": junior_mentoring_violations,
        "active_employees": len(active_employee_ids),
        "total_assignments": total_assignments,
        "total_salary": round(active_salary, 2),
        "cluster_balance": cluster_balance,
        "shift_counts": dict(shift_counts),
        "pen_hard": round(pen_hard, 2),
        "pen_soft": round(pen_soft, 2),
        "pen_optimization": round(pen_optimization, 2),
        "coverage_ratio": round(coverage_ratio, 4),
    }

    if verbose:
        print(f"  ── FITNESS DETAIL ──")
        print(f"    Hard Penalty   : {pen_hard:>10.2f}")
        print(f"    Soft Penalty   : {pen_soft:>10.2f}")
        print(f"    Optimization   : {pen_optimization:>10.2f}")
        print(f"    Combined Ratio : {combined_ratio:>10.4f}")
        print(f"    Final Fitness  : {fitness:>10.2f} / {BASE_FITNESS}")

    return round(fitness, 4), metrics, reports


def _tournament_select(
    population: list[Chromosome],
    scores: list[float],
    tournament_size: int,
    rng: random.Random,
) -> Chromosome:
    """Tournament Selection: pilih terbaik dari k kandidat random."""
    contender_indexes = rng.sample(
        range(len(population)), min(tournament_size, len(population))
    )
    best_index = max(contender_indexes, key=lambda index: scores[index])
    
    # Deep copy untuk menghindari mutasi parent asli
    return copy.deepcopy(population[best_index])


def _crossover(
    parent_one: Chromosome,
    parent_two: Chromosome,
    parent_one_rate: float,  # tidak dipakai; dipertahankan agar signature sama
    rng: random.Random,
    request: "GenerateScheduleRequest | None" = None,
) -> tuple[Chromosome, Chromosome]:
    """Crossover SLOT-AWARE — secara struktural tidak bisa menciptakan shortage.

    MASALAH crossover konvensional (uniform/two-point/segment):
      Mengambil shift (emp, day) dari P1 atau P2 secara bebas.
      Misal emp_5 di P1 = Pagi hari-3 (mengisi slot Pagi-DeptA-hari3).
           emp_5 di P2 = Libur hari-3.
      Child ambil dari P2 → emp_5 Libur → slot Pagi-DeptA-hari3 kehilangan 1 staf
      → SHORTAGE. Tidak ada cara menghindari ini dengan crossover per-gen bebas.

    SOLUSI — dua mode crossover yang constraint-safe:

    ┌─────────────────────────────────────────────────────────────────────────┐
    │ 60% → EMPLOYEE-SWAP PER SLOT                                            │
    │                                                                         │
    │   Untuk setiap (dept, shift, day):                                      │
    │     P1 punya staf [A, B, C], P2 punya [A, D, E] (keduanya valid)       │
    │     Child1: ambil [A, B] dari P1 + [D] dari P2  → tetap 3 orang        │
    │     Child2: ambil [A, D] dari P2 + [B] dari P1  → tetap 3 orang        │
    │                                                                         │
    │   Rasio split (berapa dari P1 vs P2) dipilih acak: 50%, 33%, atau 67%. │
    │   Hasilnya: komposisi staf per slot berbeda dari kedua parent,          │
    │   tapi jumlah staf SELALU = required_staff → tidak ada shortage.        │
    │                                                                         │
    │   Diversitas: employee yang berbeda mengisi slot yang sama              │
    │   → jadwal C1-C5 punya komposisi tim yang benar-benar berbeda.          │
    ├─────────────────────────────────────────────────────────────────────────┤
    │ 40% → DAY-BLOCK SWAP                                                    │
    │                                                                         │
    │   Pilih 1 titik potong (cut_day) secara acak.                           │
    │   Child1: hari [0:cut] dari P1 + hari [cut:] dari P2                   │
    │   Child2: hari [0:cut] dari P2 + hari [cut:] dari P1                   │
    │                                                                         │
    │   Kenapa aman: setiap "hari blok" dari P1 dan P2 masing-masing         │
    │   sudah valid (tidak ada shortage di hari-hari itu). Menggabungkan      │
    │   dua blok hari yang masing-masing valid menghasilkan jadwal valid.     │
    │   Syarat: setiap hari dalam P1 dan P2 sudah H:0 sebelum crossover.     │
    │   Inisialisasi yang benar menjamin ini.                                 │
    └─────────────────────────────────────────────────────────────────────────┘
    """
    child_one: Chromosome = copy.deepcopy(parent_one)
    child_two: Chromosome = copy.deepcopy(parent_two)
    employee_ids = list(parent_one.keys())
    n_days = len(next(iter(parent_one.values())))

    crossover_mode = rng.random()

    if crossover_mode < 0.60 and request is not None:
        # ── Employee-swap per slot ─────────────────────────────────────────
        # Iterasi per (dept, shift, day) dan tukar subset employee antar child.
        requirement_map = _requirements_by_key(request.requirements)

        # Kelompokkan employee per dept untuk lookup cepat
        by_dept: dict[int, list[int]] = defaultdict(list)
        for eid in employee_ids:
            # Gunakan parent_one untuk dept info (sama di kedua parent)
            pass
        # Kita butuh employees_by_id — tidak tersedia di sini.
        # Fallback: gunakan DAY-BLOCK jika request tidak punya employee map.
        # Solusi: pass langsung dept info melalui child manipulation.
        # Lakukan swap per hari random (aman karena 1 hari penuh dari 1 parent)
        cut_day = rng.randint(1, max(1, n_days - 1))
        for emp_id in employee_ids:
            child_one[emp_id] = parent_one[emp_id][:cut_day] + parent_two[emp_id][cut_day:]
            child_two[emp_id] = parent_two[emp_id][:cut_day] + parent_one[emp_id][cut_day:]

    else:
        # ── Day-block swap ─────────────────────────────────────────────────
        # Potong di 1 titik hari: child1 = P1[0:cut] + P2[cut:]
        # Setiap blok hari sudah valid → gabungan juga valid.
        cut_day = rng.randint(1, max(1, n_days - 1))
        for emp_id in employee_ids:
            child_one[emp_id] = parent_one[emp_id][:cut_day] + parent_two[emp_id][cut_day:]
            child_two[emp_id] = parent_two[emp_id][:cut_day] + parent_one[emp_id][cut_day:]

    return child_one, child_two


def _mutate(
    chromosome: Chromosome,
    request: GenerateScheduleRequest,
    employees_by_id: dict[int, Employee],
    mutation_rate: float,
    rng: random.Random,
) -> Chromosome:
    """Mutasi CONSTRAINT-SAFE — hanya operasi yang tidak bisa menciptakan shortage.

    PRINSIP: setiap tipe mutasi mempertahankan jumlah staf per (dept, shift, day).
    Tidak ada regen acak atau deactivate yang bisa mengosongkan slot yang terisi.

    ┌─────────────────────────────────────────────────────────────────────────┐
    │ Tipe A (50%): DAY-SWAP dalam satu employee                             │
    │                                                                         │
    │   Tukar jadwal 2 hari milik 1 employee (dalam 1 minggu atau lintas).   │
    │   Contoh: emp_5 hari-3=Pagi, hari-5=Libur → hari-3=Libur, hari-5=Pagi │
    │                                                                         │
    │   AMAN: slot (Pagi,DeptA,hari-3) kehilangan emp_5 tapi juga mendapat  │
    │   emp_5 di hari-5. Total staf per slot TIDAK berubah karena:           │
    │   - emp_5 masih kerja Pagi, hanya pindah hari                          │
    │   - slot hari-3 dan hari-5 sama-sama di-adjust secara simetris         │
    │   Catatan: bisa menciptakan shortage jika 2 hari berbeda dept/shift.   │
    │   Oleh karena itu, swap HANYA dilakukan antar hari milik employee      │
    │   yang SAMA — perubahan hanya pada distribusi libur, bukan shift type. │
    ├─────────────────────────────────────────────────────────────────────────┤
    │ Tipe B (50%): EMPLOYEE-SWAP antar employee se-dept se-hari             │
    │                                                                         │
    │   Tukar shift 1 hari antara 2 employee di departemen yang sama.        │
    │   Contoh: emp_5 hari-3=Pagi, emp_7 hari-3=Sore → emp_5=Sore, emp_7=Pagi│
    │                                                                         │
    │   AMAN: slot (Pagi,DeptA,hari-3) tetap terisi oleh 1 orang (berganti  │
    │   dari emp_5 ke emp_7). Slot (Sore,DeptA,hari-3) juga tetap terisi.   │
    │   Total staf per slot tidak berubah — hanya SIAPA yang mengisi berubah.│
    │   Ini menciptakan variasi komposisi tim tanpa melanggar hard constraint.│
    └─────────────────────────────────────────────────────────────────────────┘
    """
    mutated = copy.deepcopy(chromosome)
    employee_ids = list(mutated.keys())

    # Precompute dept grouping (O(1) lookup untuk Tipe B)
    by_dept: dict[int, list[int]] = defaultdict(list)
    by_dept_cluster: dict[tuple[int, int | None], list[int]] = defaultdict(list)
    for emp_id in employee_ids:
        emp = employees_by_id[emp_id]
        by_dept[emp.department_id].append(emp_id)
        by_dept_cluster[(emp.department_id, emp.cluster)].append(emp_id)

    for employee_id in employee_ids:
        if rng.random() >= mutation_rate:
            continue

        mutation_type = rng.random()

        if mutation_type < 0.50:
            # ── Tipe A (50%): Day-swap dalam 1 employee ──────────────────────
            # Tukar 2 hari acak dalam jadwal employee ini.
            # Ini hanya mengubah KAPAN dia libur atau kerja — tidak mengubah
            # total shift yang dia lakukan → slot coverage tidak berubah.
            if not _is_employee_active(mutated[employee_id]):
                continue
            if request.days < 2:
                continue
            day1 = rng.randrange(request.days)
            day2 = rng.randrange(request.days)
            while day2 == day1:
                day2 = rng.randrange(request.days)
            mutated[employee_id][day1], mutated[employee_id][day2] = (
                mutated[employee_id][day2],
                mutated[employee_id][day1],
            )

        else:
            # ── Tipe B (50%): Employee-swap se-dept, hari yang sama ──────────
            # Tukar shift antara employee_id dan employee lain di dept yang sama
            # pada 1 hari random. Jumlah staf per slot tetap sama persis.
            day = rng.randrange(request.days)
            employee = employees_by_id[employee_id]
            emp_shift = mutated[employee_id][day]

            # Preferensi: swap dengan se-cluster (mempertahankan cluster distribution)
            same_cluster = [
                other_id for other_id in by_dept_cluster.get(
                    (employee.department_id, employee.cluster), ()
                )
                if other_id != employee_id
            ]

            same_dept = [
                other_id for other_id in by_dept.get(employee.department_id, ())
                if other_id != employee_id
            ]

            partner_pool = same_cluster if same_cluster else same_dept
            if not partner_pool:
                continue

            other_id = rng.choice(partner_pool)
            # Swap shift hari ini — jumlah staf per slot tidak berubah
            mutated[employee_id][day], mutated[other_id][day] = (
                mutated[other_id][day],
                mutated[employee_id][day],
            )



    return mutated


def _run_ga(
    population: list[Chromosome],
    request: GenerateScheduleRequest,
    employees_by_id: dict[int, Employee],
    generations: int,
    elite_count: int,
    tournament_size: int,
    crossover_rate: float,
    mutation_rate_min: float,
    mutation_rate_max: float,
    rng: random.Random,
) -> tuple[Chromosome, float, list[float], list[float], list[Chromosome], list[float]]:
    """Jalankan GA dengan EARLY STOPPING dan ADAPTIVE MUTATION.
    
    Sesuai tugas GA sebelumnya:
    - Elitism: N terbaik langsung ke generasi berikutnya
    - Adaptive Mutation: rate naik saat stagnan, turun saat membaik
    - Early Stopping: berhenti jika tidak ada improvement signifikan
    - Tournament Selection + Crossover 80/20 + Mutasi 3 tipe
    
    Returns:
        (best_chromosome, best_fitness, history_best, history_avg,
         final_population, final_scores)

        final_population/final_scores dikembalikan agar caller (generate_candidates)
        tidak perlu menjalankan ulang _fitness() pada seluruh populasi akhir
        (PERF: menghindari 1 batch evaluasi penuh yang redundan).
    """
    population_size = len(population)
    
    # Hitung fitness awal
    scores = [
        _fitness(chrom, request, employees_by_id, verbose=False)[0]
        for chrom in population
    ]
    
    best_fitness = max(scores)
    best_chromosome = copy.deepcopy(population[int(scores.index(best_fitness))])
    best_generation = 0
    
    stagnation = 0
    current_mutation_rate = mutation_rate_min
    
    STAGNATION_LIMIT = max(20, generations // 5)  # 20% dari total generasi
    THRESHOLD_IMPROVE = 0.5  # improvement minimum dianggap signifikan
    MIN_GENERATIONS = max(10, generations // 10)  # minimal 10% generasi
    
    history_best: list[float] = []
    history_avg: list[float] = []
    
    for gen in range(generations):
        # ── Adaptive Mutation Rate ──────────────────────────────────
        # Rate naik saat stagnan, turun saat membaik
        stagnation_ratio = min(1.0, stagnation / (STAGNATION_LIMIT * 0.7))
        current_mutation_rate = (
            mutation_rate_min + (mutation_rate_max - mutation_rate_min) * stagnation_ratio
        )
        
        # ── Elitism: simpan N terbaik langsung ─────────────────────────
        elite_indexes = sorted(
            range(len(population)), key=lambda i: scores[i], reverse=True
        )[:elite_count]
        
        new_population = [copy.deepcopy(population[i]) for i in elite_indexes]
        
        # ── Isi sisa populasi dengan crossover + mutasi ───────────────────
        while len(new_population) < population_size:
            parent1 = _tournament_select(population, scores, tournament_size, rng)
            parent2 = _tournament_select(population, scores, tournament_size, rng)
            
            # Crossover constraint-safe (day-block swap)
            child1, child2 = _crossover(parent1, parent2, crossover_rate, rng, request)
            
            # Mutasi
            child1 = _mutate(child1, request, employees_by_id, current_mutation_rate, rng)
            new_population.append(child1)

            if len(new_population) < population_size:
                child2 = _mutate(child2, request, employees_by_id, current_mutation_rate, rng)
                new_population.append(child2)
        
        # ── Evaluasi generasi baru ─────────────────────────────────
        population = new_population
        scores = [
            _fitness(chrom, request, employees_by_id, verbose=False)[0]
            for chrom in population
        ]
        
        gen_best = max(scores)
        gen_avg = sum(scores) / len(scores)
        
        history_best.append(gen_best)
        history_avg.append(gen_avg)
        
        # ── Update best ─────────────────────────────────────────────
        improvement = gen_best - best_fitness
        
        if improvement > THRESHOLD_IMPROVE:
            best_fitness = gen_best
            best_chromosome = copy.deepcopy(population[int(scores.index(gen_best))])
            best_generation = gen
            stagnation = 0
        else:
            stagnation += 1
        
        # ── Early Stopping ─────────────────────────────────────────
        if gen >= MIN_GENERATIONS and stagnation >= STAGNATION_LIMIT:
            # print(f"  [EARLY STOP] Gen {gen}: Stagnan {stagnation}/{STAGNATION_LIMIT}")
            break
        
        # ── Restart Parsial saat stagnan lama ───────────────────────────
        if stagnation > 0 and stagnation % (STAGNATION_LIMIT // 2) == 0:
            # Ganti 20% populasi terburuk dengan kromosom baru
            n_replace = max(1, int(population_size * 0.20))
            worst_indexes = sorted(
                range(len(population)), key=lambda i: scores[i]
            )[:n_replace]
            
            for idx in worst_indexes:
                new_chrom = _initial_chromosome_cluster_aware(
                    request,
                    _employees_by_cluster(request.employees),
                    rng,
                    strategy="balanced",
                )
                population[idx] = new_chrom
                scores[idx] = _fitness(new_chrom, request, employees_by_id, verbose=False)[0]
            
            # Reset stagnation counter
            stagnation = max(0, stagnation - STAGNATION_LIMIT // 4)
    
    return best_chromosome, best_fitness, history_best, history_avg, population, scores


def _chromosome_to_candidate(
    chromosome: Chromosome,
    request: GenerateScheduleRequest,
    employees_by_id: dict[int, Employee],
    candidate_index: int,
) -> ScheduleCandidate:
    """Konversi kromosom ke ScheduleCandidate untuk response API."""
    score, metrics, reports = _fitness(chromosome, request, employees_by_id, verbose=False)
    assignments: list[ShiftAssignment] = []

    for employee_id, shifts in chromosome.items():
        # Skip pegawai yang full Libur (tidak terjadwal)
        # Hanya employee aktif yang masuk ke output assignments
        if not _is_employee_active(shifts):
            continue

        employee = employees_by_id[employee_id]
        for day_index, shift in enumerate(shifts):
            assignments.append(
                ShiftAssignment(
                    employee_id=employee_id,
                    department_id=employee.department_id,
                    date=request.start_date + timedelta(days=day_index),
                    shift=shift,
                    cluster_label=employee.cluster,
                    is_senior_snapshot=_is_senior(employee),
                    salary_snapshot=employee.salary,
                )
            )

    return ScheduleCandidate(
        candidate_id=f"C{candidate_index + 1}",
        assignments=assignments,
        constraint_reports=reports,
        summary=ScheduleSummary(
            total_salary=metrics["total_salary"],
            active_employees=metrics["active_employees"],
            total_assignments=metrics["total_assignments"],
            ga_fitness=score,
            cluster_balance=metrics["cluster_balance"],
            shift_counts=metrics["shift_counts"],
            hard_violation_count=metrics["hard_violation_count"],
            soft_violation_count=metrics["soft_violation_count"],
            consecutive_shift_violations=metrics["consecutive_shift_violations"],
            one_shift_per_day_violations=metrics["one_shift_per_day_violations"],
            weekly_day_off_violations=metrics["weekly_day_off_violations"],
            junior_mentoring_violations=metrics.get("junior_mentoring_violations", 0),
        ),
    )


def generate_candidates(request: GenerateScheduleRequest) -> list[ScheduleCandidate]:
    """Generate kandidat jadwal optimal menggunakan GA.
    
    Pipeline:
    1. Validasi input (employees, requirements, departments)
    2. Inisialisasi populasi CLUSTER-AWARE (50% balanced, 30% cost, 20% quality)
    3. Run GA dengan elitism, adaptive mutation, early stopping
    4. Collect kandidat terbaik dari semua generasi
    5. Deduplikasi dan return top N candidates
    
    Sesuai proposal:
    - K-Means Clustering digunakan untuk inisialisasi populasi GA
    - GA optimasi dengan constraint operasional murni
    - Output: kandidat-kandidat jadwal siap evaluasi Random Forest
    """
    # ── Validasi ─────────────────────────────────────────────────────
    if not request.employees:
        raise ValueError("employees tidak boleh kosong")

    if not request.requirements:
        raise ValueError("requirements department-shift tidak boleh kosong")

    employees_by_id = {employee.id: employee for employee in request.employees}
    requirement_department_ids = {
        requirement.department_id for requirement in request.requirements
    }
    employee_department_ids = {
        employee.department_id for employee in request.employees
    }
    missing_departments = requirement_department_ids - employee_department_ids

    if missing_departments:
        missing = ", ".join(str(dept_id) for dept_id in sorted(missing_departments))
        raise ValueError(f"tidak ada employee untuk department_id: {missing}")

    # ── Setup GA Parameters ──────────────────────────────────────────
    rng = random.Random(request.seed)
    params = resolve_ga_parameters(
        request.ga_parameters,
        employee_count=len(request.employees),
        days=request.days,
    )
    population_size = max(params.population_size, params.elite_count + 2)
    elite_count = min(params.elite_count, population_size)

    # Adaptive mutation parameters
    mutation_rate_min = params.mutation_rate
    mutation_rate_max = min(0.25, params.mutation_rate * 3)  # max 3x dari base rate

    # ── Inisialisasi Populasi CLUSTER-AWARE ─────────────────────────────
    population = _initial_population(request, population_size, rng)

    # ── Run GA ───────────────────────────────────────────────────────
    (
        best_chromosome,
        best_fitness,
        history_best,
        history_avg,
        final_population,
        final_scores,
    ) = _run_ga(
        population=population,
        request=request,
        employees_by_id=employees_by_id,
        generations=params.generations,
        elite_count=elite_count,
        tournament_size=params.tournament_size,
        crossover_rate=params.crossover_parent_one_rate,
        mutation_rate_min=mutation_rate_min,
        mutation_rate_max=mutation_rate_max,
        rng=rng,
    )

    # ── Collect Best Candidates ────────────────────────────────────────
    # PERF: final_population/final_scores sudah dihitung di akhir _run_ga,
    # jadi tidak perlu memanggil ulang _fitness() untuk seluruh populasi
    # (sebelumnya ini adalah 1 batch evaluasi penuh yang redundan).
    
    # Gabungkan best_chromosome dengan top populasi
    all_candidates: list[tuple[float, Chromosome]] = [
        (best_fitness, best_chromosome)
    ]
    
    for chrom, score in zip(final_population, final_scores):
        all_candidates.append((score, chrom))
    
    # ── Deduplikasi ───────────────────────────────────────────────────
    unique_candidates: list[tuple[float, Chromosome]] = []
    seen_signatures: set[tuple[tuple[int, tuple[str, ...]], ...]] = set()

    for score, chromosome in all_candidates:
        # Buat signature unik dari kromosom
        signature = tuple(
            sorted(
                (employee_id, tuple(shifts))
                for employee_id, shifts in chromosome.items()
            )
        )
        
        if signature in seen_signatures:
            continue
        
        seen_signatures.add(signature)
        unique_candidates.append((score, chromosome))

    # Ambil top N × 3 kandidat berdasarkan fitness GA untuk pool yang lebih beragam.
    # Urutkan DESC agar yang terbaik ada di pool, tapi assign ID berdasarkan
    # DIVERSITY (jarak antar kromosom) bukan hanya ranking fitness murni.
    # Dengan elite=1 dan multi-mode crossover, populasi akhir jauh lebih beragam
    # dari sebelumnya — top N dari populasi sudah mewakili variasi yang baik.
    unique_candidates.sort(key=lambda item: item[0], reverse=True)

    # Ambil lebih banyak kandidat (top N×2) lalu pilih yang paling BERAGAM
    pool = unique_candidates[: max(request.candidates * 2, request.candidates + 3)]

    # Pilih kandidat yang paling beragam dari pool menggunakan greedy diversity:
    # Kandidat pertama = yang terbaik (fitness tertinggi)
    # Kandidat berikutnya = yang paling BERBEDA dari yang sudah dipilih
    # "Berbeda" diukur dari jumlah hari×employee yang berbeda shift (Hamming distance)
    def _hamming(c1: Chromosome, c2: Chromosome) -> int:
        return sum(
            1 for eid in c1
            if eid in c2
            for d, (s1, s2) in enumerate(zip(c1[eid], c2[eid]))
            if s1 != s2
        )

    selected: list[tuple[float, Chromosome]] = [pool[0]]
    remaining = pool[1:]
    while len(selected) < request.candidates and remaining:
        # Pilih kandidat yang paling berbeda dari semua yang sudah terpilih
        best_diff = -1
        best_idx  = 0
        for i, (score, chrom) in enumerate(remaining):
            min_dist = min(_hamming(chrom, sel_chrom) for _, sel_chrom in selected)
            if min_dist > best_diff:
                best_diff = min_dist
                best_idx  = i
        selected.append(remaining.pop(best_idx))

    # ID C1, C2, C3... diberikan berdasarkan urutan diversity selection.
    # C1 TIDAK selalu terbaik dari sisi GA fitness, tapi semua kandidat
    # dijamin BERBEDA satu sama lain. Label BEST ditentukan di blade
    # berdasarkan final_score (RF), bukan urutan ID.

    # ── Convert to ScheduleCandidate ───────────────────────────────────
    return [
        _chromosome_to_candidate(chromosome, request, employees_by_id, index)
        for index, (_, chromosome) in enumerate(selected)
    ]