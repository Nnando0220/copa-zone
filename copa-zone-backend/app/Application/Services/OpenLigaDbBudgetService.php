<?php

namespace App\Application\Services;

use App\Models\ApiSyncLog;
use Carbon\CarbonImmutable;
use RuntimeException;

class OpenLigaDbBudgetService
{
    private const PROVIDER = 'openligadb';

    public function ensureCallAllowed(string $priority = 'normal'): void
    {
        $used = $this->callsUsedToday();
        $dailyLimit = $this->dailyLimit();
        $reserved = $this->reservedRequests();
        $normalLimit = max(0, $dailyLimit - $reserved);

        if ($priority === 'essential') {
            if ($used >= $dailyLimit) {
                throw new RuntimeException('Limite diario interno da OpenLigaDB atingido.');
            }

            return;
        }

        if ($used >= $normalLimit) {
            throw new RuntimeException('Limite normal da OpenLigaDB atingido; reserva operacional preservada.');
        }
    }

    public function startLog(string $operation, ?string $scope = null, string $priority = 'normal'): ApiSyncLog
    {
        return ApiSyncLog::create([
            'provider' => self::PROVIDER,
            'operation' => $operation,
            'scope' => $scope,
            'priority' => $priority,
            'status' => 'pending',
            'started_at' => now(),
        ]);
    }

    public function finishLog(ApiSyncLog $log, string $status, ?int $httpStatus = null, ?string $errorMessage = null): void
    {
        $startedAt = $log->started_at ? CarbonImmutable::parse($log->started_at) : null;

        $log->forceFill([
            'status' => $status,
            'http_status' => $httpStatus,
            'duration_ms' => $startedAt ? (int) round($startedAt->diffInMilliseconds(now())) : null,
            'error_message' => $errorMessage,
            'finished_at' => now(),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $used = $this->callsUsedToday();
        $today = CarbonImmutable::now($this->displayTimezone())->toDateString();

        return [
            'daily_limit' => $this->dailyLimit(),
            'reserved_requests' => $this->reservedRequests(),
            'calls_used_today' => $used,
            'normal_calls_remaining' => max(0, $this->dailyLimit() - $this->reservedRequests() - $used),
            'reserve_calls_remaining' => max(0, $this->dailyLimit() - $used),
            'date' => $today,
            'timezone' => $this->displayTimezone(),
        ];
    }

    private function callsUsedToday(): int
    {
        $today = CarbonImmutable::now($this->displayTimezone());
        $start = $today->startOfDay()->utc();
        $end = $today->endOfDay()->utc();

        return (int) ApiSyncLog::query()
            ->where('provider', self::PROVIDER)
            ->whereBetween('started_at', [$start, $end])
            ->sum('calls_count');
    }

    private function displayTimezone(): string
    {
        return (string) config('services.openligadb.display_timezone', 'America/Sao_Paulo');
    }

    private function dailyLimit(): int
    {
        return max(1, (int) config('services.openligadb.daily_limit', 80));
    }

    private function reservedRequests(): int
    {
        return max(0, (int) config('services.openligadb.reserved_requests', 10));
    }
}
