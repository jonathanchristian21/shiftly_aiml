<?php

namespace App\Services;

use App\Models\DepartmentShiftRequirement;
use App\Models\Employee;
use App\Models\ScheduleCandidate;
use App\Models\ScheduleRun;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Workflow generate jadwal dari sisi Laravel.
 *
 * Tugasnya: siapkan pool employee dan requirement dari MySQL, panggil FastAPI,
 * lalu simpan schedule_runs, candidates, entries, dan constraint reports.
 */
class ScheduleGenerationService
{
    public function __construct(private readonly ShiftlyAiService $ai)
    {
    }

    public function runClustering(array $filters = [], int $clusters = 3): array
    {
        $employees = $this->employeePool($filters)->get();
        $payload = $employees->map(fn (Employee $employee) => $this->employeePayload($employee))->values()->all();
        $result = $this->ai->clusterEmployees($payload, $clusters);
        $clusterMap = collect($result['clusters'] ?? [])->keyBy('employee_id');

        DB::transaction(function () use ($employees, $clusterMap) {
            foreach ($employees as $employee) {
                $cluster = $clusterMap->get($employee->id);

                if ($cluster === null) {
                    continue;
                }

                $employee->update([
                    'cluster_label' => $cluster['cluster'],
                    'clustered_at' => now(),
                ]);
            }
        });

        return $result;
    }

    public function generate(array $input, ?int $managerId = null): ScheduleRun
    {
        $startDate = Carbon::parse($input['start_date'])->toDateString();
        $endDate = Carbon::parse($input['end_date'])->toDateString();
        $days = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        $filters = $input['filters'] ?? [];
        $employees = $this->employeePool($filters)->get();

        if ($employees->isEmpty()) {
            throw new \InvalidArgumentException('Pool employee kosong. Pilih minimal satu employee aktif.');
        }

        $requirements = $this->requirementsForPool($employees)->values();

        if ($requirements->isEmpty()) {
            throw new \InvalidArgumentException('Requirement department-shift belum tersedia untuk pool ini.');
        }

        $gaParameters = $input['ga_parameters'] ?? [];
        $payload = [
            'employees' => $employees->map(fn (Employee $employee) => $this->employeePayload($employee))->values()->all(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'candidates' => (int) ($input['candidates'] ?? 3),
            'requirements' => $requirements->all(),
            'ga_parameters' => $gaParameters,
            'seed' => $input['seed'] ?? 42,
        ];

        $generated = $this->ai->generateSchedules($payload);
        $evaluated = $this->ai->evaluateCandidates($generated['candidates'] ?? []);

        return DB::transaction(function () use (
            $managerId,
            $input,
            $startDate,
            $endDate,
            $days,
            $filters,
            $requirements,
            $gaParameters,
            $evaluated,
        ) {
            $scheduleRun = ScheduleRun::create([
                'manager_id' => $managerId,
                'name' => $input['name'] ?? 'Generate '.now()->format('Y-m-d H:i'),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days' => $days,
                'filters' => $filters,
                'requirements_snapshot' => $requirements->all(),
                'ga_parameters' => $gaParameters,
                'generated_at' => now(),
                'status' => 'draft',
            ]);

            foreach ($evaluated['candidates'] ?? [] as $candidatePayload) {
                $this->storeCandidate($scheduleRun, $candidatePayload);
            }

            return $scheduleRun->load('candidates');
        });
    }

    public function publish(ScheduleRun $scheduleRun, ScheduleCandidate $scheduleCandidate): ScheduleRun
    {
        if ($scheduleCandidate->schedule_run_id !== $scheduleRun->id) {
            throw new \InvalidArgumentException('Candidate tidak berasal dari schedule run ini.');
        }

        DB::transaction(function () use ($scheduleRun, $scheduleCandidate) {
            $scheduleRun->candidates()->where('id', '!=', $scheduleCandidate->id)->update([
                'status' => 'discarded',
            ]);

            $scheduleCandidate->update([
                'status' => 'selected',
            ]);

            $scheduleRun->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
        });

        return $scheduleRun->refresh()->load('selectedCandidate');
    }

    private function employeePool(array $filters)
    {
        return Employee::query()
            ->with('department')
            ->active()
            ->when($filters['employee_ids'] ?? null, fn ($query, $ids) => $query->whereIn('id', $ids))
            ->when($filters['department_ids'] ?? null, fn ($query, $ids) => $query->whereIn('department_id', $ids))
            ->when($filters['education'] ?? null, fn ($query, $education) => $query->where('education', $education))
            ->when($filters['job_level'] ?? null, fn ($query, $level) => $query->where('job_level', $level))
            ->when(array_key_exists('cluster_label', $filters), fn ($query) => $query->where('cluster_label', $filters['cluster_label']));
    }

    private function requirementsForPool(Collection $employees)
    {
        $departmentIds = $employees->pluck('department_id')->unique()->values();

        return DepartmentShiftRequirement::query()
            ->active()
            ->whereIn('department_id', $departmentIds)
            ->get()
            ->map(fn (DepartmentShiftRequirement $requirement) => [
                'department_id' => $requirement->department_id,
                'shift' => $requirement->shift,
                'required_staff' => $requirement->required_staff,
                'required_senior' => $requirement->required_senior,
            ]);
    }

    private function employeePayload(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'department_id' => $employee->department_id,
            'department' => $employee->department?->name,
            'education' => $employee->education,
            'job_level' => $employee->job_level,
            'age' => $employee->age,
            'salary' => (float) $employee->salary / 12, // Convert annual to monthly
            'rating' => (float) $employee->rating,
            'certifications' => $employee->certifications,
            'cluster' => $employee->cluster_label,
            'is_senior' => $employee->is_senior,
        ];
    }

