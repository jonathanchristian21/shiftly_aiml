<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentShiftRequirement;
use App\Models\Employee;
use App\Models\ScheduleRun;
use App\Models\ScheduleCandidate;
use App\Models\ScheduleEntry;
use App\Services\ShiftlyAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function __construct(
        protected ShiftlyAiService $aiService
    ) {}

    public function index()
    {
        $schedules = ScheduleRun::with(['selectedCandidate'])
            ->latest()
            ->paginate(15);

        return view('manager.schedules.index', compact('schedules'));
    }

    public function create()
    {
        $departments = Department::where('is_active', true)->get();
        $employees = Employee::with('department')->active()->whereNotNull('cluster_label')->get();

        $stats = [
            'total_employees' => Employee::active()->count(),
            'clustered'       => Employee::active()->whereNotNull('cluster_label')->count(),
            'departments'     => $departments->count(),
        ];

        return view('manager.schedules.create', compact('departments', 'employees', 'stats'));
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'start_date'     => 'required|date',
            'days'           => 'required|integer|min:1|max:31',
            'employee_ids'   => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'candidates'     => 'integer|min:1|max:5',
        ]);

        // ── Ambil employees yang dipilih ──────────────────────────────────────
        $employees = Employee::with('department')
            ->whereIn('id', $validated['employee_ids'])
            ->active()
            ->get();

        if ($employees->isEmpty()) {
            return back()->with('error', 'No employees selected for scheduling.');
        }

        if ($employees->whereNull('cluster_label')->isNotEmpty()) {
            return back()->with('error', 'Some selected employees have not been clustered yet. Please run clustering first.');
        }

        // ── Shift requirements ────────────────────────────────────────────────
        $requirements = DepartmentShiftRequirement::where('is_active', true)->get();

        // ── Tuning GA berdasarkan ukuran input ────────────────────────────────
        $employeeCount = $employees->count();
        $days          = $validated['days'];
        $populationSize = $employeeCount >= 300 ? 24 : ($employeeCount >= 180 ? 30 : 40);
        $generations    = $employeeCount >= 300 ? 40 : ($employeeCount >= 180 ? 55 : 80);

        if ($days > 14) {
            $populationSize = max(20, $populationSize - 4);
            $generations    = max(30, $generations - 10);
        }

        // ── Siapkan data employees satu kali ─────────────────────────────────
        // Array ini DIPAKAI ULANG untuk payload GA maupun payload RF agar RF
        // mendapat data nyata pegawai (age, job_level, education, dll.) dan
        // bukan fallback default yang menyebabkan rf_profit_score = 0%.
        $employeeData = $employees->map(fn($emp) => [
            'id'            => $emp->id,
            'name'          => $emp->name,
            'department_id' => $emp->department_id,
            'department'    => $emp->department->name,
            'education'     => $emp->education,
            'job_level'     => $emp->job_level,
            'age'           => $emp->age,
            'salary'        => (float) $emp->salary,
            'rating'        => (float) $emp->rating,
            'certifications'=> (int) ($emp->certifications ?? 0),
            'satisfied'     => (float) ($emp->satisfied ?? 0.5),
            'cluster'       => $emp->cluster_label,
            'is_senior'     => $emp->is_senior,
        ])->toArray();

        // ── Payload GA ────────────────────────────────────────────────────────
        $gaPayload = [
            'employees'     => $employeeData,
            'start_date'    => $validated['start_date'],
            'days'          => $validated['days'],
            'candidates'    => $validated['candidates'] ?? 3,
            'requirements'  => $requirements->map(fn($req) => [
                'department_id'  => $req->department_id,
                'shift'          => $req->shift,
                'required_staff' => $req->required_staff,
                'required_senior'=> $req->required_senior,
            ])->toArray(),
            'ga_parameters' => [
                'population_size'          => $populationSize,
                'generations'              => $generations,
                'elite_count'              => 2,
                'tournament_size'          => 4,
                'crossover_parent_one_rate'=> 0.8,
                'mutation_rate'            => 0.08,
            ],
            // Gunakan seed acak per request supaya GA tidak menghasilkan kandidat yang sama terus.
            // (Kalau seed fix, GA deterministic → output cenderung sama.)
            'seed' => random_int(1, PHP_INT_MAX),
        ];

        try {
            // Gunakan pipeline GA+RF sekaligus supaya hasil yang disimpan di DB
            // benar-benar berasal dari kandidat terbaru dan RF pakai payload yang sama.
            $rfResponse = $this->aiService->generateAndEvaluate($gaPayload);
            $evaluatedCandidates = $rfResponse['candidates'] ?? [];


            if (empty($evaluatedCandidates)) {
                return back()->with('error', 'No candidates returned from GA+RF pipeline.');
            }

            // ── Simpan ke database (bukan session) ────────────────────────────
            DB::beginTransaction();
            
            $scheduleRun = ScheduleRun::create([
                'manager_id'              => Auth::id(),
                'name'                    => 'Schedule ' . now()->format('Y-m-d H:i'),
                'start_date'              => $validated['start_date'],
                'end_date'                => date('Y-m-d', strtotime($validated['start_date'] . ' + ' . ($validated['days'] - 1) . ' days')),
                'days'                    => $validated['days'],
                'filters'                 => ['employee_ids' => $validated['employee_ids']],
                'requirements_snapshot'   => $requirements->toArray(),
                'ga_parameters'           => $gaPayload['ga_parameters'],
                'status'                  => 'draft',
                'generated_at'            => now(),
            ]);

            // Simpan SEMUA candidates ke database
            foreach ($evaluatedCandidates as $candidateData) {
                $candidate = ScheduleCandidate::create([
                    'schedule_run_id'      => $scheduleRun->id,
                    'candidate_code'       => $candidateData['candidate_id'],
                    'ga_fitness'           => $candidateData['summary']['ga_fitness'],
                    'rf_profit_score'      => $candidateData['rf_profit_score'] ?? null,
                    'total_salary'         => $candidateData['summary']['total_salary'],
                    'active_employees'     => $candidateData['summary']['active_employees'],
                    'total_assignments'    => $candidateData['summary']['total_assignments'],
                    'cluster_balance'      => $candidateData['summary']['cluster_balance'] ?? null,
                    'hard_violation_count' => $candidateData['summary']['hard_violation_count'] ?? 0,
                    'soft_violation_count' => $candidateData['summary']['soft_violation_count'] ?? 0,
                    'consecutive_shift_violations' => $candidateData['summary']['consecutive_shift_violations'] ?? 0,
                    'one_shift_per_day_violations' => $candidateData['summary']['one_shift_per_day_violations'] ?? 0,
                    'weekly_day_off_violations'    => $candidateData['summary']['weekly_day_off_violations'] ?? 0,
                    'shift_counts'         => $candidateData['summary']['shift_counts'] ?? [],
                    'status'               => 'candidate',
                ]);

                // Simpan assignments untuk setiap candidate
                foreach ($candidateData['assignments'] as $assignment) {
                    ScheduleEntry::create([
                        'schedule_candidate_id' => $candidate->id,
                        'employee_id'           => $assignment['employee_id'],
                        'department_id'         => $assignment['department_id'],
                        'shift_date'            => $assignment['date'],
                        'shift'                 => $assignment['shift'],
                        'cluster_label'         => $assignment['cluster_label'] ?? null,
                        'is_senior_snapshot'    => $assignment['is_senior_snapshot'] ?? false,
                        'salary_snapshot'       => $assignment['salary_snapshot'] ?? null,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('manager.schedules.compare', $scheduleRun)
                ->with('success', 'Successfully generated ' . count($evaluatedCandidates) . ' schedule candidates!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Schedule generation failed: ' . $e->getMessage());
        }
    }

    public function compare(ScheduleRun $schedule)
    {
        $schedule->load(['candidates.entries']);
        
        if ($schedule->candidates->isEmpty()) {
            return redirect()->route('manager.schedules.create')
                ->with('error', 'No schedule candidates found. Please generate schedules first.');
        }

        $poolInfo = [
            'start_date'     => $schedule->start_date->format('Y-m-d'),
            'days'           => $schedule->days,
            'employee_count' => count($schedule->filters['employee_ids'] ?? []),
        ];

        // Format candidates untuk view
        $candidates = $schedule->candidates->map(function($candidate) {
            // Hitung final score (GA Fitness 50% + RF Profit Score 50%)
            // Normalize GA Fitness ke skala 0-100 berdasarkan max fitness di batch ini
            $gaFitness = $candidate->ga_fitness ?? 0;
            $rfScore = $candidate->rf_profit_score ?? 0;
            
            return [
                'candidate_id' => $candidate->candidate_code,
                'ga_fitness'   => $candidate->ga_fitness,
                'rf_profit_score' => $candidate->rf_profit_score,
                'final_score'  => null, // Will be calculated after all candidates mapped
                'ga_fitness_raw' => $gaFitness,
                'summary'      => [
                    'ga_fitness'           => $candidate->ga_fitness,
                    'total_salary'         => $candidate->total_salary,
                    'active_employees'     => $candidate->active_employees,
                    'total_assignments'    => $candidate->total_assignments,
                    'cluster_balance'      => $candidate->cluster_balance,
                    'hard_violation_count' => $candidate->hard_violation_count,
                    'soft_violation_count' => $candidate->soft_violation_count,
                    'consecutive_shift_violations' => $candidate->consecutive_shift_violations,
                    'one_shift_per_day_violations' => $candidate->one_shift_per_day_violations,
                    'weekly_day_off_violations'    => $candidate->weekly_day_off_violations,
                    'shift_counts'         => $candidate->shift_counts,
                ],
                'assignments'  => $candidate->entries->map(fn($entry) => [
                    'employee_id'       => $entry->employee_id,
                    'department_id'     => $entry->department_id,
                    'date'              => $entry->shift_date,
                    'shift'             => $entry->shift,
                    'cluster_label'     => $entry->cluster_label,
                    'is_senior_snapshot'=> $entry->is_senior_snapshot,
                    'salary_snapshot'   => $entry->salary_snapshot,
                ])->toArray(),
            ];
        })->toArray();
        
        // Normalize GA Fitness ke skala 0-100 berdasarkan min-max dalam batch ini
        $gaFitnessValues = array_column($candidates, 'ga_fitness_raw');
        $minGaFitness = min($gaFitnessValues);
        $maxGaFitness = max($gaFitnessValues);
        $gaRange = $maxGaFitness - $minGaFitness;
        
        // Hitung final score untuk setiap candidate
        foreach ($candidates as &$candidate) {
            // Normalize GA Fitness ke 0-100
            if ($gaRange > 0) {
                $gaNorm = (($candidate['ga_fitness_raw'] - $minGaFitness) / $gaRange) * 100;
            } else {
                $gaNorm = 100; // Semua sama, beri nilai 100
            }
            
            $rfScore = $candidate['rf_profit_score'] ?? 0;
            
            // Final Score = (GA Norm × 50%) + (RF Score × 50%)
            $candidate['final_score'] = ($gaNorm * 0.5) + ($rfScore * 0.5);
            
            // Cleanup temporary field
            unset($candidate['ga_fitness_raw']);
        }
        unset($candidate); // Break reference

        return view('manager.schedules.compare', compact('candidates', 'poolInfo', 'schedule'));
    }

    public function showCandidate(ScheduleRun $schedule, $candidateCode)
    {
        $candidate = $schedule->candidates()->where('candidate_code', $candidateCode)->first();

        if (!$candidate) {
            return redirect()->route('manager.schedules.compare', $schedule)
                ->with('error', 'Candidate not found.');
        }

        $candidate->load(['entries.employee.department', 'entries.department']);

        $poolInfo = [
            'start_date'     => $schedule->start_date->format('Y-m-d'),
            'days'           => $schedule->days,
            'employee_count' => count($schedule->filters['employee_ids'] ?? []),
        ];
        
        // Get all candidates untuk normalisasi
        $allCandidates = $schedule->candidates;
        $gaFitnessValues = $allCandidates->pluck('ga_fitness')->toArray();
        $minGaFitness = min($gaFitnessValues);
        $maxGaFitness = max($gaFitnessValues);
        $gaRange = $maxGaFitness - $minGaFitness;
        
        // Normalize GA Fitness current candidate
        if ($gaRange > 0) {
            $gaNorm = (($candidate->ga_fitness - $minGaFitness) / $gaRange) * 100;
        } else {
            $gaNorm = 100;
        }
        
        $rfScore = $candidate->rf_profit_score ?? 0;
        $finalScore = ($gaNorm * 0.5) + ($rfScore * 0.5);

        // Format candidate untuk view
        $enrichedCandidate = [
            'candidate_id' => $candidate->candidate_code,
            'ga_fitness'   => $candidate->ga_fitness,
            'rf_profit_score' => $candidate->rf_profit_score,
            'final_score'  => $finalScore,
            'summary'      => [
                'ga_fitness'           => $candidate->ga_fitness,
                'total_salary'         => $candidate->total_salary,
                'active_employees'     => $candidate->active_employees,
                'total_assignments'    => $candidate->total_assignments,
                'cluster_balance'      => $candidate->cluster_balance,
                'hard_violation_count' => $candidate->hard_violation_count,
                'soft_violation_count' => $candidate->soft_violation_count,
                'consecutive_shift_violations' => $candidate->consecutive_shift_violations,
                'one_shift_per_day_violations' => $candidate->one_shift_per_day_violations,
                'weekly_day_off_violations'    => $candidate->weekly_day_off_violations,
                'shift_counts'         => $candidate->shift_counts,
            ],
            'assignments'  => $candidate->entries->map(fn($entry) => [
                'employee_id'       => $entry->employee_id,
                'employee_name'     => $entry->employee->name ?? 'Employee #' . $entry->employee_id,
                'department_id'     => $entry->department_id,
                'department_name'   => $entry->department->name ?? 'Dept #' . $entry->department_id,
                'date'              => $entry->shift_date,
                'shift'             => $entry->shift,
                'cluster_label'     => $entry->cluster_label,
                'is_senior_snapshot'=> $entry->is_senior_snapshot,
                'salary_snapshot'   => $entry->salary_snapshot,
            ])->toArray(),
        ];

        return view('manager.schedules.candidate-detail', compact('enrichedCandidate', 'poolInfo', 'schedule'));
    }

    public function publish(Request $request, ScheduleRun $schedule)
    {
        $validated = $request->validate([
            'candidate_id' => 'required|string',
        ]);

        $selectedCandidate = $schedule->candidates()
            ->where('candidate_code', $validated['candidate_id'])
            ->first();

        if (!$selectedCandidate) {
            return back()->with('error', 'Selected candidate not found.');
        }

        DB::beginTransaction();
        try {
            // Update candidate yang dipilih jadi 'selected'
            $selectedCandidate->update(['status' => 'selected']);
            
            // Update schedule run jadi 'published'
            $schedule->update([
                'status'       => 'published',
                'published_at' => now(),
            ]);

            DB::commit();

            return redirect()->route('manager.schedules.show', $schedule)
                ->with('success', 'Schedule published successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to publish schedule: ' . $e->getMessage());
        }
    }

    public function show(ScheduleRun $schedule)
    {
        $schedule->load(['selectedCandidate.entries' => function($query) {
            $query->whereHas('department', function($q) {
                $q->where('is_active', true);
            })->whereExists(function($q) {
                $q->select(DB::raw(1))
                  ->from('department_shift_requirements')
                  ->whereColumn('department_shift_requirements.department_id', 'schedule_entries.department_id')
                  ->whereColumn('department_shift_requirements.shift', 'schedule_entries.shift')
                  ->where('department_shift_requirements.is_active', true);
            });
        }, 'selectedCandidate.entries.employee.department']);
        
        return view('manager.schedules.show', compact('schedule'));
    }

    public function destroy(ScheduleRun $schedule)
    {
        $schedule->update(['status' => 'archived', 'archived_at' => now()]);
        return redirect()->route('manager.schedules.index')
            ->with('success', 'Schedule archived successfully.');
    }
}