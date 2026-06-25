import os
import sys
from datetime import date
# Append shiftly-ai to path
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__))))

from app.schemas import GenerateScheduleRequest, Employee, DepartmentShiftRequirement, GAParameters
from app.services.ga_engine import generate_candidates

employees = []
# Create 100 employees in department 1
for i in range(1, 101):
    employees.append(
        Employee(
            id=i,
            department_id=1,
            education="PG" if i <= 20 else "UG",
            job_level=5 if i <= 20 else 2,
            age=30,
            salary=5000000,
            rating=4.0,
            satisfied=4,
            certifications=1 if i <= 20 else 0,
            cluster=1 if i <= 20 else 2,
            is_senior=(i <= 20)
        )
    )

requirements = [
    DepartmentShiftRequirement(department_id=1, shift="Pagi", required_staff=5, required_senior=1),
    DepartmentShiftRequirement(department_id=1, shift="Sore", required_staff=5, required_senior=1),
    DepartmentShiftRequirement(department_id=1, shift="Malam", required_staff=4, required_senior=1),
]

request = GenerateScheduleRequest(
    employees=employees,
    start_date=date(2026, 1, 1),
    days=7,
    candidates=2,
    requirements=requirements,
    ga_parameters=GAParameters(
        population_size=100, 
        generations=100,
        elite_count=5,
        tournament_size=4,
        crossover_parent_one_rate=0.8,
        mutation_rate=0.08
    ),
    seed=42
)

try:
    print(f"Total employee pool: {len(employees)}")
    print("Running GA...")
    candidates = generate_candidates(request)
    for c in candidates:
        print(f"Candidate {c.candidate_id}:")
        print(f"  Active Employees: {c.summary.active_employees} out of {len(employees)}")
        print(f"  Total Assignments: {c.summary.total_assignments}")
        print(f"  Fitness: {c.summary.ga_fitness}")
        print(f"  Hard Violations: {c.summary.hard_violation_count}")
        print(f"  Soft Violations: {c.summary.soft_violation_count}")
        # Validate that assignments only contain active employees
        assigned_emp_ids = set(a.employee_id for a in c.assignments)
        print(f"  Assigned Employee IDs matches active_employees count: {len(assigned_emp_ids) == c.summary.active_employees}")
        if len(assigned_emp_ids) != c.summary.active_employees:
            print(f"  Mismatch: Assigned IDs={len(assigned_emp_ids)}, Active={c.summary.active_employees}")
            print(f"  Assigned IDs: {assigned_emp_ids}")
            
    print("Test finished successfully!")
except Exception as e:
    import traceback
    traceback.print_exc()
    print("Test failed!")
