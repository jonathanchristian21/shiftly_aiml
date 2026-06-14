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

    public function cluster(array $payload): array
    {
        return $this->client()
            ->post('/cluster', $payload)
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
}
