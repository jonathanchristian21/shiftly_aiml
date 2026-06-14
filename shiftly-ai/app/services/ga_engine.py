"""Genetic Algorithm untuk generate kandidat jadwal Shiftly.

Input berasal dari web: pool employee, requirement per department/shift,
range tanggal, dan parameter GA. Fitness memakai sistem penalty dengan skor
lebih tinggi lebih baik, mengikuti format tugas GA sebelumnya.
"""

from __future__ import annotations

from collections import Counter, defaultdict
from datetime import timedelta
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

# Higher fitness is better. Penalties reduce the score from BASE_FITNESS.
BASE_FITNESS = 1000.0
W_STAFF_SHORTAGE = 60.0
W_STAFF_OVER = 8.0
W_SENIOR_SHORTAGE = 40.0
W_MALAM_PAGI = 5.0
W_WEEKLY_DAY_OFF = 1.5
W_ACTIVE_EMPLOYEE = 1.2
W_ASSIGNMENT = 0.15
W_SALARY_PER_MILLION = 0.35
W_CLUSTER_BALANCE_REWARD = 35.0


Chromosome = dict[int, list[str]]
RequirementKey = tuple[int, str]


def _is_senior(employee: Employee) -> bool:
    if employee.is_senior is not None:
        return employee.is_senior

    return (employee.education or "").strip().lower() == "pg"


def _requirements_by_key(
    requirements: list[DepartmentShiftRequirement],
) -> dict[RequirementKey, DepartmentShiftRequirement]:
    return {
        (requirement.department_id, requirement.shift): requirement
        for requirement in requirements
        if requirement.required_staff > 0 or requirement.required_senior > 0
    }


def _employees_by_department(employees: list[Employee]) -> dict[int, list[Employee]]:
    grouped: dict[int, list[Employee]] = defaultdict(list)
    for employee in employees:
        grouped[employee.department_id].append(employee)

    return grouped


def _cluster_balance(chromosome: Chromosome, employees_by_id: dict[int, Employee]) -> float:
    cluster_counts = Counter()

    for employee_id, shifts in chromosome.items():
        employee = employees_by_id[employee_id]
        if employee.cluster is None:
            continue

        cluster_counts[employee.cluster] += sum(1 for shift in shifts if shift != "Libur")

    if len(cluster_counts) <= 1:
        return 1.0

    counts = list(cluster_counts.values())
    average = sum(counts) / len(counts)
    if average == 0:
        return 1.0

    spread = max(counts) - min(counts)
    return round(max(0.0, 1.0 - (spread / (average + 1))), 4)


def _select_staff(
    employees: list[Employee],
    chromosome: Chromosome,
    day_index: int,
    shift: str,
    required_staff: int,
    required_senior: int,
    rng: random.Random,
) -> list[Employee]:
    available = [
        employee for employee in employees
        if chromosome[employee.id][day_index] == "Libur"
        and not (
            day_index > 0
            and chromosome[employee.id][day_index - 1] == "Malam"
            and shift == "Pagi"
        )
    ]

    if len(available) < required_staff:
        available = [
            employee for employee in employees
            if chromosome[employee.id][day_index] == "Libur"
        ]

    seniors = [employee for employee in available if _is_senior(employee)]
    juniors = [employee for employee in available if not _is_senior(employee)]

    seniors.sort(key=lambda employee: (employee.salary, -employee.rating, employee.id))
    juniors.sort(key=lambda employee: (employee.salary, -employee.rating, employee.id))

    selected: list[Employee] = []
    senior_window = seniors[: max(required_senior * 3, required_senior)]
    rng.shuffle(senior_window)

    for employee in senior_window:
        if len([item for item in selected if _is_senior(item)]) >= required_senior:
            break
        selected.append(employee)

    remaining = [
        employee for employee in seniors + juniors
        if employee.id not in {item.id for item in selected}
    ]
    top_window = remaining[: max(required_staff * 3, required_staff)]
    rng.shuffle(top_window)

    for employee in top_window:
        if len(selected) >= required_staff:
            break
        selected.append(employee)

    return selected[:required_staff]


