"""Schema request/response FastAPI.

Struktur di sini sengaja mengikuti tabel Laravel: employee memiliki
department_id, schedule candidate punya assignments dan constraint_reports
yang bisa langsung disimpan ke database web.
"""

from datetime import date, timedelta
from typing import Literal

from pydantic import BaseModel, Field, model_validator


ShiftName = Literal["Pagi", "Sore", "Malam", "Libur"]


class Employee(BaseModel):
    id: int
    name: str | None = None
    department_id: int
    department: str | None = None
    education: str | None = None
    job_level: int = Field(default=1, ge=1)
    age: int = Field(default=25, ge=16)
    salary: float = Field(default=0, ge=0)
    rating: float = Field(default=3, ge=0)
    satisfied: int = Field(default=3, ge=0)
    certifications: int = Field(default=0, ge=0)
    cluster: int | None = None
    is_senior: bool | None = None


class ClusterRequest(BaseModel):
    employees: list[Employee]
    n_clusters: int = Field(default=4, ge=1, le=10)


class EmployeeCluster(BaseModel):
    employee_id: int
    cluster: int
    cluster_name: str
    description: str


class ClusterResponse(BaseModel):
    n_clusters: int
    clusters: list[EmployeeCluster]


class DepartmentShiftRequirement(BaseModel):
    department_id: int
    shift: Literal["Pagi", "Sore", "Malam"]
    required_staff: int = Field(default=0, ge=0)
    required_senior: int = Field(default=0, ge=0)


class GAParameters(BaseModel):
    population_size: int = Field(default=40, ge=4, le=200)
    generations: int = Field(default=80, ge=1, le=500)
    elite_count: int = Field(default=2, ge=1, le=10)
    tournament_size: int = Field(default=4, ge=2, le=10)
    crossover_parent_one_rate: float = Field(default=0.8, ge=0, le=1)
    mutation_rate: float = Field(default=0.08, ge=0, le=1)


class GenerateScheduleRequest(BaseModel):
    employees: list[Employee]
    start_date: date
    end_date: date | None = None
    days: int = Field(default=7, ge=1, le=31)
    candidates: int = Field(default=3, ge=1, le=5)
    requirements: list[DepartmentShiftRequirement]
    ga_parameters: GAParameters = Field(default_factory=GAParameters)
    seed: int | None = 42

    @model_validator(mode="after")
    def sync_schedule_range(self) -> "GenerateScheduleRequest":
        if self.end_date is not None:
            if self.end_date < self.start_date:
                raise ValueError("end_date tidak boleh lebih awal dari start_date")

            self.days = (self.end_date - self.start_date).days + 1

        if self.days < 1 or self.days > 31:
            raise ValueError("durasi jadwal harus 1 sampai 31 hari")

        if self.end_date is None:
            self.end_date = self.start_date + timedelta(days=self.days - 1)

        return self


class ShiftAssignment(BaseModel):
    employee_id: int
    department_id: int
    date: date
    shift: ShiftName
    cluster_label: int | None = None
    is_senior_snapshot: bool = False
    salary_snapshot: float = 0


class ScheduleSummary(BaseModel):
    total_salary: float
    active_employees: int
    total_assignments: int
    ga_fitness: float
    cluster_balance: float
    shift_counts: dict[str, int]
    hard_violation_count: int
    soft_violation_count: int
    consecutive_shift_violations: int
    one_shift_per_day_violations: int
    weekly_day_off_violations: int
    junior_mentoring_violations: int = 0


class ConstraintReport(BaseModel):
    department_id: int
    date: date
    shift: Literal["Pagi", "Sore", "Malam"]
    required_staff: int
    actual_staff: int
    required_senior: int
    actual_senior: int
    has_hard_violation: bool


class ScheduleCandidate(BaseModel):
    candidate_id: str
    assignments: list[ShiftAssignment]
    constraint_reports: list[ConstraintReport]
    summary: ScheduleSummary


class GenerateScheduleResponse(BaseModel):
    candidates: list[ScheduleCandidate]


class EvaluateCandidatesRequest(BaseModel):
    candidates: list[ScheduleCandidate]


class EvaluatedCandidate(ScheduleCandidate):
    rf_profit_score: float


class EvaluateCandidatesResponse(BaseModel):
    candidates: list[EvaluatedCandidate]
