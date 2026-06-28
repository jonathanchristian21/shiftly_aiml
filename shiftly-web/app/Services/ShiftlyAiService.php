<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client untuk komunikasi Laravel -> FastAPI.
 *
 * Service ini hanya menangani request/response AI. Workflow database tetap
 * dikerjakan di ScheduleGenerationService.
 */
class ShiftlyAiService
{
    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.shiftly_ai.url'))
            ->timeout((int) config('services.shiftly_ai.timeout', 120))
            ->acceptJson()
            ->asJson();
    }

    public function health(): array
    {
        return $this->client()
            ->get('/health')
            ->throw()
            ->json();
    }

    public function clusterEmployees(array $employees, int $clusters = 3): array
    {
        return $this->client()
            ->post('/cluster', [
                'employees' => $employees,
                'n_clusters' => $clusters,
            ])
            ->throw()
            ->json();
    }

    public function generateSchedules(array $payload): array
    {
        return $this->client()
            ->post('/generate-schedules', $payload)
            ->throw()
            ->json();
    }

    public function evaluateCandidates(array $payload): array
    {
        return $this->client()
            ->post('/evaluate-candidates', $payload)
            ->throw()
            ->json();
    }

    /**
     * Generate jadwal dengan GA lalu langsung evaluasi dengan Random Forest.
     *
     * Endpoint ini menerima payload yang sama dengan /generate-schedules,
     * dan secara otomatis mengalirkan hasilnya ke RF dengan data employees
     * yang SAMA (bukan fallback default).
     *
     * Mengapa satu endpoint lebih baik daripada dua panggilan terpisah?
     * - Data employees dikirim SEKALI, dipakai oleh GA dan RF.
     * - RF mendapat data asli (age, job_level, education, dll.) untuk
     *   setiap employee, sehingga prediksi salary akurat dan rf_profit_score
     *   benar-benar mencerminkan komposisi tim di jadwal tersebut.
     * - Menghindari bug: jika dua panggilan terpisah, employees bisa terlupa
     *   dikirim ke RF (penyebab rf_profit_score = 0%).
     */
    public function generateAndEvaluate(array $payload): array
    {
        return $this->client()
            ->post('/generate-and-evaluate', $payload)
            ->throw()
            ->json();
    }
}