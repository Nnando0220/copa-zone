<?php

namespace App\Integrations\OpenLigaDb;

use App\Application\Services\OpenLigaDbBudgetService;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use Throwable;

class OpenLigaDbClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly OpenLigaDbBudgetService $budget,
    )
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availableLeagues(int|string $season, string $priority = 'normal'): array
    {
        return $this->get("/getavailableleagues/{$season}", 'available_leagues', "world_cup:{$season}", $priority);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function teams(string $shortcut, int|string $season, string $priority = 'normal'): array
    {
        return $this->get("/getavailableteams/{$shortcut}/{$season}", 'teams', "{$shortcut}:{$season}", $priority);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function groups(string $shortcut, int|string $season, string $priority = 'normal'): array
    {
        return $this->get("/getavailablegroups/{$shortcut}/{$season}", 'groups', "{$shortcut}:{$season}", $priority);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function matches(string $shortcut, int|string $season, string $priority = 'normal'): array
    {
        return $this->get("/getmatchdata/{$shortcut}/{$season}", 'matches', "{$shortcut}:{$season}", $priority);
    }

    /**
     * @return array<string, mixed>
     */
    public function lastChangeDate(string $shortcut, int|string $season, string $priority = 'normal'): array
    {
        $payload = $this->get("/getlastchangedate/{$shortcut}/{$season}", 'last_change_date', "{$shortcut}:{$season}", $priority);
        $value = $payload[0] ?? null;

        return is_array($value) ? $value : ['last_changed_at' => $value];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get(string $path, string $operation, ?string $scope, string $priority): array
    {
        $this->budget->ensureCallAllowed($priority);
        $log = $this->budget->startLog($operation, $scope, $priority);
        $baseUrl = rtrim((string) config('services.openligadb.base_url'), '/');

        try {
            $response = $this->http
                ->baseUrl($baseUrl)
                ->acceptJson()
                ->connectTimeout((int) config('services.openligadb.connect_timeout'))
                ->timeout((int) config('services.openligadb.timeout'))
                ->retry(2, 250)
                ->get($path);

            $this->budget->finishLog($log, $response->successful() ? 'success' : 'failed', $response->status());

            $payload = $response->throw()->json();
        } catch (Throwable $exception) {
            $this->budget->finishLog($log, 'failed', null, $exception->getMessage());

            throw $exception;
        }

        return Arr::wrap($payload);
    }
}
