<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\League\CreateLeagueAction;
use App\Application\Actions\League\JoinLeagueByCodeAction;
use App\Application\Actions\League\JoinPublicLeagueAction;
use App\Application\Actions\League\PreviewLeagueByCodeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\League\CreateLeagueRequest;
use App\Http\Requests\League\JoinLeagueByCodeRequest;
use App\Http\Resources\LeagueResource;
use App\Models\League;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeagueController extends Controller
{
    public function store(CreateLeagueRequest $request, CreateLeagueAction $action): JsonResponse
    {
        $league = $action->execute($request->user(), $request->validated());

        return response()->json([
            'data' => ['league' => new LeagueResource($this->leagueForResource($league->id))],
            'meta' => [],
            'message' => 'Liga criada com sucesso.',
        ], 201);
    }

    public function show(Request $request, League $league): JsonResponse
    {
        if (! $this->canViewLeague($request, $league)) {
            abort(404);
        }

        return response()->json([
            'data' => ['league' => new LeagueResource($this->leagueForResource($league->id))],
            'meta' => [],
            'message' => 'Liga carregada com sucesso.',
        ]);
    }

    public function join(Request $request, League $league, JoinPublicLeagueAction $action): JsonResponse
    {
        $joinedLeague = $action->execute($request->user(), $league);

        return response()->json([
            'data' => ['league' => new LeagueResource($this->leagueForResource($joinedLeague->id))],
            'meta' => [],
            'message' => 'Entrada na liga realizada com sucesso.',
        ]);
    }

    public function leave(Request $request, League $league): JsonResponse
    {
        $membership = $league->members()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            abort(404);
        }

        if ($membership->role === 'owner') {
            return response()->json([
                'data' => ['league' => new LeagueResource($this->leagueForResource($league->id))],
                'meta' => [],
                'message' => 'O dono da liga nao pode sair da propria liga.',
            ], 422);
        }

        $membership->forceFill(['status' => 'left'])->save();

        return response()->json([
            'data' => ['league' => new LeagueResource($this->leagueForResource($league->id))],
            'meta' => [],
            'message' => 'Voce saiu da liga com sucesso.',
        ]);
    }

    public function joinByCode(JoinLeagueByCodeRequest $request, JoinLeagueByCodeAction $action): JsonResponse
    {
        $league = $action->execute($request->user(), $request->validated('invite_code'));

        return response()->json([
            'data' => ['league' => new LeagueResource($this->leagueForResource($league->id))],
            'meta' => [],
            'message' => 'Entrada por codigo realizada com sucesso.',
        ]);
    }

    public function previewByCode(JoinLeagueByCodeRequest $request, PreviewLeagueByCodeAction $action): JsonResponse
    {
        $preview = $action->execute($request->user(), $request->validated('invite_code'));
        $league = $preview['league'];

        return response()->json([
            'data' => ['league' => new LeagueResource($this->leagueForResource($league->id))],
            'meta' => [
                'already_member' => $preview['already_member'],
            ],
            'message' => $preview['already_member']
                ? 'Voce ja faz parte dessa liga.'
                : 'Liga encontrada com sucesso.',
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $myLeagues = $this->baseLeagueQuery()
            ->whereHas('members', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('status', 'active'))
            ->latest()
            ->limit(6)
            ->get();

        $publicLeagues = $this->baseLeagueQuery()
            ->where('visibility', 'public')
            ->where('status', 'open')
            ->whereDoesntHave('members', fn ($query) => $query->where('user_id', $user->id))
            ->latest()
            ->limit(4)
            ->get();

        $privateCount = $myLeagues->where('visibility', 'private')->count();

        return response()->json([
            'data' => [
                'summary' => [
                    'my_leagues_count' => $myLeagues->count(),
                    'private_leagues_count' => $privateCount,
                    'public_leagues_available_count' => $publicLeagues->count(),
                    'activity_label' => $myLeagues->isEmpty()
                        ? 'Voce ainda nao participa de nenhuma liga.'
                        : 'Suas ligas estao prontas para acompanhar a Copa.',
                ],
                'my_leagues' => LeagueResource::collection($myLeagues),
                'public_leagues' => LeagueResource::collection($publicLeagues),
                'activity' => $this->activityFor($myLeagues),
            ],
            'meta' => [],
            'message' => 'Dashboard carregado com sucesso.',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $leagues = $this->baseLeagueQuery()
            ->whereHas('members', fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->where('status', 'active'))
            ->latest()
            ->paginate(12);

        return response()->json([
            'data' => LeagueResource::collection($leagues->items()),
            'meta' => [
                'current_page' => $leagues->currentPage(),
                'last_page' => $leagues->lastPage(),
                'total' => $leagues->total(),
            ],
            'message' => 'Ligas do usuario carregadas com sucesso.',
        ]);
    }

    public function publicLeagues(Request $request): JsonResponse
    {
        $leagues = $this->baseLeagueQuery()
            ->where('visibility', 'public')
            ->where('status', 'open')
            ->latest()
            ->paginate(12);

        return response()->json([
            'data' => LeagueResource::collection($leagues->items()),
            'meta' => [
                'current_page' => $leagues->currentPage(),
                'last_page' => $leagues->lastPage(),
                'total' => $leagues->total(),
            ],
            'message' => 'Ligas publicas carregadas com sucesso.',
        ]);
    }

    private function baseLeagueQuery()
    {
        return League::query()
            ->withCount(['activeMembers as active_members_count'])
            ->with([
                'members' => fn ($query) => $query->where('status', 'active'),
                'owner',
                'settings',
            ])
            ->select('leagues.*');
    }

    private function leagueForResource(string $leagueId): League
    {
        return $this->baseLeagueQuery()->findOrFail($leagueId);
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

    private function activityFor($leagues): array
    {
        if ($leagues->isEmpty()) {
            return [[
                'type' => 'empty',
                'title' => 'Entre ou crie uma liga',
                'description' => 'Ligas publicas ficam abertas para descoberta. Ligas privadas aparecem aqui somente quando voce participa.',
            ]];
        }

        return $leagues->map(fn (League $league) => [
            'type' => 'league_status',
            'title' => $league->name,
            'description' => $league->status === 'open'
                ? 'Liga aberta para participantes e preparacao dos palpites.'
                : 'Liga em acompanhamento.',
        ])->values()->all();
    }
}
