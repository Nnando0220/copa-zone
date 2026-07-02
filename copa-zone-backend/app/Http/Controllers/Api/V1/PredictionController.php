<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Prediction\RebuildLeagueRankingAction;
use App\Application\Services\WorldCup2026StructureService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prediction\PlacePredictionRequest;
use App\Http\Resources\LeagueRankingResource;
use App\Http\Resources\PredictionResource;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\Prediction;
use App\Models\WorldCupMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PredictionController extends Controller
{
    public function __construct(
        private readonly WorldCup2026StructureService $structure,
    ) {
    }

    public function index(Request $request, League $league): JsonResponse
    {
        $member = $this->activeMember($request, $league);

        $predictions = Prediction::query()
            ->with(['match.homeTeam', 'match.awayTeam', 'match.group'])
            ->where('league_id', $league->id)
            ->where('league_member_id', $member->id)
            ->latest('submitted_at')
            ->get();

        return response()->json([
            'data' => ['predictions' => PredictionResource::collection($predictions)],
            'meta' => ['total' => $predictions->count()],
            'message' => 'Palpites carregados com sucesso.',
        ]);
    }

    public function store(PlacePredictionRequest $request, League $league, WorldCupMatch $match): JsonResponse
    {
        $member = $this->activeMember($request, $league);
        $this->ensureMatchIsPredictable($league, $match);

        $prediction = Prediction::query()
            ->where('league_id', $league->id)
            ->where('league_member_id', $member->id)
            ->where('match_id', $match->id)
            ->first();

        if ($prediction && $prediction->status !== 'pending') {
            throw ValidationException::withMessages([
                'prediction' => 'Este palpite ja foi apurado e nao pode ser alterado.',
            ]);
        }

        $payload = $request->validated();
        $predictedWinnerSide = $this->predictedWinnerSide($match, $payload);
        $lockAt = $this->lockAt($league, $match);

        $prediction = Prediction::updateOrCreate(
            [
                'league_id' => $league->id,
                'league_member_id' => $member->id,
                'match_id' => $match->id,
            ],
            [
                'predicted_home_score' => $payload['predicted_home_score'],
                'predicted_away_score' => $payload['predicted_away_score'],
                'predicted_winner_side' => $predictedWinnerSide,
                'status' => 'pending',
                'submitted_at' => now(),
                'locked_at' => $lockAt,
                'points_awarded' => 0,
                'score_reason' => null,
                'prediction_version' => $prediction ? $prediction->prediction_version + 1 : 1,
            ],
        );

        return response()->json([
            'data' => ['prediction' => new PredictionResource($prediction->load(['match.homeTeam', 'match.awayTeam', 'match.group']))],
            'meta' => [],
            'message' => 'Palpite salvo com sucesso.',
        ], $prediction->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function predictedWinnerSide(WorldCupMatch $match, array $payload): ?string
    {
        $homeScore = (int) $payload['predicted_home_score'];
        $awayScore = (int) $payload['predicted_away_score'];

        if ($homeScore !== $awayScore || ! $this->structure->matchCanRequireWinnerPrediction($match)) {
            return null;
        }

        $winnerSide = $payload['predicted_winner_side'] ?? null;

        if (! in_array($winnerSide, ['home', 'away'], true)) {
            throw ValidationException::withMessages([
                'predicted_winner_side' => 'Escolha o vencedor caso o palpite do mata-mata termine empatado.',
            ]);
        }

        return $winnerSide;
    }

    public function destroy(Request $request, League $league, Prediction $prediction): JsonResponse
    {
        $member = $this->activeMember($request, $league);

        if ($prediction->league_id !== $league->id || $prediction->league_member_id !== $member->id) {
            abort(404);
        }

        $match = $prediction->match;

        if (! $league->settings?->allow_prediction_cancellation) {
            throw ValidationException::withMessages([
                'prediction' => 'Esta liga nao permite cancelamento de palpites.',
            ]);
        }

        if ($match && $this->lockAt($league, $match)->isPast()) {
            throw ValidationException::withMessages([
                'prediction' => 'Este palpite ja foi bloqueado.',
            ]);
        }

        $prediction->forceFill([
            'status' => 'cancelled',
            'points_awarded' => 0,
            'score_reason' => null,
        ])->save();

        return response()->json([
            'data' => ['prediction' => new PredictionResource($prediction)],
            'meta' => [],
            'message' => 'Palpite cancelado com sucesso.',
        ]);
    }

    public function ranking(Request $request, League $league, RebuildLeagueRankingAction $rebuildRanking): JsonResponse
    {
        $this->activeMember($request, $league);
        $rebuildRanking->execute($league, false);

        $ranking = $league->rankings()
            ->with(['leagueMember.user'])
            ->orderBy('position')
            ->orderByDesc('total_points')
            ->get();

        return response()->json([
            'data' => ['rankings' => LeagueRankingResource::collection($ranking)],
            'meta' => ['total' => $ranking->count()],
            'message' => 'Ranking carregado com sucesso.',
        ]);
    }

    private function activeMember(Request $request, League $league): LeagueMember
    {
        $member = $league->members()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if (! $member) {
            abort(404);
        }

        return $member;
    }

    private function ensureMatchIsPredictable(League $league, WorldCupMatch $match): void
    {
        if (! $this->structure->matchHasResolvedParticipants($match)) {
            throw ValidationException::withMessages([
                'match' => 'Esta partida ainda aguarda as selecoes classificadas.',
            ]);
        }

        if ($match->starts_at === null) {
            throw ValidationException::withMessages([
                'match' => 'Esta partida ainda nao possui horario definido.',
            ]);
        }

        if (in_array($match->status, ['finished', 'postponed', 'cancelled', 'unknown'], true)) {
            throw ValidationException::withMessages([
                'match' => 'Esta partida nao esta aberta para palpite.',
            ]);
        }

        if ($this->lockAt($league, $match)->isPast()) {
            throw ValidationException::withMessages([
                'match' => 'Esta partida ja foi bloqueada para novos palpites.',
            ]);
        }
    }

    private function lockAt(League $league, WorldCupMatch $match)
    {
        $minutes = (int) ($league->settings?->prediction_lock_minutes_before_start ?? 0);

        return $match->starts_at->copy()->subMinutes($minutes);
    }
}
