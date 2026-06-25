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
    GenerateScheduleRequest,
    ScheduleCandidate,
    ScheduleSummary,
    ShiftAssignment,
)


WORKING_SHIFTS = ["Pagi", "Sore", "Malam"]
ALL_SHIFTS = ["Pagi", "Sore", "Malam", "Libur"]

# Fitness base
BASE_FITNESS = 10000.0  # Naikkan 10x untuk accommodate penalties

# HARD CONSTRAINTS (wajib dipenuhi, penalti BESAR)
W_STAFF_SHORTAGE = 100.0     # per orang kurang per shift (KRITIS)
W_STAFF_OVER = 8.0           # per orang lebih per shift (pemborosan)
W_SENIOR_SHORTAGE = 120.0    # per senior kurang (kepala shift wajib)

# SOFT CONSTRAINTS (diusahakan, penalti KECIL, boleh dilanggar kondisi darurat)
W_MALAM_PAGI = 1.5           # shift malam→pagi berturut (ergonomi) - turunkan lagi
W_WEEKLY_DAY_OFF = 5.0       # deviasi dari 2 hari libur/minggu (naikkan, penting!)
W_JUNIOR_MENTORING = 2.0     # junior tanpa senior (mentoring)

# OPTIMIZATION OBJECTIVES (meminimalkan biaya — bobot diseimbangkan)
W_ACTIVE_EMPLOYEE = 3.5      # per pegawai aktif (NAIKKAN untuk aggressive subset selection)
W_ASSIGNMENT = 0.3           # per assignment (kurangi total shift kerja)
W_SALARY_PER_MILLION = 1.5   # per juta rupiah total gaji (naikkan)