def _initial_chromosome(request: GenerateScheduleRequest, rng: random.Random) -> Chromosome:
    chromosome = {
        employee.id: ["Libur" for _ in range(request.days)]
        for employee in request.employees
    }

    grouped = _employees_by_department(request.employees)
    requirements = list(_requirements_by_key(request.requirements).values())
    requirements.sort(key=lambda item: (item.shift != "Malam", item.department_id, item.shift))

    for day_index in range(request.days):
        day_requirements = requirements[:]
        rng.shuffle(day_requirements)

        for requirement in day_requirements:
            department_pool = grouped.get(requirement.department_id, [])
            selected = _select_staff(
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


def _fitness(
    chromosome: Chromosome,
    request: GenerateScheduleRequest,
    employees_by_id: dict[int, Employee],
) -> tuple[float, dict[str, int], list[ConstraintReport]]:
    requirement_map = _requirements_by_key(request.requirements)
    reports: list[ConstraintReport] = []
    hard_violation_count = 0
    staff_shortage = 0
    staff_over = 0
    senior_shortage = 0

    for day_index in range(request.days):
        current_date = request.start_date + timedelta(days=day_index)

        for (department_id, shift), requirement in requirement_map.items():
            assigned = [
                employee_id for employee_id, shifts in chromosome.items()
                if shifts[day_index] == shift
                and employees_by_id[employee_id].department_id == department_id
            ]
            actual_staff = len(assigned)
            actual_senior = sum(1 for employee_id in assigned if _is_senior(employees_by_id[employee_id]))
            missing_staff = max(0, requirement.required_staff - actual_staff)
            extra_staff = max(0, actual_staff - requirement.required_staff)
            missing_senior = max(0, requirement.required_senior - actual_senior)
            has_hard_violation = missing_staff > 0 or missing_senior > 0

            if has_hard_violation:
                hard_violation_count += int(missing_staff > 0) + int(missing_senior > 0)

            staff_shortage += missing_staff
            staff_over += extra_staff
            senior_shortage += missing_senior

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

    consecutive_violations = 0
    weekly_day_off_violations = 0
    active_employee_ids: set[int] = set()
    total_assignments = 0
    shift_counts = Counter({"Pagi": 0, "Sore": 0, "Malam": 0, "Libur": 0})

    for employee_id, shifts in chromosome.items():
        worked = False
        for index, shift in enumerate(shifts):
            shift_counts[shift] += 1
            if shift != "Libur":
                worked = True
                total_assignments += 1

            if index > 0 and shifts[index - 1] == "Malam" and shift == "Pagi":
                consecutive_violations += 1

        for start in range(0, request.days, 7):
            week = shifts[start:start + 7]
            if len(week) < 7:
                continue
            weekly_day_off_violations += abs(week.count("Libur") - 2)

        if worked:
            active_employee_ids.add(employee_id)

    active_salary = sum(employees_by_id[employee_id].salary for employee_id in active_employee_ids)
    cluster_balance = _cluster_balance(chromosome, employees_by_id)
    soft_violation_count = consecutive_violations + weekly_day_off_violations

    penalty = (
        staff_shortage * W_STAFF_SHORTAGE
        + staff_over * W_STAFF_OVER
        + senior_shortage * W_SENIOR_SHORTAGE
        + consecutive_violations * W_MALAM_PAGI
        + weekly_day_off_violations * W_WEEKLY_DAY_OFF
        + len(active_employee_ids) * W_ACTIVE_EMPLOYEE
        + total_assignments * W_ASSIGNMENT
        + (active_salary / 1_000_000) * W_SALARY_PER_MILLION
        - cluster_balance * W_CLUSTER_BALANCE_REWARD
    )
    fitness = max(0.0, min(BASE_FITNESS, BASE_FITNESS - penalty))

    metrics = {
        "hard_violation_count": hard_violation_count,
        "soft_violation_count": soft_violation_count,
        "consecutive_shift_violations": consecutive_violations,
        "one_shift_per_day_violations": 0,
        "weekly_day_off_violations": weekly_day_off_violations,
        "active_employees": len(active_employee_ids),
        "total_assignments": total_assignments,
        "total_salary": round(active_salary, 2),
        "cluster_balance": cluster_balance,
        "shift_counts": dict(shift_counts),
    }

    return round(fitness, 4), metrics, reports


def _tournament_select(
    population: list[Chromosome],
    scores: list[float],
    tournament_size: int,
    rng: random.Random,
) -> Chromosome:
    contender_indexes = rng.sample(range(len(population)), min(tournament_size, len(population)))
    best_index = max(contender_indexes, key=lambda index: scores[index])
    return {
        employee_id: shifts[:]
        for employee_id, shifts in population[best_index].items()
    }


def _crossover(
    parent_one: Chromosome,
    parent_two: Chromosome,
    parent_one_rate: float,
    rng: random.Random,
) -> tuple[Chromosome, Chromosome]:
    child_one: Chromosome = {}
    child_two: Chromosome = {}

    for employee_id in parent_one:
        shifts_one = []
        shifts_two = []
        for shift_one, shift_two in zip(parent_one[employee_id], parent_two[employee_id]):
            if rng.random() < parent_one_rate:
                shifts_one.append(shift_one)
                shifts_two.append(shift_two)
            else:
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
    mutated = {
        employee_id: shifts[:]
        for employee_id, shifts in chromosome.items()
    }
    employee_ids = list(mutated.keys())

    for employee_id in employee_ids:
        if rng.random() >= mutation_rate:
            continue

        day_index = rng.randrange(request.days)
        current_shift = mutated[employee_id][day_index]

        if rng.random() < 0.55:
            choices = [shift for shift in ALL_SHIFTS if shift != current_shift]
            mutated[employee_id][day_index] = rng.choice(choices)
        else:
            same_department_ids = [
                other_id for other_id in employee_ids
                if other_id != employee_id
                and employees_by_id[other_id].department_id == employees_by_id[employee_id].department_id
            ]
            if same_department_ids:
                other_id = rng.choice(same_department_ids)
                mutated[employee_id][day_index], mutated[other_id][day_index] = (
                    mutated[other_id][day_index],
                    mutated[employee_id][day_index],
                )

    return mutated


def _chromosome_to_candidate(
    chromosome: Chromosome,
    request: GenerateScheduleRequest,
    employees_by_id: dict[int, Employee],
    candidate_index: int,
) -> ScheduleCandidate:
    score, metrics, reports = _fitness(chromosome, request, employees_by_id)
    assignments: list[ShiftAssignment] = []

    for employee_id, shifts in chromosome.items():
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
        ),
    )


