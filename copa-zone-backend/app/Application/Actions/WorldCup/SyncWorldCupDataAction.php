<?php

namespace App\Application\Actions\WorldCup;

use App\Events\WorldCupMatchFinished;
use App\Events\WorldCupMatchUpdated;
use App\Events\WorldCupStageUpdated;
use App\Integrations\OpenLigaDb\OpenLigaDbClient;
use App\Models\Team;
use App\Models\TournamentEdition;
use App\Models\TournamentGroup;
use App\Models\WorldCupSyncState;
use App\Models\WorldCupMatch;
use App\Support\OpenLigaDbTranslationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SyncWorldCupDataAction
{
    private const PROVIDER = 'openligadb';

    private const KNOCKOUT_STAGE_CODES = [
        'round_of_32',
        'round_of_16',
        'quarterfinal',
        'semifinal',
        'third_place',
        'final',
    ];

    public function __construct(
        private readonly OpenLigaDbClient $client,
        private readonly OpenLigaDbTranslationService $translator,
    )
    {
    }

    /**
     * @return array{edition: TournamentEdition|null, teams: int, groups: int, matches: int, status: string}
     */
    public function execute(?string $shortcut = null, ?int $season = null, bool $force = false, string $priority = 'normal', bool $matchesOnly = false): array
    {
        $shortcut ??= (string) config('services.openligadb.world_cup.shortcut');
        $season ??= (int) config('services.openligadb.world_cup.season');

        if ($shortcut === '') {
            throw new RuntimeException('OPENLIGADB_WORLD_CUP_SHORTCUT nao foi configurado.');
        }

        $state = $this->syncState($shortcut, $season);
        $state->forceFill([
            'status' => 'running',
            'last_started_at' => now(),
            'last_error' => null,
        ])->save();

        try {
            $leaguePayload = $matchesOnly ? [] : $this->client->availableLeagues($season, $priority);
            $teamsPayload = $matchesOnly ? [] : $this->client->teams($shortcut, $season, $priority);
            $groupsPayload = $matchesOnly ? [] : $this->client->groups($shortcut, $season, $priority);
            $matchesPayload = $this->client->matches($shortcut, $season, $priority);
        } catch (Throwable $exception) {
            $state->forceFill([
                'status' => 'failed',
                'last_finished_at' => now(),
                'next_attempt_at' => now()->addMinutes(10),
                'last_error' => $exception->getMessage(),
            ])->save();

            return $this->fallbackResult('failed');
        }

        return DB::transaction(function () use ($shortcut, $season, $leaguePayload, $teamsPayload, $groupsPayload, $matchesPayload, $state, $matchesOnly): array {
            $edition = $this->upsertEdition($shortcut, $season, $leaguePayload, $matchesPayload, $matchesOnly);
            $teams = $this->upsertTeams($teamsPayload, $matchesPayload);
            $groups = $this->upsertGroups($edition, $groupsPayload, $matchesPayload);
            $matchesCount = $this->upsertMatches($edition, $matchesPayload, $teams, $groups);
            $this->removeObsoleteGroups($edition);
            $payloadChangedAt = $this->payloadChangedAt($matchesPayload);

            $edition->forceFill([
                'status' => 'synced',
                'last_synced_at' => now(),
            ])->save();

            $state->forceFill([
                'status' => 'synced',
                'last_finished_at' => now(),
                'last_changed_at' => $payloadChangedAt ?? now(),
                'next_attempt_at' => now()->addMinutes(10),
                'last_error' => null,
            ])->save();

            return [
                'edition' => $edition->refresh(),
                'teams' => count($teams),
                'groups' => count($groups),
                'matches' => $matchesCount,
                'status' => 'synced',
            ];
        });
    }

    private function syncState(string $shortcut, int $season): WorldCupSyncState
    {
        return WorldCupSyncState::firstOrCreate(
            [
                'provider' => self::PROVIDER,
                'scope' => 'world_cup',
                'shortcut' => $shortcut,
                'season' => $season,
            ],
            ['status' => 'idle'],
        );
    }

    /**
     * @param array<int, array<string, mixed>> $matchesPayload
     */
    private function payloadChangedAt(array $matchesPayload): ?CarbonImmutable
    {
        return collect($matchesPayload)
            ->map(function (array $match): ?CarbonImmutable {
                $value = data_get($match, 'lastUpdateDateTime')
                    ?? data_get($match, 'lastUpdateDateTimeUtc')
                    ?? data_get($match, 'lastChangedAt');

                if (! is_string($value) || trim($value) === '') {
                    return null;
                }

                try {
                    return CarbonImmutable::parse($value, (string) config('services.openligadb.source_timezone', 'Europe/Berlin'))->utc();
                } catch (Throwable) {
                    return null;
                }
            })
            ->filter()
            ->sort()
            ->last();
    }

    /**
     * @return array{edition: TournamentEdition|null, teams: int, groups: int, matches: int, status: string}
     */
    private function fallbackResult(string $status): array
    {
        $edition = TournamentEdition::query()->latest('last_synced_at')->latest()->first();

        return [
            'edition' => $edition,
            'teams' => Team::query()->count(),
            'groups' => TournamentGroup::query()->count(),
            'matches' => WorldCupMatch::query()->count(),
            'status' => $status,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $leaguePayload
     * @param array<int, array<string, mixed>> $matchesPayload
     */
    private function upsertEdition(string $shortcut, int $season, array $leaguePayload, array $matchesPayload, bool $matchesOnly = false): TournamentEdition
    {
        if ($matchesOnly) {
            $existing = TournamentEdition::query()
                ->where('provider', self::PROVIDER)
                ->where('provider_league_id', $shortcut)
                ->where('season', $season)
                ->latest('last_synced_at')
                ->latest()
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $league = collect($leaguePayload)->first(
            fn (array $item): bool => strcasecmp((string) data_get($item, 'leagueShortcut'), $shortcut) === 0,
        );
        $firstMatch = $matchesPayload[0] ?? [];
        $name = data_get($league, 'leagueName') ?: data_get($firstMatch, 'leagueName') ?: 'Copa do Mundo';

        return TournamentEdition::updateOrCreate(
            [
                'provider' => self::PROVIDER,
                'provider_league_id' => $shortcut,
                'season' => $season,
            ],
            [
                'name' => $name,
                'status' => 'syncing',
            ],
        );
    }

    /**
     * @param array<int, array<string, mixed>> $teamsPayload
     * @param array<int, array<string, mixed>> $matchesPayload
     * @return array<string, Team>
     */
    private function upsertTeams(array $teamsPayload, array $matchesPayload): array
    {
        $teams = [];

        foreach ($this->collectTeams($teamsPayload, $matchesPayload) as $item) {
            $providerTeamId = (string) data_get($item, 'teamId');

            if ($providerTeamId === '') {
                continue;
            }

            $teamName = data_get($item, 'teamName');
            $shortName = data_get($item, 'shortName');
            $logoUrl = data_get($item, 'teamIconUrl');
            $team = Team::firstOrNew([
                'provider' => self::PROVIDER,
                'provider_team_id' => $providerTeamId,
            ]);

            $translatedName = $teamName
                ? $this->translator->translateTeam($teamName)
                : ($team->name ?: 'Selecao '.$providerTeamId);

            $team->forceFill([
                'name' => $translatedName,
                'external_name' => $teamName ?: $team->external_name,
                'official_name' => $teamName ?: $team->official_name,
                'display_name_pt_br' => $teamName ? $this->translator->translateTeam($teamName) : ($team->display_name_pt_br ?: $translatedName),
                'country_code' => $shortName ?: $team->country_code,
                'code' => $shortName ?: $team->code,
                'country' => $teamName ?: $team->country,
                'logo_url' => $logoUrl ?: $team->logo_url,
            ])->save();

            $teams[$providerTeamId] = $team;
        }

        return $teams;
    }

    /**
     * @param array<int, array<string, mixed>> $groupsPayload
     * @param array<int, array<string, mixed>> $matchesPayload
     * @return array<string, TournamentGroup>
     */
    private function upsertGroups(TournamentEdition $edition, array $groupsPayload, array $matchesPayload): array
    {
        $groups = [];

        foreach ($this->collectGroups($groupsPayload, $matchesPayload) as $item) {
            $name = trim((string) data_get($item, 'groupName'));

            if ($name === '') {
                continue;
            }

            $displayName = $this->translator->translateStage($name);
            $internalCode = $this->translator->stageCode($name);
            $group = TournamentGroup::query()
                ->where('tournament_edition_id', $edition->id)
                ->where(fn ($query) => $query
                    ->where('name', $name)
                    ->orWhere('name', $displayName)
                    ->orWhere('external_name', $name))
                ->first();

            if (! $group) {
                $group = new TournamentGroup(['tournament_edition_id' => $edition->id]);
            }

            $group->forceFill([
                'name' => $displayName,
                'external_name' => $name,
                'internal_code' => $internalCode,
                'display_name' => $displayName,
                'locale' => 'pt-BR',
                'translation_status' => $internalCode === 'unknown_stage' ? 'pending' : 'automatic',
            ])->save();

            if ($group->wasRecentlyCreated || $group->wasChanged(['name', 'external_name', 'internal_code', 'display_name', 'translation_status'])) {
                WorldCupStageUpdated::dispatch($group->refresh());
            }

            $groups[$name] = $group;
        }

        return $groups;
    }

    /**
     * @param array<int, array<string, mixed>> $matchesPayload
     * @param array<string, Team> $teams
     * @param array<string, TournamentGroup> $groups
     */
    private function upsertMatches(TournamentEdition $edition, array $matchesPayload, array $teams, array $groups): int
    {
        $count = 0;

        foreach ($matchesPayload as $item) {
            $providerFixtureId = (string) data_get($item, 'matchID');

            if ($providerFixtureId === '') {
                continue;
            }

            $homeProviderId = (string) data_get($item, 'team1.teamId');
            $awayProviderId = (string) data_get($item, 'team2.teamId');
            $round = trim((string) data_get($item, 'group.groupName'));
            $regulationResult = $this->regulationResult($item);
            $finalResult = $this->finalResult($item, $regulationResult);
            $penaltyResult = $this->penaltyResult($item, $finalResult);
            $decidingResult = $this->decidingResult($item, $finalResult, $penaltyResult);
            $isFinished = data_get($item, 'matchIsFinished') === true;
            $winnerProviderId = $isFinished ? $this->winnerProviderId($decidingResult, $homeProviderId, $awayProviderId) : null;

            $match = WorldCupMatch::updateOrCreate(
                [
                    'provider' => self::PROVIDER,
                    'provider_fixture_id' => $providerFixtureId,
                ],
                [
                    'tournament_edition_id' => $edition->id,
                    'tournament_group_id' => $groups[$round]->id ?? null,
                    'home_team_id' => $teams[$homeProviderId]->id ?? null,
                    'away_team_id' => $teams[$awayProviderId]->id ?? null,
                    'winner_team_id' => $winnerProviderId !== null ? ($teams[$winnerProviderId]->id ?? null) : null,
                    'starts_at' => $this->parseDate(data_get($item, 'matchDateTime')),
                    'timezone' => data_get($item, 'timeZoneID'),
                    'venue_name' => null,
                    'round' => $round ?: null,
                    'status' => $this->mapStatus($item),
                    'status_short' => $isFinished ? 'finished' : 'open',
                    'elapsed' => null,
                    'home_score' => $isFinished ? data_get($finalResult, 'pointsTeam1') : null,
                    'away_score' => $isFinished ? data_get($finalResult, 'pointsTeam2') : null,
                    'home_penalty_score' => $isFinished ? data_get($penaltyResult, 'pointsTeam1') : null,
                    'away_penalty_score' => $isFinished ? data_get($penaltyResult, 'pointsTeam2') : null,
                    'winner_source' => $isFinished ? $this->winnerSource($item, $regulationResult, $finalResult, $decidingResult, $penaltyResult) : null,
                ],
            );

            if ($match->wasRecentlyCreated || $match->wasChanged([
                'tournament_group_id',
                'home_team_id',
                'away_team_id',
                'winner_team_id',
                'starts_at',
                'status',
                'home_score',
                'away_score',
                'home_penalty_score',
                'away_penalty_score',
                'winner_source',
            ])) {
                WorldCupMatchUpdated::dispatch($match->refresh());

                if ($match->status === 'finished') {
                    WorldCupMatchFinished::dispatch($match);
                }
            }

            $count++;
        }

        return $count;
    }

    private function removeObsoleteGroups(TournamentEdition $edition): void
    {
        TournamentGroup::query()
            ->where('tournament_edition_id', $edition->id)
            ->whereDoesntHave('matches')
            ->where(fn ($query) => $query
                ->whereNull('external_name')
                ->orWhereNull('internal_code')
                ->orWhereNull('display_name')
                ->orWhere('translation_status', 'pending'))
            ->delete();
    }

    /**
     * @param array<int, array<string, mixed>> $teamsPayload
     * @param array<int, array<string, mixed>> $matchesPayload
     * @return array<int, array<string, mixed>>
     */
    private function collectTeams(array $teamsPayload, array $matchesPayload): array
    {
        $teams = [];

        foreach ($teamsPayload as $team) {
            $teamId = (string) data_get($team, 'teamId');

            if ($teamId !== '') {
                $teams[$teamId] = $team;
            }
        }

        foreach ($matchesPayload as $match) {
            foreach (['team1', 'team2'] as $key) {
                $team = data_get($match, $key);
                $teamId = (string) data_get($team, 'teamId');

                if ($teamId !== '') {
                    $teams[$teamId] = $team;
                }
            }
        }

        return array_values($teams);
    }

    /**
     * @param array<int, array<string, mixed>> $groupsPayload
     * @param array<int, array<string, mixed>> $matchesPayload
     * @return array<int, array<string, mixed>>
     */
    private function collectGroups(array $groupsPayload, array $matchesPayload): array
    {
        $groups = [];

        foreach ($groupsPayload as $group) {
            $name = trim((string) data_get($group, 'groupName'));

            if ($name !== '') {
                $groups[$name] = $group;
            }
        }

        foreach ($matchesPayload as $match) {
            $group = data_get($match, 'group');
            $name = trim((string) data_get($group, 'groupName'));

            if ($name !== '') {
                $groups[$name] = $group;
            }
        }

        return array_values($groups);
    }

    /**
     * @param array<string, mixed> $match
     * @return array<string, mixed>|null
     */
    private function regulationResult(array $match): ?array
    {
        $results = $this->matchResults($match);
        $nonPenaltyResults = $results->reject(fn (array $result): bool => $this->isPenaltyResult($result));
        $finalResults = $nonPenaltyResults
            ->filter(fn (array $result): bool => (int) data_get($result, 'resultTypeID') === 2);

        return $finalResults
            ->sortBy(fn (array $result): int => (int) data_get($result, 'resultOrderID'))
            ->first()
            ?? $nonPenaltyResults
                ->sortByDesc(fn (array $result): int => (int) data_get($result, 'resultOrderID'))
                ->first()
            ?? $results
                ->sortByDesc(fn (array $result): int => (int) data_get($result, 'resultOrderID'))
                ->first();
    }

    /**
     * @param array<string, mixed> $match
     * @param array<string, mixed>|null $regulationResult
     * @return array<string, mixed>|null
     */
    private function finalResult(array $match, ?array $regulationResult): ?array
    {
        $results = $this->matchResults($match);
        $nonPenaltyResults = $results->reject(fn (array $result): bool => $this->isPenaltyResult($result));
        $finalResults = $nonPenaltyResults
            ->filter(fn (array $result): bool => (int) data_get($result, 'resultTypeID') === 2);
        $extraTimeResult = $finalResults
            ->filter(fn (array $result): bool => $this->isExtraTimeResult($result))
            ->sortByDesc(fn (array $result): int => (int) data_get($result, 'resultOrderID'))
            ->first();

        $resolvedFinalResult = $extraTimeResult
            ?? $regulationResult
            ?? $nonPenaltyResults
                ->sortByDesc(fn (array $result): int => (int) data_get($result, 'resultOrderID'))
                ->first()
            ?? $results
                ->sortByDesc(fn (array $result): int => (int) data_get($result, 'resultOrderID'))
                ->first();

        if (! $this->isKnockoutMatchPayload($match) || ! $this->hasEqualScores($resolvedFinalResult)) {
            return $resolvedFinalResult;
        }

        return $nonPenaltyResults
            ->sortByDesc(fn (array $result): int => (int) data_get($result, 'resultOrderID'))
            ->first(function (array $result) use ($resolvedFinalResult): bool {
                return $this->hasDifferentScores($result)
                    && (int) data_get($result, 'resultOrderID') > (int) data_get($resolvedFinalResult, 'resultOrderID');
            })
            ?? $resolvedFinalResult;
    }

    /**
     * @param array<string, mixed>|null $finalResult
     * @param array<string, mixed>|null $penaltyResult
     * @return array<string, mixed>|null
     */
    private function decidingResult(array $match, ?array $finalResult, ?array $penaltyResult): ?array
    {
        if ($this->hasDifferentScores($penaltyResult)) {
            return $penaltyResult;
        }

        if ($this->hasDifferentScores($finalResult)) {
            return $finalResult;
        }

        if (! $this->isKnockoutMatchPayload($match)) {
            return null;
        }

        return $this->matchResults($match)
            ->sortByDesc(fn (array $result): int => (int) data_get($result, 'resultOrderID'))
            ->first(fn (array $result): bool => $this->hasDifferentScores($result));
    }

    /**
     * @param array<string, mixed>|null $finalResult
     * @return array<string, mixed>|null
     */
    private function penaltyResult(array $match, ?array $finalResult): ?array
    {
        if (! $this->isKnockoutMatchPayload($match)) {
            return null;
        }

        $results = $this->matchResults($match);
        $namedPenalty = $results
            ->filter(fn (array $result): bool => $this->isPenaltyResult($result))
            ->sortByDesc(fn (array $result): int => (int) data_get($result, 'resultOrderID'))
            ->first();

        if ($namedPenalty !== null) {
            return $namedPenalty;
        }

        if (! $this->hasEqualScores($finalResult)) {
            return null;
        }

        return $results
            ->sortByDesc(fn (array $result): int => (int) data_get($result, 'resultOrderID'))
            ->first(fn (array $result): bool => $this->hasDifferentScores($result) && $result !== $finalResult);
    }

    /**
     * @param array<string, mixed>|null $result
     */
    private function winnerProviderId(?array $result, string $homeProviderId, string $awayProviderId): ?string
    {
        if (! $this->hasDifferentScores($result)) {
            return null;
        }

        return (int) data_get($result, 'pointsTeam1') > (int) data_get($result, 'pointsTeam2')
            ? $homeProviderId
            : $awayProviderId;
    }

    /**
     * @param array<string, mixed>|null $finalResult
     * @param array<string, mixed>|null $decidingResult
     * @param array<string, mixed>|null $penaltyResult
     */
    private function winnerSource(array $match, ?array $regulationResult, ?array $finalResult, ?array $decidingResult, ?array $penaltyResult): ?string
    {
        if ($decidingResult === null) {
            return null;
        }

        if ($penaltyResult !== null && $this->sameResult($decidingResult, $penaltyResult)) {
            return 'penalties';
        }

        if ($this->isExtraTimeResult($decidingResult)) {
            return 'extra_time';
        }

        if (
            $this->isKnockoutMatchPayload($match)
            && $this->hasEqualScores($regulationResult)
            && $finalResult !== null
            && $this->sameResult($decidingResult, $finalResult)
            && ! $this->sameResult($finalResult, $regulationResult)
        ) {
            return 'extra_time';
        }

        return $this->hasEqualScores($finalResult) ? 'tiebreaker' : 'score';
    }

    /**
     * @param array<string, mixed> $match
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function matchResults(array $match)
    {
        return collect(data_get($match, 'matchResults', []))
            ->filter(fn (mixed $result): bool => is_array($result))
            ->values();
    }

    /**
     * @param array<string, mixed> $result
     */
    private function isPenaltyResult(array $result): bool
    {
        $name = $this->normalizedResultName($result);

        return str_contains($name, 'penalt')
            || str_contains($name, 'elfmeter')
            || str_contains($name, 'i.e')
            || str_contains($name, 'shootout')
            || str_contains($name, 'pen.')
            || str_contains($name, 'pens')
            || str_contains($name, 'n.e');
    }

    /**
     * @param array<string, mixed> $result
     */
    private function isExtraTimeResult(array $result): bool
    {
        $name = $this->normalizedResultName($result);

        return str_contains($name, 'verlanger')
            || str_contains($name, 'extra time')
            || str_contains($name, 'after extra')
            || str_contains($name, 'prorrog')
            || str_contains($name, 'aet')
            || str_contains($name, 'n.v');
    }

    /**
     * @param array<string, mixed> $result
     */
    private function normalizedResultName(array $result): string
    {
        return Str::of((string) data_get($result, 'resultName'))
            ->ascii()
            ->lower()
            ->toString();
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function sameResult(array $left, array $right): bool
    {
        return data_get($left, 'resultID') === data_get($right, 'resultID')
            && data_get($left, 'resultOrderID') === data_get($right, 'resultOrderID')
            && data_get($left, 'resultName') === data_get($right, 'resultName')
            && data_get($left, 'pointsTeam1') === data_get($right, 'pointsTeam1')
            && data_get($left, 'pointsTeam2') === data_get($right, 'pointsTeam2');
    }

    /**
     * @param array<string, mixed> $match
     */
    private function isKnockoutMatchPayload(array $match): bool
    {
        $round = trim((string) data_get($match, 'group.groupName'));

        return in_array($this->translator->stageCode($round), self::KNOCKOUT_STAGE_CODES, true);
    }

    /**
     * @param array<string, mixed>|null $result
     */
    private function hasDifferentScores(?array $result): bool
    {
        return $result !== null
            && data_get($result, 'pointsTeam1') !== null
            && data_get($result, 'pointsTeam2') !== null
            && (int) data_get($result, 'pointsTeam1') !== (int) data_get($result, 'pointsTeam2');
    }

    /**
     * @param array<string, mixed>|null $result
     */
    private function hasEqualScores(?array $result): bool
    {
        return $result !== null
            && data_get($result, 'pointsTeam1') !== null
            && data_get($result, 'pointsTeam2') !== null
            && (int) data_get($result, 'pointsTeam1') === (int) data_get($result, 'pointsTeam2');
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value, (string) config('services.openligadb.source_timezone', 'Europe/Berlin'))
            ->utc();
    }

    /**
     * @param array<string, mixed> $match
     */
    private function mapStatus(array $match): string
    {
        if (data_get($match, 'matchIsFinished') === true) {
            return 'finished';
        }

        $startsAt = $this->parseDate(data_get($match, 'matchDateTime'));

        if ($startsAt === null) {
            return 'unknown';
        }

        return $startsAt->isFuture() ? 'scheduled' : 'in_progress_unconfirmed';
    }
}