# REWARD (bonus untuk distribusi baik)
W_CLUSTER_BALANCE_REWARD = 800.0  # reward distribusi cluster merata (NAIKKAN significally)

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
        return []

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

    selected: list[Employee] = []

    # 1. Pilih senior untuk kepala shift (HARD CONSTRAINT)
    senior_pool = cluster_a if cluster_a else seniors
    senior_pool.sort(key=lambda e: (e.salary, -e.rating, e.id))
    
    for emp in senior_pool:
        if len([e for e in selected if _is_senior(e)]) >= required_senior:
            break
        if emp not in selected:
            selected.append(emp)

    # 2. Isi sisa dengan Cluster C (stabilizers)
    cluster_c.sort(key=lambda e: (-e.rating, -e.satisfied, e.salary, e.id))
    for emp in cluster_c:
        if len(selected) >= required_staff:
            break
        if emp not in selected:
            selected.append(emp)

    # 3. Isi dengan Cluster B (cost-efficient)
    cluster_b.sort(key=lambda e: (e.salary, -e.rating, e.id))
    for emp in cluster_b:
        if len(selected) >= required_staff:
            break
        if emp not in selected:
            selected.append(emp)

    # 4. Isi dengan no cluster atau senior lain
    remaining = [e for e in (no_cluster + seniors + juniors) if e not in selected]
    remaining.sort(key=lambda e: (e.salary, -e.rating, e.id))
    
    for emp in remaining:
        if len(selected) >= required_staff:
            break
        if emp not in selected:
            selected.append(emp)

    # 5. FALLBACK: Cluster D (watchlist) hanya jika SANGAT terpaksa
    # Hindari cluster D di shift malam atau shift berat
    if len(selected) < required_staff and cluster_d:
        cluster_d.sort(key=lambda e: (e.rating, e.satisfied, -e.salary, e.id))
        # Hanya ambil jika shift bukan malam ATAU sangat terpaksa
        if shift != "Malam" or len(selected) < required_staff * 0.7:
            for emp in cluster_d:
                if len(selected) >= required_staff:
                    break
                if emp not in selected:
                    selected.append(emp)

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

    # Buffer multiplier tergantung strategy
    buffer = {"balanced": 1.20, "cost_efficient": 1.05, "quality_first": 1.35}
    multiplier = buffer.get(strategy, 1.20)

    # Pilih subset pegawai per department yang akan diaktifkan
    active_pool: dict[int, list[Employee]] = {}
    for dept_id, dept_employees in grouped.items():
        n_min = min_required.get(dept_id, len(dept_employees))
        n_active = min(len(dept_employees), max(n_min, ceil(n_min * multiplier)))

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
        # Shuffle requirement dalam 1 hari (variasi populasi)
        day_requirements = requirements[:]
        rng.shuffle(day_requirements)

        for requirement in day_requirements:
            # Gunakan HANYA active_pool, bukan seluruh department
            department_pool = active_pool.get(requirement.department_id, [])

            # Gunakan cluster-aware selection
            selected = _select_staff_cluster_aware(
                department_pool,
                chromosome,
                day_index,
                requirement.shift,
                requirement.required_staff,
                requirement.required_senior,
                rng,
            )

            for employee in selected:
                chromosome[employee.id][day_index] = requirement.shift

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
    
    REWARD:
    - Cluster balance merata
    """
    requirement_map = _requirements_by_key(request.requirements)
    reports: list[ConstraintReport] = []
    
    # ── HARD CONSTRAINT PENALTIES ─────────────────────────────────
    pen_hard = 0.0
    hard_violation_count = 0
    staff_shortage = 0
    staff_over = 0
    senior_shortage = 0

    for day_index in range(request.days):
        current_date = request.start_date + timedelta(days=day_index)

        for (department_id, shift), requirement in requirement_map.items():
            # Hitung pegawai aktual di shift ini
            assigned = [
                employee_id for employee_id, shifts in chromosome.items()
                if shifts[day_index] == shift
                and employees_by_id[employee_id].department_id == department_id
            ]
            
            actual_staff = len(assigned)
            actual_senior = sum(
                1 for emp_id in assigned if _is_senior(employees_by_id[emp_id])
            )
            
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
    for day_index in range(request.days):
        for (dept_id, shift) in requirement_map.keys():
            assigned = [
                emp_id for emp_id, shifts in chromosome.items()
                if shifts[day_index] == shift
                and employees_by_id[emp_id].department_id == dept_id
            ]
            
            if not assigned:
                continue
            
            juniors = [e for e in assigned if not _is_senior(employees_by_id[e])]
            seniors = [e for e in assigned if _is_senior(employees_by_id[e])]
            
            # Junior lebih banyak dari senior (butuh mentoring)
            if len(juniors) > len(seniors):
                gap = len(juniors) - len(seniors)
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

    # ── REWARD ────────────────────────────────────────────────────
    cluster_balance = _cluster_balance(chromosome, employees_by_id)
    reward = cluster_balance * W_CLUSTER_BALANCE_REWARD

    # ── NORMALISASI & FINAL FITNESS ───────────────────────────────
    # Formula: BASE_FITNESS - (penalties) + reward
    # Tidak gunakan normalisasi proporsional karena bobot sudah diseimbangkan
    penalty_total = pen_hard + pen_soft + pen_optimization
    
    fitness = BASE_FITNESS - penalty_total + reward
    # Clamp ke range [0, BASE_FITNESS * 2] untuk akomodasi reward
    fitness = max(0.0, min(BASE_FITNESS * 2, fitness))

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
        "reward": round(reward, 2),
    }

    if verbose:
        print(f"  ── FITNESS DETAIL ──")
        print(f"    Hard Penalty   : {pen_hard:>10.2f}")
        print(f"    Soft Penalty   : {pen_soft:>10.2f}")
        print(f"    Optimization   : {pen_optimization:>10.2f}")
        print(f"    Cluster Reward : {reward:>10.2f}")
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
    parent_one_rate: float,
    rng: random.Random,
) -> tuple[Chromosome, Chromosome]:
    """Uniform Crossover 80/20 (sesuai tugas GA sebelumnya).
    
    - 80% gen dari parent 1, 20% dari parent 2 → child 1
    - 20% gen dari parent 1, 80% dari parent 2 → child 2
    - Setiap gen (employee × day) dipilih INDEPENDEN
    """
    child_one: Chromosome = {}
    child_two: Chromosome = {}

    for employee_id in parent_one:
        shifts_one = []
        shifts_two = []
        
        for shift_one, shift_two in zip(parent_one[employee_id], parent_two[employee_id]):
            if rng.random() < parent_one_rate:  # 80% dari P1
                shifts_one.append(shift_one)
                shifts_two.append(shift_two)
            else:  # 20% dari P2
                shifts_one.append(shift_two)
                shifts_two.append(shift_one)

        child_one[employee_id] = shifts_one
        child_two[employee_id] = shifts_two

    return child_one, child_two


def _mutate(
    chromosome: Chromosome,
    request: GenerateScheduleRequest,
    employees_by_id: dict[int, Employee],
    mutation_rate: float,
    rng: random.Random,
) -> Chromosome:
    """Mutasi dengan 4 jenis operasi.
    
    Tipe A (35%): Swap 2 hari dalam 1 minggu untuk 1 pegawai
    Tipe B (30%): Swap 2 pegawai SE-CLUSTER di hari yang sama (cluster-aware)
    Tipe C (20%): Re-generate 1 minggu dengan tetap penuhi hard constraint
    Tipe D (15%): Deactivate/Activate pegawai (subset selection)
    """
    mutated = copy.deepcopy(chromosome)
    employee_ids = list(mutated.keys())

    for employee_id in employee_ids:
        if rng.random() >= mutation_rate:
            continue

        mutation_type = rng.random()
        
        # Tipe A (35%): Swap 2 hari dalam 1 minggu
        if mutation_type < 0.35:
            # Skip pegawai yang full Libur (tidak ada yang bisa di-swap)
            if not _is_employee_active(mutated[employee_id]):
                continue

            week = rng.randrange(0, (request.days + 6) // 7)
            week_start = week * 7
            week_end = min(week_start + 7, request.days)
            
            if week_end - week_start >= 2:
                day1 = rng.randrange(week_start, week_end)
                day2 = rng.randrange(week_start, week_end)
                if day1 != day2:
                    mutated[employee_id][day1], mutated[employee_id][day2] = (
                        mutated[employee_id][day2],
                        mutated[employee_id][day1],
                    )
        
        # Tipe B (30%): Swap 2 pegawai SE-CLUSTER (cluster-aware mutation)
        elif mutation_type < 0.65:
            day = rng.randrange(request.days)
            employee = employees_by_id[employee_id]
            
            # Cari pegawai lain di cluster yang sama dan department yang sama
            same_cluster = [
                other_id for other_id in employee_ids
                if other_id != employee_id
                and employees_by_id[other_id].cluster == employee.cluster
                and employees_by_id[other_id].department_id == employee.department_id
                and mutated[other_id][day] != "Libur"  # hanya swap shift kerja
            ]
            
            if same_cluster:
                other_id = rng.choice(same_cluster)
                mutated[employee_id][day], mutated[other_id][day] = (
                    mutated[other_id][day],
                    mutated[employee_id][day],
                )
            else:
                # Fallback: swap dengan pegawai random di department sama
                same_dept = [
                    other_id for other_id in employee_ids
                    if other_id != employee_id
                    and employees_by_id[other_id].department_id == employee.department_id
                ]
                if same_dept:
                    other_id = rng.choice(same_dept)
                    mutated[employee_id][day], mutated[other_id][day] = (
                        mutated[other_id][day],
                        mutated[employee_id][day],
                    )
        
        # Tipe C (20%): Re-generate 1 minggu
        elif mutation_type < 0.85:
            # Skip pegawai yang full Libur
            if not _is_employee_active(mutated[employee_id]):
                continue

            week = rng.randrange(0, (request.days + 6) // 7)
            week_start = week * 7
            week_end = min(week_start + 7, request.days)
            
            # Tentukan jumlah libur baru (2 hari ideal, tapi bisa bervariasi)
            target_offs = 2 if rng.random() < 0.8 else rng.choice([1, 3])
            
            # Re-generate minggu ini
            week_days = list(range(week_start, week_end))
            rng.shuffle(week_days)
            
            # Set libur
            for i, day in enumerate(week_days):
                if i < target_offs:
                    mutated[employee_id][day] = "Libur"
                else:
                    # Random shift kerja (hindari malam→pagi)
                    if day > 0 and mutated[employee_id][day - 1] == "Malam":
                        mutated[employee_id][day] = rng.choice(["Sore", "Malam", "Libur"])
                    else:
                        mutated[employee_id][day] = rng.choice(WORKING_SHIFTS)

        # Tipe D (15%): Deactivate/Activate pegawai (SUBSET SELECTION)
        else:
            is_active = _is_employee_active(mutated[employee_id])

            if is_active:
                # DEACTIVATE: set semua hari ke Libur (keluarkan dari jadwal)
                mutated[employee_id] = ["Libur"] * request.days
            else:
                # ACTIVATE: beri shift kerja random (2 libur/minggu)
                employee = employees_by_id[employee_id]
                for week_start in range(0, request.days, 7):
                    week_end = min(week_start + 7, request.days)
                    week_len = week_end - week_start
                    n_offs = min(2, week_len)
                    off_days = set(rng.sample(range(week_start, week_end), n_offs))
                    for d in range(week_start, week_end):
                        if d in off_days:
                            mutated[employee_id][d] = "Libur"
                        else:
                            # Hindari malam→pagi
                            if d > 0 and mutated[employee_id][d - 1] == "Malam":
                                mutated[employee_id][d] = rng.choice(["Sore", "Malam"])
                            else:
                                mutated[employee_id][d] = rng.choice(WORKING_SHIFTS)

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
) -> tuple[Chromosome, float, list[float], list[float]]:
    """Jalankan GA dengan EARLY STOPPING dan ADAPTIVE MUTATION.
    
    Sesuai tugas GA sebelumnya:
    - Elitism: N terbaik langsung ke generasi berikutnya
    - Adaptive Mutation: rate naik saat stagnan, turun saat membaik
    - Early Stopping: berhenti jika tidak ada improvement signifikan
    - Tournament Selection + Crossover 80/20 + Mutasi 3 tipe
    
    Returns:
        (best_chromosome, best_fitness, history_best, history_avg)
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
            
            # Crossover 80/20 (SEMUA pasangan di-crossover)
            child1, child2 = _crossover(parent1, parent2, crossover_rate, rng)
            
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
    
    return best_chromosome, best_fitness, history_best, history_avg


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
    params = request.ga_parameters
    population_size = max(params.population_size, params.elite_count + 2)
    elite_count = min(params.elite_count, population_size)
    
    # Adaptive mutation parameters
    mutation_rate_min = params.mutation_rate
    mutation_rate_max = min(0.30, params.mutation_rate * 3)  # max 3x dari base rate

    # ── Inisialisasi Populasi CLUSTER-AWARE ─────────────────────────────
    population = _initial_population(request, population_size, rng)

    # ── Run GA ───────────────────────────────────────────────────────
    best_chromosome, best_fitness, history_best, history_avg = _run_ga(
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
    # Ambil kandidat terbaik dari populasi akhir
    final_scores = [
        _fitness(chrom, request, employees_by_id, verbose=False)[0]
        for chrom in population
    ]
    
    # Gabungkan best_chromosome dengan top populasi
    all_candidates: list[tuple[float, Chromosome]] = [
        (best_fitness, best_chromosome)
    ]
    
    for chrom, score in zip(population, final_scores):
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

    # Sort by fitness (descending) dan ambil top N
    unique_candidates.sort(key=lambda item: item[0], reverse=True)
    top_candidates = unique_candidates[: request.candidates]

    # ── Convert to ScheduleCandidate ───────────────────────────────────
    return [
        _chromosome_to_candidate(chromosome, request, employees_by_id, index)
        for index, (_, chromosome) in enumerate(top_candidates)
    ]
