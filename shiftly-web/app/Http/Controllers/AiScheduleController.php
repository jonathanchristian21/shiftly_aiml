<?php

namespace App\Http\Controllers;

use App\Models\ScheduleCandidate;
use App\Models\ScheduleRun;
use App\Services\ScheduleGenerationService;
use App\Services\ShiftlyAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Endpoint jembatan UI Laravel dengan FastAPI.
 *
 * Controller ini belum membuat tampilan Blade; ia menyediakan aksi yang nanti
 * dipanggil halaman manager untuk clustering, generate jadwal, dan publish.
 */
class AiScheduleController extends Controller
{
    public function health(ShiftlyAiService $ai): JsonResponse
    {
        return response()->json($ai->health());
    }

    public function cluster(Request $request, ScheduleGenerationService $service): JsonResponse
    {
        $data = $request->validate([
            'clusters' => ['nullable', 'integer', 'min:1', 'max:10'],
            'filters.employee_ids' => ['nullable', 'array'],
            'filters.employee_ids.*' => ['integer', 'exists:employees,id'],
            'filters.department_ids' => ['nullable', 'array'],
            'filters.department_ids.*' => ['integer', 'exists:departments,id'],
            'filters.education' => ['nullable', 'string', Rule::in(['PG', 'UG'])],
            'filters.job_level' => ['nullable', 'integer', 'min:1'],
            'filters.cluster_label' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $result = $service->runClustering(
                $data['filters'] ?? [],
                (int) ($data['clusters'] ?? 3),
            );

            return response()->json($result);
        } catch (Throwable $error) {
            return response()->json([
                'message' => 'Gagal menjalankan clustering.',
                'error' => $error->getMessage(),
            ], 422);
        }
    }

    public function generate(Request $request, ScheduleGenerationService $service): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'candidates' => ['nullable', 'integer', 'min:1', 'max:5'],
            'seed' => ['nullable', 'integer'],
            'filters.employee_ids' => ['nullable', 'array'],
            'filters.employee_ids.*' => ['integer', 'exists:employees,id'],
            'filters.department_ids' => ['nullable', 'array'],
            'filters.department_ids.*' => ['integer', 'exists:departments,id'],
            'filters.education' => ['nullable', 'string', Rule::in(['PG', 'UG'])],
            'filters.job_level' => ['nullable', 'integer', 'min:1'],
            'filters.cluster_label' => ['nullable', 'integer', 'min:0'],
            'ga_parameters.population_size' => ['nullable', 'integer', 'min:4', 'max:200'],
            'ga_parameters.generations' => ['nullable', 'integer', 'min:1', 'max:500'],
            'ga_parameters.elite_count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'ga_parameters.tournament_size' => ['nullable', 'integer', 'min:2', 'max:10'],
            'ga_parameters.crossover_parent_one_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'ga_parameters.mutation_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        try {
            $scheduleRun = $service->generate($data, $request->user()?->id);

            return response()->json([
                'message' => 'Generate jadwal selesai.',
                'schedule_run' => $scheduleRun,
            ], 201);
        } catch (Throwable $error) {
            return response()->json([
                'message' => 'Gagal generate jadwal.',
                'error' => $error->getMessage(),
            ], 422);
        }
    }

    public function publish(
        ScheduleRun $scheduleRun,
        ScheduleCandidate $scheduleCandidate,
        ScheduleGenerationService $service,
    ): JsonResponse {
        try {
            $publishedRun = $service->publish($scheduleRun, $scheduleCandidate);

            return response()->json([
                'message' => 'Jadwal berhasil dipublish.',
                'schedule_run' => $publishedRun,
            ]);
        } catch (Throwable $error) {
            return response()->json([
                'message' => 'Gagal publish jadwal.',
                'error' => $error->getMessage(),
            ], 422);
        }
    }
}
