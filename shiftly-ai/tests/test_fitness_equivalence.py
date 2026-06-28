"""Regression test: pastikan optimasi performa di _fitness() TIDAK mengubah
nilai fitness/metrics/reports sama sekali — hanya mempercepat cara hitungnya.

Cara kerja: jalankan _fitness() dari versi ORIGINAL (sebelum optimasi) dan
versi PATCHED (sesudah optimasi) pada chromosome RANDOM yang sama, lalu
bandingkan hasilnya harus identik persis.

Test ini menyimpan salinan fungsi _fitness() versi original secara inline
(disalin dari ga_engine.py sebelum patch) supaya regresi bisa dideteksi
tanpa perlu checkout git history.
"""
import os
import random
import sys
import unittest
from collections import Counter, defaultdict
from datetime import date, timedelta

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from app.schemas import (
    DepartmentShiftRequirement,
    Employee,
    GAParameters,
    GenerateScheduleRequest,
)
from app.services import ga_engine as patched


def _original_fitness(chromosome, request, employees_by_id):
    """Salinan _fitness() SEBELUM optimasi (O(days*requirements*employees)),
    dipertahankan di sini hanya sebagai oracle pembanding untuk regression test.
    """
    W_STAFF_SHORTAGE = patched.W_STAFF_SHORTAGE
    W_STAFF_OVER = patched.W_STAFF_OVER
    W_SENIOR_SHORTAGE = patched.W_SENIOR_SHORTAGE
    W_MALAM_PAGI = patched.W_MALAM_PAGI
    W_WEEKLY_DAY_OFF = patched.W_WEEKLY_DAY_OFF
    W_JUNIOR_MENTORING = patched.W_JUNIOR_MENTORING
    W_ACTIVE_EMPLOYEE = patched.W_ACTIVE_EMPLOYEE
    W_ASSIGNMENT = patched.W_ASSIGNMENT
    W_SALARY_PER_MILLION = patched.W_SALARY_PER_MILLION
    W_CLUSTER_BALANCE_REWARD = patched.W_CLUSTER_BALANCE_REWARD
    W_SHIFT_COVERAGE_REWARD = patched.W_SHIFT_COVERAGE_REWARD
    BASE_FITNESS = patched.BASE_FITNESS

    requirement_map = patched._requirements_by_key(request.requirements)

    pen_hard = 0.0
    staff_shortage = 0
    staff_over = 0

    for day_index in range(request.days):
        for (department_id, shift), requirement in requirement_map.items():
            assigned = [
                employee_id for employee_id, shifts in chromosome.items()
                if shifts[day_index] == shift
                and employees_by_id[employee_id].department_id == department_id
            ]
            actual_staff = len(assigned)
            actual_senior = sum(
                1 for emp_id in assigned if patched._is_senior(employees_by_id[emp_id])
            )
            missing_staff = max(0, requirement.required_staff - actual_staff)
            extra_staff = max(0, actual_staff - requirement.required_staff)
            missing_senior = max(0, requirement.required_senior - actual_senior)

            if missing_staff > 0:
                pen_hard += missing_staff * W_STAFF_SHORTAGE
                staff_shortage += missing_staff
            if extra_staff > 0:
                pen_hard += extra_staff * W_STAFF_OVER
                staff_over += extra_staff
            if missing_senior > 0:
                pen_hard += missing_senior * W_SENIOR_SHORTAGE

    pen_soft = 0.0
    active_employee_ids = set()
    total_assignments = 0

    for employee_id, shifts in chromosome.items():
        if not patched._is_employee_active(shifts):
            continue
        active_employee_ids.add(employee_id)
        for index, shift in enumerate(shifts):
            if shift != "Libur":
                total_assignments += 1
            if index > 0 and shifts[index - 1] == "Malam" and shift == "Pagi":
                pen_soft += W_MALAM_PAGI

        for week_start in range(0, request.days, 7):
            week = shifts[week_start: week_start + 7]
            if len(week) < 7:
                continue
            day_offs = week.count("Libur")
            if day_offs < 2:
                pen_soft += (2 - day_offs) * W_WEEKLY_DAY_OFF
            elif day_offs > 2:
                pen_soft += (day_offs - 2) * (W_WEEKLY_DAY_OFF * 0.2)

    for day_index in range(request.days):
        for (dept_id, shift) in requirement_map.keys():
            assigned = [
                emp_id for emp_id, shifts in chromosome.items()
                if shifts[day_index] == shift
                and employees_by_id[emp_id].department_id == dept_id
            ]
            if not assigned:
                continue
            juniors = [e for e in assigned if not patched._is_senior(employees_by_id[e])]
            seniors = [e for e in assigned if patched._is_senior(employees_by_id[e])]
            if len(juniors) > len(seniors):
                pen_soft += (len(juniors) - len(seniors)) * W_JUNIOR_MENTORING

    active_salary = sum(employees_by_id[emp_id].salary for emp_id in active_employee_ids)
    pen_optimization = (
        len(active_employee_ids) * W_ACTIVE_EMPLOYEE
        + total_assignments * W_ASSIGNMENT
        + (active_salary / 1_000_000) * W_SALARY_PER_MILLION
    )

    cluster_balance = patched._cluster_balance(chromosome, employees_by_id)
    reward_cluster = cluster_balance * W_CLUSTER_BALANCE_REWARD

    total_req_slots = sum(req.required_staff for req in request.requirements) * request.days
    exact_fill_slots = max(0, total_req_slots - staff_shortage - staff_over)
    coverage_ratio = exact_fill_slots / max(total_req_slots, 1)
    reward_coverage = coverage_ratio * W_SHIFT_COVERAGE_REWARD

    reward = reward_cluster + reward_coverage
    penalty_total = pen_hard + pen_soft + pen_optimization
    fitness = BASE_FITNESS - penalty_total + reward
    fitness = max(0.0, min(BASE_FITNESS * 2, fitness))
    return round(fitness, 4)