def generate_candidates(request: GenerateScheduleRequest) -> list[ScheduleCandidate]:
    if not request.employees:
        raise ValueError("employees tidak boleh kosong")

    if not request.requirements:
        raise ValueError("requirements department-shift tidak boleh kosong")

    employees_by_id = {employee.id: employee for employee in request.employees}
    requirement_department_ids = {requirement.department_id for requirement in request.requirements}
    employee_department_ids = {employee.department_id for employee in request.employees}
    missing_departments = requirement_department_ids - employee_department_ids

    if missing_departments:
        missing = ", ".join(str(department_id) for department_id in sorted(missing_departments))
        raise ValueError(f"tidak ada employee untuk department_id: {missing}")

    rng = random.Random(request.seed)
    parameters = request.ga_parameters
    population_size = max(parameters.population_size, parameters.elite_count + 2)
    elite_count = min(parameters.elite_count, population_size)

    population = [
        _initial_chromosome(request, rng)
        for _ in range(population_size)
    ]
    scores = [
        _fitness(chromosome, request, employees_by_id)[0]
        for chromosome in population
    ]

    best_chromosomes: list[Chromosome] = []

    for _ in range(parameters.generations):
        ranked_indexes = sorted(range(len(population)), key=lambda index: scores[index], reverse=True)
        best_chromosomes.extend(
            {
                employee_id: shifts[:]
                for employee_id, shifts in population[index].items()
            }
            for index in ranked_indexes[:request.candidates]
        )

        new_population = [
            {
                employee_id: shifts[:]
                for employee_id, shifts in population[index].items()
            }
            for index in ranked_indexes[:elite_count]
        ]

        while len(new_population) < population_size:
            parent_one = _tournament_select(population, scores, parameters.tournament_size, rng)
            parent_two = _tournament_select(population, scores, parameters.tournament_size, rng)
            child_one, child_two = _crossover(
                parent_one,
                parent_two,
                parameters.crossover_parent_one_rate,
                rng,
            )
            new_population.append(
                _mutate(child_one, request, employees_by_id, parameters.mutation_rate, rng)
            )
            if len(new_population) < population_size:
                new_population.append(
                    _mutate(child_two, request, employees_by_id, parameters.mutation_rate, rng)
                )

        population = new_population
        scores = [
            _fitness(chromosome, request, employees_by_id)[0]
            for chromosome in population
        ]

    best_chromosomes.extend(population)
    unique_candidates: list[tuple[float, Chromosome]] = []
    seen_signatures: set[tuple[tuple[int, tuple[str, ...]], ...]] = set()

    for chromosome in best_chromosomes:
        signature = tuple(
            sorted((employee_id, tuple(shifts)) for employee_id, shifts in chromosome.items())
        )
        if signature in seen_signatures:
            continue
        seen_signatures.add(signature)
        unique_candidates.append((_fitness(chromosome, request, employees_by_id)[0], chromosome))

    unique_candidates.sort(key=lambda item: item[0], reverse=True)

    return [
        _chromosome_to_candidate(chromosome, request, employees_by_id, index)
        for index, (_, chromosome) in enumerate(unique_candidates[:request.candidates])
    ]
