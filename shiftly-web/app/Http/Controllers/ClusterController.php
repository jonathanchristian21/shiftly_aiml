<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\ShiftlyAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClusterController extends Controller
{
    public function __construct(
        protected ShiftlyAiService $aiService
    ) {}

    public function show()
    {
        $stats = [
            'total_employees' => Employee::active()->count(),
            'clustered' => Employee::active()->whereNotNull('cluster_label')->count(),
            'not_clustered' => Employee::active()->whereNull('cluster_label')->count(),
        ];

        $clusterDistribution = Employee::active()
            ->whereNotNull('cluster_label')
            ->select('cluster_label', DB::raw('count(*) as count'))
            ->groupBy('cluster_label')
            ->orderBy('cluster_label')
            ->get();

        // Analyze cluster characteristics
        $clusterAnalysis = [];
        if ($clusterDistribution->isNotEmpty()) {
            foreach ($clusterDistribution as $cluster) {
                $employees = Employee::active()
                    ->where('cluster_label', $cluster->cluster_label)
                    ->get();

                // Mencegah error division by zero
                $totalEmp = max($employees->count(), 1);

                $clusterAnalysis[$cluster->cluster_label] = [
                    'count' => $employees->count(),
                    'avg_age' => round($employees->avg('age'), 1),
                    'avg_salary' => round($employees->avg('salary'), 2),
                    'avg_job_level' => round($employees->avg('job_level'), 1),
                    'avg_rating' => round($employees->avg('rating'), 1),
                    'avg_satisfied' => round($employees->avg('satisfied'), 1), // DIKIRIM KE VIEW AGAR TIDAK ERROR
                    'avg_certifications' => round($employees->avg('certifications'), 1),
                    'pg_percentage' => round(($employees->where('education', 'PG')->count() / $totalEmp) * 100, 1),
                    'senior_count' => $employees->where('is_senior', true)->count(),
                ];
            }
        }

        return view('manager.cluster.index', compact('stats', 'clusterDistribution', 'clusterAnalysis'));
    }

    public function startClustering(Request $request)
    {
        // Kita kunci secara otomatis ke 4 cluster
        $nClusters = 4;

        $employees = Employee::active()->get();

        if ($employees->isEmpty()) {
            return back()->with('error', 'No active employees to cluster.');
        }

        try {
            $payload = [
                'employees' => $employees->map(fn($emp) => [
                    'id' => $emp->id,
                    'name' => $emp->name,
                    'department_id' => $emp->department_id,
                    'education' => $emp->education,
                    'job_level' => $emp->job_level,
                    'age' => $emp->age,
                    'salary' => (float) $emp->salary,
                    'rating' => (float) $emp->rating,
                    'satisfied' => (int) $emp->satisfied, // WAJIB DIKIRIM KE AI
                    'certifications' => $emp->certifications,
                ])->toArray(),
                'n_clusters' => $nClusters,
            ];

            $response = $this->aiService->cluster($payload);

            DB::beginTransaction();
            foreach ($response['clusters'] as $cluster) {
                Employee::where('id', $cluster['employee_id'])->update([
                    'cluster_label' => $cluster['cluster'], // 1, 2, 3, atau 4
                    'clustered_at' => now(),
                ]);
            }
            DB::commit();

            return redirect()->route('manager.cluster.show')
                ->with('success', "Sukses! AI telah memetakan pegawai ke dalam 4 Profil Operasional (A, B, C, D).");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Clustering failed: '.$e->getMessage());
        }
    }
}