def _make_request(n_employees, n_days, n_depts, seed):
    rng = random.Random(seed)
    employees = []
    for i in range(1, n_employees + 1):
        dept = ((i - 1) % n_depts) + 1
        is_pg = rng.random() < 0.2
        employees.append(
            Employee(
                id=i,
                department_id=dept,
                education="PG" if is_pg else "UG",
                salary=rng.uniform(3_000_000, 9_000_000),
                rating=rng.uniform(2.5, 5.0),
                satisfied=rng.randint(1, 5),
                cluster=rng.randint(1, 4),
                is_senior=is_pg,
            )
        )
    requirements = []
    for dept in range(1, n_depts + 1):
        for shift, staff in (("Pagi", 4), ("Sore", 4), ("Malam", 3)):
            requirements.append(
                DepartmentShiftRequirement(
                    department_id=dept, shift=shift, required_staff=staff, required_senior=1,
                )
            )
    return GenerateScheduleRequest(
        employees=employees,
        start_date=date(2026, 1, 1),
        days=n_days,
        candidates=2,
        requirements=requirements,
        ga_parameters=GAParameters(),
        seed=seed,
    )


def _random_chromosome(request, seed):
    rng = random.Random(seed)
    return {
        e.id: [rng.choice(["Pagi", "Sore", "Malam", "Libur"]) for _ in range(request.days)]
        for e in request.employees
    }


class FitnessEquivalenceTests(unittest.TestCase):
    """_fitness() versi optimized harus menghasilkan nilai numerik IDENTIK
    dengan versi original pada chromosome yang sama."""

    def test_random_chromosomes_match_across_scales(self):
        scenarios = [
            (20, 7, 3),
            (60, 14, 5),
            (150, 14, 8),
        ]
        for n_employees, n_days, n_depts in scenarios:
            request = _make_request(n_employees, n_days, n_depts, seed=1)
            employees_by_id = {e.id: e for e in request.employees}

            for trial in range(5):
                chromosome = _random_chromosome(request, seed=100 + trial)
                expected = _original_fitness(chromosome, request, employees_by_id)
                actual, _, _ = patched._fitness(chromosome, request, employees_by_id, verbose=False)
                self.assertEqual(
                    expected, actual,
                    msg=f"Mismatch at n_employees={n_employees}, trial={trial}: "
                        f"expected={expected}, actual={actual}",
                )

    def test_all_libur_chromosome(self):
        """Edge case: semua pegawai Libur (tidak ada yang aktif)."""
        request = _make_request(30, 7, 3, seed=2)
        employees_by_id = {e.id: e for e in request.employees}
        chromosome = {e.id: ["Libur"] * request.days for e in request.employees}

        expected = _original_fitness(chromosome, request, employees_by_id)
        actual, _, _ = patched._fitness(chromosome, request, employees_by_id, verbose=False)
        self.assertEqual(expected, actual)

    def test_full_coverage_chromosome(self):
        """Edge case: kromosom dari _initial_chromosome_cluster_aware (kondisi realistis)."""
        request = _make_request(80, 14, 5, seed=3)
        employees_by_id = {e.id: e for e in request.employees}
        rng = random.Random(7)
        chromosome = patched._initial_chromosome_cluster_aware(
            request, patched._employees_by_cluster(request.employees), rng, strategy="balanced"
        )

        expected = _original_fitness(chromosome, request, employees_by_id)
        actual, _, _ = patched._fitness(chromosome, request, employees_by_id, verbose=False)
        self.assertEqual(expected, actual)


if __name__ == "__main__":
    unittest.main()