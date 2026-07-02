<?php

namespace App\Application\Services;

use App\Http\Resources\TeamResource;
use App\Http\Resources\WorldCupMatchResource;
use App\Models\Team;
use App\Models\WorldCupMatch;
use App\Support\OpenLigaDbTranslationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WorldCup2026StructureService
{
    private const UNRESOLVED_TEAM_MARKERS = ['/', '\\', ' or ', ' ou ', 'winner ', 'vencedor ', 'loser ', 'perdedor '];

    private const TEAM_CODE_ALIASES = [
        'ale' => 'alemanha',
        'alg' => 'argelia',
        'arg' => 'argentina',
        'aus' => 'australia',
        'aut' => 'austria',
        'bel' => 'belgica',
        'bih' => 'bosnia e herzegovina',
        'bra' => 'brasil',
        'can' => 'canada',
        'col' => 'colombia',
        'cpv' => 'cabo verde',
        'cro' => 'croacia',
        'egy' => 'egito',
        'eng' => 'inglaterra',
        'esp' => 'espanha',
        'fra' => 'franca',
        'gha' => 'gana',
        'jpn' => 'japao',
        'mar' => 'marrocos',
        'mex' => 'mexico',
        'nor' => 'noruega',
        'par' => 'paraguai',
        'por' => 'portugal',
        'rdc' => 'rd congo',
        'sen' => 'senegal',
        'sui' => 'suica',
        'swe' => 'suecia',
        'usa' => 'estados unidos',
    ];

    private const KNOCKOUT_STAGES = [
        'round_of_32',
        'round_of_16',
        'quarterfinal',
        'semifinal',
        'third_place',
        'final',
    ];

    public function __construct(
        private readonly OpenLigaDbTranslationService $translator,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function officialGroups(?Collection $matches = null, int $predictionLockMinutesBeforeStart = 0): array
    {
        $groupMatches = ($matches ?? $this->groupStageMatches())
            ->values();
        $teamsByGroup = $this->teamsByGroup();
        $groupConfig = collect(config('world_cup_2026.groups', []));

        return $groupConfig
            ->map(function (array $teamLabels, string $code) use ($groupMatches, $teamsByGroup, $predictionLockMinutesBeforeStart): array {
                $matchesForGroup = $groupMatches
                    ->filter(fn (WorldCupMatch $match): bool => $this->groupCodeForMatch($match) === $code)
                    ->sortBy(fn (WorldCupMatch $match): string => sprintf(
                        '%d-%020d',
                        $match->starts_at === null ? 1 : 0,
                        $match->starts_at?->getTimestamp() ?? PHP_INT_MAX,
                    ))
                    ->values();

                $decoratedMatches = $matchesForGroup
                    ->map(fn (WorldCupMatch $match) => $this->decorateMatch($match, [
                        'group_code' => $code,
                        'slot_home_label' => $match->homeTeam ? null : 'Aguardando selecao',
                        'slot_away_label' => $match->awayTeam ? null : 'Aguardando selecao',
                    ], $predictionLockMinutesBeforeStart));

                return [
                    'code' => $code,
                    'display_name' => 'Grupo '.$code,
                    'sorting_criteria' => 'Pontos, saldo de gols, gols pro e nome.',
                    'standings' => $this->buildStandings($code, $teamLabels, $matchesForGroup, $teamsByGroup->get($code, collect())),
                    'matches' => WorldCupMatchResource::collection($decoratedMatches)->resolve(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function officialBracket(?Collection $matches = null, int $predictionLockMinutesBeforeStart = 0): array
    {
        $actualMatches = ($matches ?? $this->knockoutMatches())
            ->values();
        $stageConfig = collect(config('world_cup_2026.bracket', []));
        $pools = [];
        $resolvedWinners = [];
        $mappedStages = [];

        foreach ($stageConfig as $stageCode => $stage) {
            $templates = collect($stage['matches'] ?? []);
            $stageMatches = $actualMatches
                ->filter(fn (WorldCupMatch $match): bool => $this->stageCodeForMatch($match) === $stageCode)
                ->values();

            $usedMatchIds = [];
            $matches = $templates->map(function (array $template) use ($stageCode, $stage, $stageMatches, &$usedMatchIds, &$pools, &$resolvedWinners, $predictionLockMinutesBeforeStart): array {
                $pool = $this->poolForTemplate($stageCode, $template, $pools);
                $pools[$stageCode][$template['order']] = $pool;
                $resolvedTeams = $this->resolvedTeamsForTemplate($stageCode, $template, $resolvedWinners);
                $actualMatch = $this->findMatchForTemplate($stageMatches, $pool, $usedMatchIds);

                if ($actualMatch) {
                    $usedMatchIds[] = $actualMatch->id;
                }

                $slotHome = $this->displayNameForTeam($resolvedTeams['home'])
                    ?: $actualMatch?->homeTeam?->display_name_pt_br
                    ?: $actualMatch?->homeTeam?->name
                    ?: $template['home_label'];
                $slotAway = $this->displayNameForTeam($resolvedTeams['away'])
                    ?: $actualMatch?->awayTeam?->display_name_pt_br
                    ?: $actualMatch?->awayTeam?->name
                    ?: $template['away_label'];

                $decorated = $actualMatch
                    ? new WorldCupMatchResource($this->decorateMatch($actualMatch, [
                        'bracket_stage' => $stageCode,
                        'bracket_order' => $template['order'],
                        'slot_home_label' => $slotHome,
                        'slot_away_label' => $slotAway,
                        'resolved_home_team' => $resolvedTeams['home'],
                        'resolved_away_team' => $resolvedTeams['away'],
                    ], $predictionLockMinutesBeforeStart))->resolve()
                    : $this->placeholderMatch($stageCode, $stage, $template, $slotHome, $slotAway);

                $resolvedWinners[$stageCode][$template['order']] = $actualMatch
                    ? $this->winnerForMatch($actualMatch, $resolvedTeams)
                    : null;

                return $decorated;
            })->all();

            $mappedStages[] = [
                'code' => $stageCode,
                'display_name' => $stage['display_name'],
                'order' => $stage['order'],
                'match_count' => count($stage['matches'] ?? []),
                'matches' => $matches,
            ];
        }

        return $mappedStages;
    }

    public function matchHasResolvedParticipants(WorldCupMatch $target): bool
    {
        $target->loadMissing(['homeTeam', 'awayTeam', 'winnerTeam', 'group']);

        if ($this->hasResolvedTeamsForPrediction($target)) {
            return true;
        }

        if (! in_array($this->stageCodeForMatch($target), self::KNOCKOUT_STAGES, true)) {
            return false;
        }

        foreach ($this->officialBracket() as $stage) {
            foreach ($stage['matches'] ?? [] as $match) {
                if (($match['id'] ?? null) !== $target->id) {
                    continue;
                }

                return ($match['match_state'] ?? null) !== 'awaiting_teams'
                    && ! empty($match['home_team']['id'])
                    && ! empty($match['away_team']['id']);
            }
        }

        return false;
    }

    public function matchCanRequireWinnerPrediction(WorldCupMatch $match): bool
    {
        $match->loadMissing('group');

        return in_array($this->stageCodeForMatch($match), self::KNOCKOUT_STAGES, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function placeholderMatch(string $stageCode, array $stage, array $template, string $slotHome, string $slotAway): array
    {
        return [
            'id' => null,
            'provider_fixture_id' => null,
            'starts_at' => null,
            'starts_at_br' => null,
            'lock_at' => null,
            'timezone' => (string) config('services.openligadb.display_timezone', 'America/Sao_Paulo'),
            'provider_timezone' => null,
            'venue_name' => null,
            'round' => $stage['display_name'],
            'status' => 'scheduled',
            'status_label' => $this->translator->translateStatus('scheduled'),
            'match_state' => 'awaiting_teams',
            'match_state_label' => $this->translator->translateStatus('awaiting_teams'),
            'status_short' => null,
            'elapsed' => null,
            'home_score' => null,
            'away_score' => null,
            'home_penalty_score' => null,
            'away_penalty_score' => null,
            'winner_team_id' => null,
            'winner_side' => null,
            'winner_source' => null,
            'prediction_status' => 'locked',
            'can_predict' => false,
            'group_code' => null,
            'bracket_stage' => $stageCode,
            'bracket_order' => $template['order'],
            'slot_home_label' => $slotHome,
            'slot_away_label' => $slotAway,
            'group' => null,
            'home_team' => null,
            'away_team' => null,
        ];
    }

    private function findMatchForTemplate(Collection $matches, array $pool, array $usedMatchIds): ?WorldCupMatch
    {
        $remaining = $matches
            ->reject(fn (WorldCupMatch $match): bool => in_array($match->id, $usedMatchIds, true))
            ->values();

        $exact = $remaining->first(function (WorldCupMatch $match) use ($pool): bool {
            if (! $match->homeTeam && ! $match->awayTeam) {
                return false;
            }

            return (
                $this->teamMatchesPool($match->homeTeam, $pool['home'])
                && $this->teamMatchesPool($match->awayTeam, $pool['away'])
            ) || (
                $this->teamMatchesPool($match->homeTeam, $pool['away'])
                && $this->teamMatchesPool($match->awayTeam, $pool['home'])
            );
        });

        return $exact;
    }

    /**
     * @param  array<string, array<int, array{home: array<int, string>, away: array<int, string>}>>  $pools
     * @return array{home: array<int, string>, away: array<int, string>}
     */
    private function poolForTemplate(string $stageCode, array $template, array $pools): array
    {
        if ($stageCode === 'round_of_32') {
            return [
                'home' => [$this->normalizeLabel($template['home_label'])],
                'away' => [$this->normalizeLabel($template['away_label'])],
            ];
        }

        $previousStage = $this->previousStageFor($stageCode);
        $sourceMatches = $template['source_matches'] ?? [];
        $homePool = [];
        $awayPool = [];

        if (isset($sourceMatches[0], $pools[$previousStage][$sourceMatches[0]])) {
            $homePool = array_values(array_unique(array_merge(
                $pools[$previousStage][$sourceMatches[0]]['home'],
                $pools[$previousStage][$sourceMatches[0]]['away'],
            )));
        }

        if (isset($sourceMatches[1], $pools[$previousStage][$sourceMatches[1]])) {
            $awayPool = array_values(array_unique(array_merge(
                $pools[$previousStage][$sourceMatches[1]]['home'],
                $pools[$previousStage][$sourceMatches[1]]['away'],
            )));
        }

        return [
            'home' => $homePool,
            'away' => $awayPool,
        ];
    }

    private function previousStageFor(string $stageCode): string
    {
        return match ($stageCode) {
            'round_of_16' => 'round_of_32',
            'quarterfinal' => 'round_of_16',
            'semifinal' => 'quarterfinal',
            'third_place', 'final' => 'semifinal',
            default => 'round_of_32',
        };
    }

    /**
     * @param  array<string, array<int, Team|null>>  $resolvedWinners
     * @return array{home: Team|null, away: Team|null}
     */
    private function resolvedTeamsForTemplate(string $stageCode, array $template, array $resolvedWinners): array
    {
        if ($stageCode === 'round_of_32') {
            return ['home' => null, 'away' => null];
        }

        $previousStage = $this->previousStageFor($stageCode);
        $sourceMatches = $template['source_matches'] ?? [];

        return [
            'home' => isset($sourceMatches[0]) ? ($resolvedWinners[$previousStage][$sourceMatches[0]] ?? null) : null,
            'away' => isset($sourceMatches[1]) ? ($resolvedWinners[$previousStage][$sourceMatches[1]] ?? null) : null,
        ];
    }

    /**
     * @param  array{home: Team|null, away: Team|null}  $resolvedTeams
     */
    private function winnerForMatch(WorldCupMatch $match, array $resolvedTeams): ?Team
    {
        if ($match->winnerTeam) {
            return $match->winnerTeam;
        }

        if ($match->home_score === null || $match->away_score === null) {
            return null;
        }

        if ($match->home_score > $match->away_score) {
            return $resolvedTeams['home'] ?? $match->homeTeam;
        }

        if ($match->away_score > $match->home_score) {
            return $resolvedTeams['away'] ?? $match->awayTeam;
        }

        return null;
    }

    private function displayNameForTeam(?Team $team): ?string
    {
        if (! $team) {
            return null;
        }

        return $team->display_name_pt_br ?: $team->name;
    }

    /**
     * @param  array<int, string>  $teamLabels
     * @return array<int, array<string, mixed>>
     */
    private function buildStandings(string $groupCode, array $teamLabels, Collection $matches, Collection $groupTeams): array
    {
        $rows = collect($teamLabels)->mapWithKeys(function (string $teamLabel) use ($groupTeams): array {
            $team = $groupTeams->first(fn (Team $candidate): bool => $this->normalizeTeam($candidate) === $this->normalizeLabel($teamLabel));

            return [
                $this->normalizeLabel($teamLabel) => [
                    'team' => $team ? (new TeamResource($team))->resolve() : [
                        'id' => null,
                        'name' => $teamLabel,
                        'display_name' => $teamLabel,
                        'code' => Str::upper(Str::substr($this->normalizeLabel($teamLabel), 0, 3)),
                        'country' => null,
                        'country_code' => null,
                        'logo_url' => null,
                        'provider_team_id' => null,
                    ],
                    'played' => 0,
                    'won' => 0,
                    'drawn' => 0,
                    'lost' => 0,
                    'goals_for' => 0,
                    'goals_against' => 0,
                    'goal_difference' => 0,
                    'points' => 0,
                    'position' => null,
                ],
            ];
        })->all();

        $matches->each(function (WorldCupMatch $match) use (&$rows, $groupCode): void {
            if ($this->groupCodeForMatch($match) !== $groupCode) {
                return;
            }

            if ($match->home_score === null || $match->away_score === null || ! $match->homeTeam || ! $match->awayTeam) {
                return;
            }

            $homeKey = $this->normalizeTeam($match->homeTeam);
            $awayKey = $this->normalizeTeam($match->awayTeam);

            if ($homeKey === null || $awayKey === null || ! isset($rows[$homeKey], $rows[$awayKey])) {
                return;
            }

            $rows[$homeKey]['played']++;
            $rows[$awayKey]['played']++;
            $rows[$homeKey]['goals_for'] += $match->home_score;
            $rows[$homeKey]['goals_against'] += $match->away_score;
            $rows[$awayKey]['goals_for'] += $match->away_score;
            $rows[$awayKey]['goals_against'] += $match->home_score;

            if ($match->home_score > $match->away_score) {
                $rows[$homeKey]['won']++;
                $rows[$awayKey]['lost']++;
                $rows[$homeKey]['points'] += 3;
            } elseif ($match->home_score < $match->away_score) {
                $rows[$awayKey]['won']++;
                $rows[$homeKey]['lost']++;
                $rows[$awayKey]['points'] += 3;
            } else {
                $rows[$homeKey]['drawn']++;
                $rows[$awayKey]['drawn']++;
                $rows[$homeKey]['points']++;
                $rows[$awayKey]['points']++;
            }
        });

        $sorted = collect($rows)
            ->map(function (array $row): array {
                $row['goal_difference'] = $row['goals_for'] - $row['goals_against'];

                return $row;
            })
            ->sort(function (array $left, array $right): int {
                foreach (['points', 'goal_difference', 'goals_for'] as $metric) {
                    if ($left[$metric] !== $right[$metric]) {
                        return $right[$metric] <=> $left[$metric];
                    }
                }

                return strcmp(
                    Str::ascii($left['team']['display_name'] ?? $left['team']['name']),
                    Str::ascii($right['team']['display_name'] ?? $right['team']['name']),
                );
            })
            ->values()
            ->map(function (array $row, int $index): array {
                $row['position'] = $index + 1;

                return $row;
            });

        return $sorted->all();
    }

    private function stageCodeForMatch(WorldCupMatch $match): ?string
    {
        $roundStage = $match->round ? $this->translator->stageCode($match->round) : null;

        if ($roundStage !== null && $roundStage !== 'unknown_stage') {
            return $roundStage;
        }

        return $match->group?->internal_code;
    }

    private function groupCodeForMatch(WorldCupMatch $match): ?string
    {
        $homeGroup = $this->groupCodeForTeam($match->homeTeam);
        $awayGroup = $this->groupCodeForTeam($match->awayTeam);

        if ($homeGroup !== null && $homeGroup === $awayGroup) {
            return $homeGroup;
        }

        return null;
    }

    private function groupCodeForTeam(?Team $team): ?string
    {
        $normalized = $this->normalizeTeam($team);

        if ($normalized === null) {
            return null;
        }

        foreach (config('world_cup_2026.groups', []) as $code => $teams) {
            foreach ($teams as $teamLabel) {
                if ($this->normalizeLabel($teamLabel) === $normalized) {
                    return $code;
                }
            }
        }

        return null;
    }

    /**
     * @return Collection<string, Collection<int, Team>>
     */
    private function teamsByGroup(): Collection
    {
        $teams = Team::query()->get();

        return collect(config('world_cup_2026.groups', []))
            ->map(function (array $teamLabels) use ($teams): Collection {
                return $teams
                    ->filter(function (Team $team) use ($teamLabels): bool {
                        $normalizedTeam = $this->normalizeTeam($team);

                        if ($normalizedTeam === null) {
                            return false;
                        }

                        foreach ($teamLabels as $teamLabel) {
                            if ($this->normalizeLabel($teamLabel) === $normalizedTeam) {
                                return true;
                            }
                        }

                        return false;
                    })
                    ->values();
            });
    }

    /**
     * @return Collection<int, WorldCupMatch>
     */
    private function groupStageMatches(): Collection
    {
        return WorldCupMatch::query()
            ->with(['homeTeam', 'awayTeam', 'winnerTeam', 'group'])
            ->whereHas('group', fn ($query) => $query->where('internal_code', 'group_stage'))
            ->orderByRaw('starts_at is null')
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @return Collection<int, WorldCupMatch>
     */
    private function knockoutMatches(): Collection
    {
        return WorldCupMatch::query()
            ->with(['homeTeam', 'awayTeam', 'winnerTeam', 'group'])
            ->whereHas('group', fn ($query) => $query->whereIn('internal_code', self::KNOCKOUT_STAGES))
            ->orderByRaw('starts_at is null')
            ->orderBy('starts_at')
            ->get()
            ->unique(function (WorldCupMatch $match): string {
                $stage = $this->stageCodeForMatch($match) ?? 'unknown';
                $home = $this->normalizeTeam($match->homeTeam) ?? 'sem-mandante';
                $away = $this->normalizeTeam($match->awayTeam) ?? 'sem-visitante';

                return $stage.'|'.$home.'|'.$away;
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function decorateMatch(WorldCupMatch $match, array $context = [], int $predictionLockMinutesBeforeStart = 0): WorldCupMatch
    {
        foreach ($context as $key => $value) {
            $match->setAttribute($key, $value);
        }

        $match->setAttribute('group_code', $context['group_code'] ?? $this->groupCodeForMatch($match));
        $match->setAttribute('bracket_stage', $context['bracket_stage'] ?? $this->stageCodeForMatch($match));
        $match->setAttribute('bracket_order', $context['bracket_order'] ?? null);
        $match->setAttribute('match_state_override', $this->matchStateFor(
            $match,
            $this->hasResolvedTeamsForPrediction(
                $match,
                $context['resolved_home_team'] ?? null,
                $context['resolved_away_team'] ?? null,
            ),
        ));
        $match->setAttribute('can_predict', $this->canPredict(
            $match,
            $predictionLockMinutesBeforeStart,
            $context['resolved_home_team'] ?? null,
            $context['resolved_away_team'] ?? null,
        ));

        return $match;
    }

    private function canPredict(
        WorldCupMatch $match,
        int $predictionLockMinutesBeforeStart = 0,
        ?Team $resolvedHomeTeam = null,
        ?Team $resolvedAwayTeam = null,
    ): bool
    {
        if (! $this->hasResolvedTeamsForPrediction($match, $resolvedHomeTeam, $resolvedAwayTeam) || $match->starts_at === null) {
            return false;
        }

        if (in_array($match->status, ['finished', 'postponed', 'cancelled', 'unknown'], true)) {
            return false;
        }

        return $match->starts_at->copy()->subMinutes(max(0, $predictionLockMinutesBeforeStart))->isFuture();
    }

    private function matchStateFor(WorldCupMatch $match, bool $hasResolvedTeams): string
    {
        if (! $hasResolvedTeams) {
            return 'awaiting_teams';
        }

        if (in_array($match->status, ['finished', 'postponed', 'cancelled', 'unknown'], true)) {
            return $match->status;
        }

        if ($match->starts_at === null) {
            return 'unknown';
        }

        if ($match->starts_at->isPast()) {
            return $match->status === 'in_progress_unconfirmed' ? 'in_progress_unconfirmed' : 'locked';
        }

        return 'open_for_prediction';
    }

    private function hasResolvedTeamsForPrediction(
        WorldCupMatch $match,
        ?Team $resolvedHomeTeam = null,
        ?Team $resolvedAwayTeam = null,
    ): bool
    {
        return $this->teamIsResolvedForPrediction($resolvedHomeTeam ?? $match->homeTeam)
            && $this->teamIsResolvedForPrediction($resolvedAwayTeam ?? $match->awayTeam);
    }

    private function teamIsResolvedForPrediction(?Team $team): bool
    {
        if (! $team) {
            return false;
        }

        foreach ([
            $team->name,
            $team->external_name,
            $team->official_name,
            $team->display_name_pt_br,
            $team->country_code,
            $team->code,
        ] as $value) {
            $normalized = strtolower(trim((string) $value));

            if ($normalized === '') {
                continue;
            }

            foreach (self::UNRESOLVED_TEAM_MARKERS as $marker) {
                if (str_contains($normalized, $marker)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function normalizeTeam(?Team $team): ?string
    {
        if (! $team) {
            return null;
        }

        $candidates = [
            $team->display_name_pt_br,
            $team->official_name,
            $team->external_name,
            $team->name,
            $this->translator->translateTeam($team->name),
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeLabel($candidate);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $pool
     */
    private function teamMatchesPool(?Team $team, array $pool): bool
    {
        if (! $team || $pool === []) {
            return false;
        }

        return array_intersect($this->normalizeTeamCandidates($team), $pool) !== [];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeTeamCandidates(Team $team): array
    {
        $candidates = [
            $team->display_name_pt_br,
            $team->official_name,
            $team->external_name,
            $team->name,
            $team->code,
            $this->translator->translateTeam($team->name),
        ];

        $normalized = [];

        foreach ($candidates as $candidate) {
            $label = $this->normalizeLabel($candidate);

            if ($label !== null) {
                $normalized[] = $this->expandTeamAlias($label);
            }

            foreach (preg_split('/[^A-Za-z0-9]+/', (string) $candidate) ?: [] as $part) {
                $partLabel = $this->normalizeLabel($part);

                if ($partLabel !== null) {
                    $normalized[] = $this->expandTeamAlias($partLabel);
                }
            }
        }

        return array_values(array_unique(array_filter($normalized)));
    }

    private function expandTeamAlias(string $normalized): string
    {
        return self::TEAM_CODE_ALIASES[$normalized] ?? $normalized;
    }

    private function normalizeLabel(?string $value): ?string
    {
        $normalized = Str::of((string) $value)
            ->ascii()
            ->lower()
            ->squish()
            ->toString();

        return $normalized !== '' ? $normalized : null;
    }
}