    private function storeCandidate(ScheduleRun $scheduleRun, array $payload): ScheduleCandidate
    {
        $summary = $payload['summary'];
        $candidate = $scheduleRun->candidates()->create([
            'candidate_code' => $payload['candidate_id'],
            'ga_fitness' => $summary['ga_fitness'],
            'rf_profit_score' => $payload['rf_profit_score'] ?? null,
            'total_salary' => $summary['total_salary'],
            'active_employees' => $summary['active_employees'],
            'total_assignments' => $summary['total_assignments'],
            'cluster_balance' => $summary['cluster_balance'],
            'hard_violation_count' => $summary['hard_violation_count'],
            'soft_violation_count' => $summary['soft_violation_count'],
            'consecutive_shift_violations' => $summary['consecutive_shift_violations'],
            'one_shift_per_day_violations' => $summary['one_shift_per_day_violations'],
            'weekly_day_off_violations' => $summary['weekly_day_off_violations'],
            'shift_counts' => $summary['shift_counts'],
            'status' => 'candidate',
        ]);

        $candidate->entries()->createMany(
            collect($payload['assignments'] ?? [])->map(fn (array $assignment) => [
                'employee_id' => $assignment['employee_id'],
                'department_id' => $assignment['department_id'],
                'shift_date' => $assignment['date'],
                'shift' => $assignment['shift'],
                'cluster_label' => $assignment['cluster_label'],
                'is_senior_snapshot' => $assignment['is_senior_snapshot'],
                'salary_snapshot' => $assignment['salary_snapshot'],
            ])->all()
        );

        $candidate->constraintReports()->createMany(
            collect($payload['constraint_reports'] ?? [])->map(fn (array $report) => [
                'department_id' => $report['department_id'],
                'shift_date' => $report['date'],
                'shift' => $report['shift'],
                'required_staff' => $report['required_staff'],
                'actual_staff' => $report['actual_staff'],
                'required_senior' => $report['required_senior'],
                'actual_senior' => $report['actual_senior'],
                'has_hard_violation' => $report['has_hard_violation'],
            ])->all()
        );

        return $candidate;
    }
}
