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


            // ── Simpan ke session ─────────────────────────────────────────────
            session(['schedule_candidates' => $evaluatedCandidates]);
            session(['schedule_pool_info'  => [
                'start_date'     => $validated['start_date'],
                'days'           => $validated['days'],
                'employee_count' => $employees->count(),
            ]]);

            return redirect()->route('manager.schedules.compare')
                ->with('success', 'Successfully generated ' . count($evaluatedCandidates) . ' schedule candidates!');

        } catch (\Exception $e) {
            return back()->with('error', 'Schedule generation failed: ' . $e->getMessage());
        }
    }

    public function compare()
    {
        $candidates = session('schedule_candidates', []);
        $poolInfo   = session('schedule_pool_info', []);

        if (empty($candidates)) {
            return redirect()->route('manager.schedules.create')
                ->with('error', 'No schedule candidates to compare. Please generate schedules first.');
        }

        return view('manager.schedules.compare', compact('candidates', 'poolInfo'));
    }

    public function showCandidate($candidateId)
    {
        $candidates = session('schedule_candidates', []);
        $poolInfo = session('schedule_pool_info', []);

        $candidate = collect($candidates)->firstWhere('candidate_id', $candidateId);

        if (!$candidate) {
            return redirect()->route('manager.schedules.compare')
                ->with('error', 'Candidate not found.');
        }

        // Enrich assignments with employee and department names
        $enrichedCandidate = $candidate;
        $employeeIds = collect($candidate['assignments'])->pluck('employee_id')->unique();
        $departmentIds = collect($candidate['assignments'])->pluck('department_id')->unique();
        
        $employees = Employee::whereIn('id', $employeeIds)->get()->keyBy('id');
        $departments = Department::whereIn('id', $departmentIds)->get()->keyBy('id');
        
        $enrichedCandidate['assignments'] = collect($candidate['assignments'])->map(function($assignment) use ($employees, $departments) {
            $assignment['employee_name'] = $employees[$assignment['employee_id']]->name ?? 'Employee #' . $assignment['employee_id'];
            $assignment['department_name'] = $departments[$assignment['department_id']]->name ?? 'Dept #' . $assignment['department_id'];
            return $assignment;
        })->toArray();

        return view('manager.schedules.candidate-detail', compact('enrichedCandidate', 'poolInfo'));
    }

    public function publish(Request $request)
    {
        $validated = $request->validate([
            'candidate_id' => 'required|string',
        ]);

        $candidates        = session('schedule_candidates', []);
        $poolInfo          = session('schedule_pool_info', []);
        $selectedCandidate = collect($candidates)->firstWhere('candidate_id', $validated['candidate_id']);

        if (!$selectedCandidate) {
            return back()->with('error', 'Selected candidate not found.');
        }

        DB::beginTransaction();
        try {
            $scheduleRun = ScheduleRun::create([
                'manager_id'   => Auth::id(),
                'name'         => 'Schedule ' . now()->format('Y-m-d H:i'),
                'start_date'   => $poolInfo['start_date'],
                'end_date'     => date('Y-m-d', strtotime($poolInfo['start_date'] . ' + ' . ($poolInfo['days'] - 1) . ' days')),
                'days'         => $poolInfo['days'],
                'status'       => 'published',
                'generated_at' => now(),
                'published_at' => now(),
            ]);

            $candidate = ScheduleCandidate::create([
                'schedule_run_id'      => $scheduleRun->id,
                'candidate_code'       => $selectedCandidate['candidate_id'],
                'ga_fitness'           => $selectedCandidate['summary']['ga_fitness'],
                'rf_profit_score'      => $selectedCandidate['rf_profit_score'] ?? null,
                'total_salary'         => $selectedCandidate['summary']['total_salary'],
                'active_employees'     => $selectedCandidate['summary']['active_employees'],
                'total_assignments'    => $selectedCandidate['summary']['total_assignments'],
                'cluster_balance'      => $selectedCandidate['summary']['cluster_balance'] ?? null,
                'hard_violation_count' => $selectedCandidate['summary']['hard_violation_count'] ?? 0,
                'soft_violation_count' => $selectedCandidate['summary']['soft_violation_count'] ?? 0,
                'shift_counts'         => $selectedCandidate['summary']['shift_counts'] ?? [],
                'status'               => 'selected',
            ]);

            foreach ($selectedCandidate['assignments'] as $assignment) {
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

            DB::commit();
            session()->forget(['schedule_candidates', 'schedule_pool_info']);

            return redirect()->route('manager.schedules.show', $scheduleRun)
                ->with('success', 'Schedule published successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to publish schedule: ' . $e->getMessage());
        }
    }

    public function show(ScheduleRun $schedule)
    {
        $schedule->load(['selectedCandidate.entries.employee.department']);
        return view('manager.schedules.show', compact('schedule'));
    }

    public function destroy(ScheduleRun $schedule)
    {
        $schedule->update(['status' => 'archived', 'archived_at' => now()]);
        return redirect()->route('manager.schedules.index')
            ->with('success', 'Schedule archived successfully.');
    }
}