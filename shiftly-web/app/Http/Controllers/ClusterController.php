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

        // Definisikan profil operasional (sesuai dengan map_clusters_to_profiles di service)
        $profileMap = [
            1 => [
                'name'  => 'SHIFT LEADERS (A)',
                'desc'  => 'Senior Produktif — Job Level & Salary Tinggi, Kepuasan Tinggi',
                'color' => 'text-emerald-600',
                'short' => 'Senior (PG), High Level & Salary'
            ],
            2 => [
                'name'  => 'JUNIOR/PROBLEMATIC (B)',
                'desc'  => 'Junior atau Bermasalah — Senioritas & Performa Rendah',
                'color' => 'text-sky',
                'short' => 'Junior (UG), Lower Level & Salary'
            ],
            3 => [
                'name'  => 'SENIOR UNHAPPY (C)',
                'desc'  => 'Senior Tidak Puas — Senioritas Tinggi, Kepuasan Rendah',
                'color' => 'text-purple-600',
                'short' => 'Senior, Low Satisfaction'
            ],
            4 => [
                'name'  => 'JUNIOR PRODUCTIVE (D)',
                'desc'  => 'Junior Produktif — Senioritas Rendah, Performa & Kepuasan Tinggi',
                'color' => 'text-amber-600',
                'short' => 'Junior, High Satisfaction & Rating'
            ],
        ];

        $clusterAnalysis = [];
        if ($clusterDistribution->isNotEmpty()) {
            foreach ($clusterDistribution as $cluster) {
                $employees = Employee::active()
                    ->where('cluster_label', $cluster->cluster_label)
                    ->get();

                $totalEmp = max($employees->count(), 1);
                $label = (int) $cluster->cluster_label; // pastikan integer

                $clusterAnalysis[$label] = [
                    'count' => $employees->count(),
                    'avg_age' => round($employees->avg('age'), 1),
                    'avg_salary' => round($employees->avg('salary') / 12, 2),
                    'avg_job_level' => round($employees->avg('job_level'), 1),
                    'avg_rating' => round($employees->avg('rating'), 1),
                    'avg_satisfied' => round($employees->avg('satisfied'), 1),
                    'avg_certifications' => round($employees->avg('certifications'), 1),
                    'pg_percentage' => round(($employees->where('education', 'PG')->count() / $totalEmp) * 100, 1),
                    'senior_count' => $employees->where('is_senior', true)->count(),
                    'profile' => $profileMap[$label] ?? [
                        'name'  => 'UNKNOWN',
                        'desc'  => 'Unmapped characteristics',
                        'color' => 'text-gray-600',
                        'short' => 'Unknown profile'
                    ],
                ];
            }
        }

        return view('manager.cluster.index', compact('stats', 'clusterDistribution', 'clusterAnalysis'));
    }

    public function startClustering(Request $request)
    {
        // Jadikan 4 cluster karena sesuai dengan 4 Profil Operasional (A, B, C, D)
        $nClusters = 4;

        $employees = Employee::active()->get();

        if ($employees->isEmpty()) {
            return back()->with('error', 'No active employees to cluster.');
        }

        try {
            $employeePayload = $employees->map(fn($emp) => [
                'id' => $emp->id,
                'name' => $emp->name,
                'department_id' => $emp->department_id,
                'education' => $emp->education,
                'job_level' => $emp->job_level,
                'age' => $emp->age,
                'salary' => (float) $emp->salary / 12, // Convert annual to monthly for consistency
                'rating' => (float) $emp->rating,
                'satisfied' => (int) $emp->satisfied,
                'certifications' => $emp->certifications,
            ])->toArray();

            $response = $this->aiService->clusterEmployees($employeePayload, $nClusters);

            DB::beginTransaction();
            foreach ($response['clusters'] as $cluster) {
                Employee::where('id', $cluster['employee_id'])->update([
                    'cluster_label' => $cluster['cluster'],
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