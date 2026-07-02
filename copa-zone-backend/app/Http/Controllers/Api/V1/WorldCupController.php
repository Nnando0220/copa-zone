<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\WorldCup2026StructureService;
use App\Application\Services\OpenLigaDbBudgetService;
use App\Http\Controllers\Controller;
use App\Http\Resources\TeamResource;
use App\Http\Resources\TournamentEditionResource;
use App\Http\Resources\WorldCupMatchResource;
use App\Models\League;
use App\Models\Team;
use App\Models\TournamentEdition;
use App\Models\WorldCupSyncState;
use App\Models\WorldCupMatch;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorldCupController extends Controller
{
    public function __construct(
        private readonly WorldCup2026StructureService $structure,
    ) {
    }

    public function show(): JsonResponse
    {
        $edition = $this->currentEditionQuery()->first();

        return response()->json([
            'data' => [
                'edition' => $edition ? new TournamentEditionResource($edition) : null,
            ],
            'meta' => $this->diagnosticMeta(),
            'message' => $edition
                ? 'Dados da Copa carregados com sucesso.'
                : 'Dados da Copa ainda não foram atualizados.',
        ]);
    }

    public function teams(): JsonResponse
    {
        $teams = Team::query()->orderBy('name')->get();

        return response()->json([
            'data' => ['teams' => TeamResource::collection($teams)],
            'meta' => ['total' => $teams->count()],
            'message' => 'Selecoes carregadas com sucesso.',
        ]);
    }

    public function groups(): JsonResponse
    {
        $groups = $this->structure->officialGroups();

        return response()->json([
            'data' => ['groups' => $groups],
            'meta' => ['total' => count($groups)],
            'message' => 'Grupos e fases carregados com sucesso.',
        ]);
    }

    public function bracket(): JsonResponse
    {
        $stages = $this->structure->officialBracket();

        return response()->json([
            'data' => [
                'bracket' => [
                    'stages' => $stages,
                ],
            ],
            'meta' => ['total' => count($stages)],
            'message' => 'Chaveamento da Copa carregado com sucesso.',
        ]);
    }

    public function matches(Request $request): JsonResponse
    {
        $matches = $this->matchesQuery($request)
            ->paginate(48);

        return response()->json([
            'data' => ['matches' => WorldCupMatchResource::collection($matches->items())],
            'meta' => [
                ...$this->diagnosticMeta(),
                'filters' => $this->filtersMeta($request),
                'current_page' => $matches->currentPage(),
                'last_page' => $matches->lastPage(),
                'total' => $matches->total(),
            ],
            'message' => 'Partidas carregadas com sucesso.',
        ]);
    }

    public function match(WorldCupMatch $match): JsonResponse
    {
        return response()->json([
            'data' => [
                'match' => new WorldCupMatchResource($match->load(['homeTeam', 'awayTeam', 'winnerTeam', 'group'])),
            ],
            'meta' => [],
            'message' => 'Partida carregada com sucesso.',
        ]);
    }

    public function syncStatus(OpenLigaDbBudgetService $budget): JsonResponse
    {
        $state = WorldCupSyncState::query()
            ->where('provider', 'openligadb')
            ->where('scope', 'world_cup')
            ->latest('updated_at')
            ->first();

        return response()->json([
            'data' => [
                'sync' => $state ? [
                    'status' => $state->status,
                    'last_started_at' => $state->last_started_at,
                    'last_finished_at' => $state->last_finished_at,
                    'last_changed_at' => $state->last_changed_at,
                    'next_attempt_at' => $state->next_attempt_at,
                    'last_error' => $state->last_error,
                ] : null,
                'budget' => $budget->status(),
            ],
            'meta' => $this->diagnosticMeta(),
            'message' => 'Status de sincronizacao carregado com sucesso.',
        ]);
    }

    public function leagueMatches(Request $request, League $league): JsonResponse
    {
        if (! $this->canViewLeague($request, $league)) {
            abort(404);
        }

        $matches = $this->matchesQuery($request)->paginate(48);

        return response()->json([
            'data' => ['matches' => WorldCupMatchResource::collection($matches->items())],
            'meta' => [
                ...$this->diagnosticMeta(),
                'filters' => $this->filtersMeta($request),
                'current_page' => $matches->currentPage(),
                'last_page' => $matches->lastPage(),
                'total' => $matches->total(),
            ],
            'message' => 'Partidas da liga carregadas com sucesso.',
        ]);
    }

    public function leagueBracket(Request $request, League $league): JsonResponse
    {
        if (! $this->canViewLeague($request, $league)) {
            abort(404);
        }

        $stages = $this->structure->officialBracket(predictionLockMinutesBeforeStart: $this->predictionLockMinutesBeforeStart($league));

        return response()->json([
            'data' => [
                'bracket' => [
                    'stages' => $stages,
                ],
            ],
            'meta' => ['total' => count($stages)],
            'message' => 'Chaveamento da Copa carregado com sucesso.',
        ]);
    }

    public function leagueWorldCup(Request $request, League $league): JsonResponse
    {
        if (! $this->canViewLeague($request, $league)) {
            abort(404);
        }

        $edition = $this->currentEditionQuery()->first();
        $nextMatches = $this->matchesQuery($request)
            ->whereIn('status', ['scheduled', 'in_progress_unconfirmed'])
            ->limit(6)
            ->get();

        return response()->json([
            'data' => [
                'edition' => $edition ? new TournamentEditionResource($edition) : null,
                'next_matches' => WorldCupMatchResource::collection($nextMatches),
            ],
            'meta' => $this->diagnosticMeta(),
            'message' => 'Dados da Copa da liga carregados com sucesso.',
        ]);
    }

    public function leagueStages(Request $request, League $league): JsonResponse
    {
        if (! $this->canViewLeague($request, $league)) {
            abort(404);
        }

        $groups = $this->structure->officialGroups(predictionLockMinutesBeforeStart: $this->predictionLockMinutesBeforeStart($league));

        return response()->json([
            'data' => ['stages' => $groups],
            'meta' => ['total' => count($groups)],
            'message' => 'Fases da Copa carregadas com sucesso.',
        ]);
    }

    public function leagueGroups(Request $request, League $league): JsonResponse
    {
        if (! $this->canViewLeague($request, $league)) {
            abort(404);
        }

        $groups = $this->structure->officialGroups(predictionLockMinutesBeforeStart: $this->predictionLockMinutesBeforeStart($league));

        return response()->json([
            'data' => ['groups' => $groups],
            'meta' => ['total' => count($groups)],
            'message' => 'Grupos oficiais da Copa carregados com sucesso.',
        ]);
    }

    private function currentEditionQuery()
    {
        return TournamentEdition::query()
            ->withCount([
                'groups as groups_count',
                'matches as matches_count',
            ])
            ->select('tournament_editions.*')
            ->latest('last_synced_at')
            ->latest();
    }

    private function matchesQuery(Request $request)
    {
        return WorldCupMatch::query()
            ->with(['homeTeam', 'awayTeam', 'winnerTeam', 'group'])
            ->tap(fn ($query) => $this->applyPeriodFilter($query, $request))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('date'), fn ($query, $date) => $query->whereDate('starts_at', $date))
            ->when($request->query('team_id'), fn ($query, $teamId) => $query
                ->where(fn ($teamQuery) => $teamQuery
                    ->where('home_team_id', $teamId)
                    ->orWhere('away_team_id', $teamId)))
            ->orderByRaw('starts_at is null')
            ->orderBy('starts_at')
            ->orderBy('round');
    }

    private function applyPeriodFilter($query, Request $request): void
    {
        $period = (string) $request->query('period', 'all');
        $now = CarbonImmutable::now();
        $startOfDay = $now->startOfDay();
        $endOfDay = $now->endOfDay();

        match ($period) {
            'current' => $query->where(fn ($currentQuery) => $currentQuery
                ->whereIn('status', ['live', 'in_progress_unconfirmed'])
                ->orWhereBetween('starts_at', [$startOfDay, $endOfDay])),
            'today' => $query->whereBetween('starts_at', [$startOfDay, $endOfDay]),
            'upcoming' => $query
                ->where('status', 'scheduled')
                ->where('starts_at', '>=', $now),
            'finished' => $query->where('status', 'finished'),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersMeta(Request $request): array
    {
        return [
            'period' => $request->query('period', 'all'),
            'status' => $request->query('status'),
            'date' => $request->query('date'),
            'team_id' => $request->query('team_id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnosticMeta(): array
    {
        $edition = TournamentEdition::query()->latest('last_synced_at')->latest()->first();

        return [
            'last_synced_at' => $edition?->last_synced_at,
            'teams_count' => Team::query()->count(),
            'groups_count' => count(config('world_cup_2026.groups', [])),
            'matches_count' => WorldCupMatch::query()->count(),
        ];
    }

    private function canViewLeague(Request $request, League $league): bool
    {
        if ($league->visibility === 'public' && $league->status === 'open') {
            return true;
        }

        return $league->members()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->exists();
    }

    private function predictionLockMinutesBeforeStart(League $league): int
    {
        return max(0, (int) ($league->loadMissing('settings')->settings?->prediction_lock_minutes_before_start ?? 0));
    }
}
