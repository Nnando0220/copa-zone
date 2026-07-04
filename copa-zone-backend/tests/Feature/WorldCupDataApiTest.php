<?php

namespace Tests\Feature;

use App\Application\Services\WorldCupSyncWindowService;
use App\Events\WorldCupMatchUpdated;
use App\Models\ApiSyncLog;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\Team;
use App\Models\TournamentEdition;
use App\Models\TournamentGroup;
use App\Models\WorldCupMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WorldCupDataApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_world_cup_sync_imports_teams_groups_and_matches_idempotently(): void
    {
        config()->set('services.openligadb.world_cup.shortcut', 'wm26');
        config()->set('services.openligadb.world_cup.season', 2026);

        Http::fake($this->openLigaDbResponses());

        $this->artisan('world-cup:sync')
            ->assertSuccessful();

        Http::fake($this->openLigaDbResponses());

        $this->artisan('world-cup:sync')
            ->assertSuccessful();

        $this->assertDatabaseCount('tournament_editions', 1);
        $this->assertDatabaseCount('teams', 2);
        $this->assertDatabaseCount('tournament_groups', 1);
        $this->assertDatabaseCount('matches', 1);
        $this->assertDatabaseHas('matches', [
            'provider_fixture_id' => '9001',
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
        ]);
    }

    public function test_world_cup_sync_records_status_budget_and_dispatches_events(): void
    {
        config()->set('services.openligadb.world_cup.shortcut', 'wm26');
        config()->set('services.openligadb.world_cup.season', 2026);
        Event::fake([WorldCupMatchUpdated::class]);
        Http::fake($this->openLigaDbResponses());

        $this->artisan('world-cup:sync')
            ->assertSuccessful();

        $this->assertDatabaseHas('world_cup_sync_states', [
            'provider' => 'openligadb',
            'scope' => 'world_cup',
            'status' => 'synced',
            'last_changed_at' => '2026-07-01 15:30:00',
        ]);
        $this->assertDatabaseHas('api_sync_logs', [
            'provider' => 'openligadb',
            'operation' => 'matches',
            'status' => 'success',
        ]);
        Event::assertDispatched(WorldCupMatchUpdated::class);
    }

    public function test_world_cup_sync_handles_broader_provider_payload_and_expected_request_volume(): void
    {
        config()->set('services.openligadb.world_cup.shortcut', 'wm26');
        config()->set('services.openligadb.world_cup.season', 2026);

        Http::fake($this->openLigaDbRichResponses());

        $this->artisan('world-cup:sync')
            ->assertSuccessful();

        Http::assertSentCount(4);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/getavailableleagues/2026'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/getavailableteams/wm26/2026'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/getavailablegroups/wm26/2026'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/getmatchdata/wm26/2026'));

        $this->assertDatabaseHas('teams', [
            'provider_team_id' => '30',
            'name' => 'Mexico',
        ]);
        $this->assertDatabaseHas('teams', [
            'provider_team_id' => '40',
            'name' => 'Canada',
        ]);
        $this->assertDatabaseHas('matches', [
            'provider_fixture_id' => '9002',
            'status' => 'finished',
            'home_score' => 2,
            'away_score' => 1,
        ]);
        $this->assertDatabaseHas('matches', [
            'provider_fixture_id' => '9003',
            'status' => 'scheduled',
        ]);
        $this->assertDatabaseCount('teams', 4);
        $this->assertDatabaseCount('tournament_groups', 2);
        $this->assertDatabaseCount('matches', 3);
    }

    public function test_world_cup_endpoints_return_synced_data_for_authenticated_user(): void
    {
        $this->syncFakeWorldCup();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/world-cup')
            ->assertOk()
            ->assertJsonPath('data.edition.name', 'FIFA World Cup')
            ->assertJsonPath('meta.teams_count', 2)
            ->assertJsonPath('meta.groups_count', 12)
            ->assertJsonPath('meta.matches_count', 1);

        $this->actingAs($user)
            ->getJson('/api/v1/world-cup/matches')
            ->assertOk()
            ->assertJsonCount(1, 'data.matches')
            ->assertJsonPath('data.matches.0.home_team.name', 'Brasil')
            ->assertJsonPath('data.matches.0.away_team.name', 'Argentina');

        $this->actingAs($user)
            ->getJson('/api/v1/world-cup/sync-status')
            ->assertOk()
            ->assertJsonPath('data.sync.status', 'synced')
            ->assertJsonPath('data.sync.last_changed_at', '2026-07-01T15:30:00.000000Z')
            ->assertJsonPath('data.budget.daily_limit', 1000);
    }

    public function test_world_cup_match_times_are_converted_from_provider_timezone_to_brazil(): void
    {
        config()->set('services.openligadb.source_timezone', 'Europe/Berlin');
        config()->set('services.openligadb.display_timezone', 'America/Sao_Paulo');
        $this->syncFakeWorldCup();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/world-cup/matches')
            ->assertOk()
            ->assertJsonPath('data.matches.0.starts_at_br', '2026-07-11T14:00:00-03:00')
            ->assertJsonPath('data.matches.0.timezone', 'America/Sao_Paulo')
            ->assertJsonPath('data.matches.0.provider_timezone', 'UTC');
    }

    public function test_world_cup_sync_persists_overtime_and_penalty_winners_from_complete_results(): void
    {
        config()->set('services.openligadb.world_cup.shortcut', 'wm26');
        config()->set('services.openligadb.world_cup.season', 2026);

        Http::fake([
            'https://api.openligadb.de/getmatchdata/wm26/2026' => Http::response([
                [
                    'matchID' => 'extra-time-1',
                    'matchDateTime' => '2026-07-04T18:00:00',
                    'timeZoneID' => 'UTC',
                    'leagueShortcut' => 'wm26',
                    'leagueName' => 'FIFA World Cup',
                    'leagueSeason' => 2026,
                    'group' => [
                        'groupID' => 201,
                        'groupName' => 'Achtelfinale',
                        'groupOrderID' => 4,
                    ],
                    'team1' => [
                        'teamId' => 610,
                        'teamName' => 'Brazil',
                        'shortName' => 'BRA',
                    ],
                    'team2' => [
                        'teamId' => 611,
                        'teamName' => 'Norway',
                        'shortName' => 'NOR',
                    ],
                    'matchIsFinished' => true,
                    'matchResults' => [
                        [
                            'resultName' => 'Endergebnis',
                            'resultTypeID' => 2,
                            'resultOrderID' => 2,
                            'pointsTeam1' => 1,
                            'pointsTeam2' => 1,
                        ],
                        [
                            'resultName' => 'Nach Verlangerung',
                            'resultTypeID' => 2,
                            'resultOrderID' => 3,
                            'pointsTeam1' => 2,
                            'pointsTeam2' => 1,
                        ],
                    ],
                ],
                [
                    'matchID' => 'penalties-1',
                    'matchDateTime' => '2026-07-04T21:00:00',
                    'timeZoneID' => 'UTC',
                    'leagueShortcut' => 'wm26',
                    'leagueName' => 'FIFA World Cup',
                    'leagueSeason' => 2026,
                    'group' => [
                        'groupID' => 201,
                        'groupName' => 'Achtelfinale',
                        'groupOrderID' => 4,
                    ],
                    'team1' => [
                        'teamId' => 620,
                        'teamName' => 'Belgium',
                        'shortName' => 'BEL',
                    ],
                    'team2' => [
                        'teamId' => 621,
                        'teamName' => 'Senegal',
                        'shortName' => 'SEN',
                    ],
                    'matchIsFinished' => true,
                    'matchResults' => [
                        [
                            'resultName' => 'Endergebnis',
                            'resultTypeID' => 2,
                            'resultOrderID' => 2,
                            'pointsTeam1' => 2,
                            'pointsTeam2' => 2,
                        ],
                        [
                            'resultName' => 'Nach Elfmeterschiessen',
                            'resultTypeID' => 2,
                            'resultOrderID' => 3,
                            'pointsTeam1' => 4,
                            'pointsTeam2' => 5,
                        ],
                    ],
                ],
                [
                    'matchID' => 'group-draw-auxiliary-1',
                    'matchDateTime' => '2026-06-20T18:00:00',
                    'timeZoneID' => 'UTC',
                    'leagueShortcut' => 'wm26',
                    'leagueName' => 'FIFA World Cup',
                    'leagueSeason' => 2026,
                    'group' => [
                        'groupID' => 101,
                        'groupName' => 'Group Stage - 1',
                        'groupOrderID' => 1,
                    ],
                    'team1' => [
                        'teamId' => 630,
                        'teamName' => 'Canada',
                        'shortName' => 'CAN',
                    ],
                    'team2' => [
                        'teamId' => 631,
                        'teamName' => 'Morocco',
                        'shortName' => 'MAR',
                    ],
                    'matchIsFinished' => true,
                    'matchResults' => [
                        [
                            'resultName' => 'Endergebnis',
                            'resultTypeID' => 2,
                            'resultOrderID' => 2,
                            'pointsTeam1' => 1,
                            'pointsTeam2' => 1,
                        ],
                        [
                            'resultName' => 'Auxiliary',
                            'resultTypeID' => 2,
                            'resultOrderID' => 3,
                            'pointsTeam1' => 0,
                            'pointsTeam2' => 1,
                        ],
                    ],
                ],
            ]),
        ]);

        $this->artisan('world-cup:sync --matches-only --essential')
            ->assertSuccessful();

        $brazil = Team::query()->where('provider_team_id', '610')->firstOrFail();
        $senegal = Team::query()->where('provider_team_id', '621')->firstOrFail();

        $this->assertDatabaseHas('matches', [
            'provider_fixture_id' => 'extra-time-1',
            'home_score' => 2,
            'away_score' => 1,
            'home_penalty_score' => null,
            'away_penalty_score' => null,
            'winner_team_id' => $brazil->id,
            'winner_source' => 'extra_time',
        ]);
        $this->assertDatabaseHas('matches', [
            'provider_fixture_id' => 'penalties-1',
            'home_score' => 2,
            'away_score' => 2,
            'home_penalty_score' => 4,
            'away_penalty_score' => 5,
            'winner_team_id' => $senegal->id,
            'winner_source' => 'penalties',
        ]);
        $this->assertDatabaseHas('matches', [
            'provider_fixture_id' => 'group-draw-auxiliary-1',
            'home_score' => 1,
            'away_score' => 1,
            'home_penalty_score' => null,
            'away_penalty_score' => null,
            'winner_team_id' => null,
            'winner_source' => null,
        ]);
    }

    public function test_world_cup_sync_treats_unlabeled_knockout_tiebreak_score_as_extra_time_not_penalties(): void
    {
        config()->set('services.openligadb.world_cup.shortcut', 'wm26');
        config()->set('services.openligadb.world_cup.season', 2026);

        Http::fake([
            'https://api.openligadb.de/getmatchdata/wm26/2026' => Http::response([
                [
                    'matchID' => 'extra-time-unlabeled-1',
                    'matchDateTime' => '2026-07-03T22:00:00',
                    'timeZoneID' => 'UTC',
                    'leagueShortcut' => 'wm26',
                    'leagueName' => 'FIFA World Cup',
                    'leagueSeason' => 2026,
                    'group' => [
                        'groupID' => 201,
                        'groupName' => 'Achtelfinale',
                        'groupOrderID' => 4,
                    ],
                    'team1' => [
                        'teamId' => 710,
                        'teamName' => 'Argentina',
                        'shortName' => 'ARG',
                    ],
                    'team2' => [
                        'teamId' => 711,
                        'teamName' => 'Cape Verde',
                        'shortName' => 'CPV',
                    ],
                    'matchIsFinished' => true,
                    'matchResults' => [
                        [
                            'resultName' => 'Endergebnis',
                            'resultTypeID' => 2,
                            'resultOrderID' => 2,
                            'pointsTeam1' => 1,
                            'pointsTeam2' => 1,
                        ],
                        [
                            'resultName' => 'Auxiliary',
                            'resultTypeID' => 2,
                            'resultOrderID' => 3,
                            'pointsTeam1' => 2,
                            'pointsTeam2' => 1,
                        ],
                    ],
                ],
            ]),
        ]);

        $this->artisan('world-cup:sync --matches-only --essential')
            ->assertSuccessful();

        $argentina = Team::query()->where('provider_team_id', '710')->firstOrFail();

        $this->assertDatabaseHas('matches', [
            'provider_fixture_id' => 'extra-time-unlabeled-1',
            'home_score' => 2,
            'away_score' => 1,
            'home_penalty_score' => null,
            'away_penalty_score' => null,
            'winner_team_id' => $argentina->id,
            'winner_source' => 'extra_time',
        ]);
    }


    public function test_league_matches_respect_private_league_membership(): void
    {
        $this->syncFakeWorldCup();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();

        $privateLeague = League::create([
            'owner_user_id' => $owner->id,
            'name' => 'Liga Privada Copa',
            'visibility' => 'private',
            'join_policy' => 'invite_code',
            'invite_code' => 'COPA2026',
            'status' => 'open',
        ]);

        LeagueMember::create([
            'league_id' => $privateLeague->id,
            'user_id' => $member->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($member)
            ->getJson("/api/v1/leagues/{$privateLeague->id}/matches")
            ->assertOk()
            ->assertJsonCount(1, 'data.matches');

        $this->actingAs($member)
            ->getJson("/api/v1/leagues/{$privateLeague->id}/world-cup/matches")
            ->assertOk()
            ->assertJsonCount(1, 'data.matches');

        $this->actingAs($outsider)
            ->getJson("/api/v1/leagues/{$privateLeague->id}/matches")
            ->assertNotFound();
    }

    public function test_league_world_cup_bracket_returns_knockout_stages(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $edition = TournamentEdition::create([
            'name' => 'WM 2026',
            'season' => 2026,
            'provider' => 'openligadb',
            'provider_league_id' => 'wm26',
            'status' => 'synced',
            'last_synced_at' => now(),
        ]);
        $stage = TournamentGroup::create([
            'tournament_edition_id' => $edition->id,
            'name' => 'Final',
            'external_name' => 'Finale',
            'internal_code' => 'final',
            'display_name' => 'Final',
        ]);
        WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'tournament_group_id' => $stage->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'final-1',
            'starts_at' => now()->addDays(3),
            'status' => 'scheduled',
        ]);
        $publicLeague = League::create([
            'owner_user_id' => $owner->id,
            'name' => 'Liga Publica Copa',
            'visibility' => 'public',
            'join_policy' => 'open',
            'status' => 'open',
        ]);

        $this->actingAs($viewer)
            ->getJson("/api/v1/leagues/{$publicLeague->id}/world-cup/bracket")
            ->assertOk()
            ->assertJsonPath('data.bracket.stages.0.code', 'round_of_32')
            ->assertJsonPath('data.bracket.stages.5.code', 'final')
            ->assertJsonPath('data.bracket.stages.5.matches.0.match_state_label', 'Aguardando selecoes');
    }

    public function test_bracket_prefers_round_name_when_persisted_stage_code_is_stale(): void
    {
        $user = User::factory()->create();
        $edition = TournamentEdition::create([
            'name' => 'WM 2026',
            'season' => 2026,
            'provider' => 'openligadb',
            'provider_league_id' => 'wm26',
            'status' => 'synced',
            'last_synced_at' => now(),
        ]);
        $staleStage = TournamentGroup::create([
            'tournament_edition_id' => $edition->id,
            'name' => 'Final',
            'external_name' => 'Sechzehntelfinale',
            'internal_code' => 'final',
            'display_name' => 'Final',
        ]);
        $home = Team::create([
            'name' => 'Alemanha',
            'display_name_pt_br' => 'Alemanha',
            'code' => 'ALE',
            'provider' => 'openligadb',
            'provider_team_id' => '201',
        ]);
        $away = Team::create([
            'name' => 'Paraguai',
            'display_name_pt_br' => 'Paraguai',
            'code' => 'PAR',
            'provider' => 'openligadb',
            'provider_team_id' => '202',
        ]);
        $match = WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'tournament_group_id' => $staleStage->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'round32-1',
            'round' => 'Sechzehntelfinale',
            'starts_at' => now()->addDays(1),
            'status' => 'scheduled',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/world-cup/bracket')
            ->assertOk()
            ->assertJsonPath('data.bracket.stages.0.code', 'round_of_32')
            ->assertJsonPath('data.bracket.stages.0.display_name', '16 avos de final')
            ->assertJsonPath('data.bracket.stages.0.matches.0.id', $match->id)
            ->assertJsonPath('data.bracket.stages.5.code', 'final')
            ->assertJsonPath('data.bracket.stages.5.matches.0.id', null);
    }

    public function test_bracket_matches_composite_team_codes_to_their_source_slot(): void
    {
        $user = User::factory()->create();
        $edition = TournamentEdition::create([
            'name' => 'WM 2026',
            'season' => 2026,
            'provider' => 'openligadb',
            'provider_league_id' => 'wm26',
            'status' => 'synced',
            'last_synced_at' => now(),
        ]);
        $stage = TournamentGroup::create([
            'tournament_edition_id' => $edition->id,
            'name' => 'Oitavas de final',
            'external_name' => 'Achtelfinale',
            'internal_code' => 'round_of_16',
            'display_name' => 'Oitavas de final',
        ]);
        $home = Team::create([
            'name' => 'POR/CRO',
            'display_name_pt_br' => 'POR/CRO',
            'code' => null,
            'provider' => 'openligadb',
            'provider_team_id' => '301',
        ]);
        $away = Team::create([
            'name' => 'ESP/AUT',
            'display_name_pt_br' => 'ESP/AUT',
            'code' => null,
            'provider' => 'openligadb',
            'provider_team_id' => '302',
        ]);
        $match = WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'tournament_group_id' => $stage->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'r16-composite-1',
            'round' => 'Achtelfinale',
            'starts_at' => now()->addDays(4),
            'status' => 'scheduled',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/world-cup/bracket')
            ->assertOk()
            ->assertJsonPath('data.bracket.stages.1.code', 'round_of_16')
            ->assertJsonPath('data.bracket.stages.1.matches.2.id', $match->id)
            ->assertJsonPath('data.bracket.stages.1.matches.0.id', null);
    }

    public function test_bracket_displays_resolved_winner_in_next_round_composite_slot(): void
    {
        $user = User::factory()->create();
        $edition = TournamentEdition::create([
            'name' => 'WM 2026',
            'season' => 2026,
            'provider' => 'openligadb',
            'provider_league_id' => 'wm26',
            'status' => 'synced',
            'last_synced_at' => now(),
        ]);
        $roundOf32 = TournamentGroup::create([
            'tournament_edition_id' => $edition->id,
            'name' => '16 avos de final',
            'external_name' => 'Sechzehntelfinale',
            'internal_code' => 'round_of_32',
            'display_name' => '16 avos de final',
        ]);
        $roundOf16 = TournamentGroup::create([
            'tournament_edition_id' => $edition->id,
            'name' => 'Oitavas de final',
            'external_name' => 'Achtelfinale',
            'internal_code' => 'round_of_16',
            'display_name' => 'Oitavas de final',
        ]);
        $usa = Team::create([
            'name' => 'Estados Unidos',
            'display_name_pt_br' => 'Estados Unidos',
            'code' => 'USA',
            'logo_url' => 'https://example.com/usa.png',
            'provider' => 'openligadb',
            'provider_team_id' => '501',
        ]);
        $bosnia = Team::create([
            'name' => 'Bosnia e Herzegovina',
            'display_name_pt_br' => 'Bosnia e Herzegovina',
            'code' => 'BIH',
            'provider' => 'openligadb',
            'provider_team_id' => '502',
        ]);
        $belgium = Team::create([
            'name' => 'Belgica',
            'display_name_pt_br' => 'Belgica',
            'code' => 'BEL',
            'logo_url' => 'https://example.com/belgium.png',
            'provider' => 'openligadb',
            'provider_team_id' => '503',
        ]);
        $senegal = Team::create([
            'name' => 'Senegal',
            'display_name_pt_br' => 'Senegal',
            'code' => 'SEN',
            'provider' => 'openligadb',
            'provider_team_id' => '504',
        ]);
        $composite = Team::create([
            'name' => 'USA/BIH',
            'display_name_pt_br' => 'USA/BIH',
            'code' => null,
            'provider' => 'openligadb',
            'provider_team_id' => '505',
        ]);

        WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'tournament_group_id' => $roundOf32->id,
            'home_team_id' => $usa->id,
            'away_team_id' => $bosnia->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'f32-7',
            'round' => 'Sechzehntelfinale',
            'starts_at' => now()->subDay(),
            'status' => 'finished',
            'home_score' => 2,
            'away_score' => 0,
        ]);
        WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'tournament_group_id' => $roundOf32->id,
            'home_team_id' => $belgium->id,
            'away_team_id' => $senegal->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'f32-8',
            'round' => 'Sechzehntelfinale',
            'starts_at' => now()->subHours(12),
            'status' => 'finished',
            'home_score' => 1,
            'away_score' => 0,
        ]);
        $nextMatch = WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'tournament_group_id' => $roundOf16->id,
            'home_team_id' => $composite->id,
            'away_team_id' => $belgium->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'r16-4',
            'round' => 'Achtelfinale',
            'starts_at' => now()->addDays(2),
            'status' => 'scheduled',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/world-cup/bracket')
            ->assertOk()
            ->assertJsonPath('data.bracket.stages.1.matches.3.id', $nextMatch->id)
            ->assertJsonPath('data.bracket.stages.1.matches.3.slot_home_label', 'Estados Unidos')
            ->assertJsonPath('data.bracket.stages.1.matches.3.slot_away_label', 'Belgica')
            ->assertJsonPath('data.bracket.stages.1.matches.3.home_team.display_name', 'Estados Unidos')
            ->assertJsonPath('data.bracket.stages.1.matches.3.home_team.logo_url', 'https://example.com/usa.png')
            ->assertJsonPath('data.bracket.stages.1.matches.3.away_team.display_name', 'Belgica')
            ->assertJsonPath('data.bracket.stages.1.matches.3.match_state', 'open_for_prediction')
            ->assertJsonPath('data.bracket.stages.1.matches.3.can_predict', true);
    }

    public function test_bracket_uses_penalty_winner_when_scores_are_tied(): void
    {
        $user = User::factory()->create();
        $edition = TournamentEdition::create([
            'name' => 'WM 2026',
            'season' => 2026,
            'provider' => 'openligadb',
            'provider_league_id' => 'wm26',
            'status' => 'synced',
            'last_synced_at' => now(),
        ]);
        $roundOf32 = TournamentGroup::create([
            'tournament_edition_id' => $edition->id,
            'name' => '16 avos de final',
            'external_name' => 'Sechzehntelfinale',
            'internal_code' => 'round_of_32',
            'display_name' => '16 avos de final',
        ]);
        $roundOf16 = TournamentGroup::create([
            'tournament_edition_id' => $edition->id,
            'name' => 'Oitavas de final',
            'external_name' => 'Achtelfinale',
            'internal_code' => 'round_of_16',
            'display_name' => 'Oitavas de final',
        ]);
        $usa = Team::create([
            'name' => 'Estados Unidos',
            'display_name_pt_br' => 'Estados Unidos',
            'code' => 'USA',
            'provider' => 'openligadb',
            'provider_team_id' => '701',
        ]);
        $bosnia = Team::create([
            'name' => 'Bosnia e Herzegovina',
            'display_name_pt_br' => 'Bosnia e Herzegovina',
            'code' => 'BIH',
            'provider' => 'openligadb',
            'provider_team_id' => '702',
        ]);
        $belgium = Team::create([
            'name' => 'Belgica',
            'display_name_pt_br' => 'Belgica',
            'code' => 'BEL',
            'provider' => 'openligadb',
            'provider_team_id' => '703',
        ]);
        $senegal = Team::create([
            'name' => 'Senegal',
            'display_name_pt_br' => 'Senegal',
            'code' => 'SEN',
            'provider' => 'openligadb',
            'provider_team_id' => '704',
        ]);
        $usaBosnia = Team::create([
            'name' => 'USA/BIH',
            'display_name_pt_br' => 'USA/BIH',
            'code' => null,
            'provider' => 'openligadb',
            'provider_team_id' => '705',
        ]);

        WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'tournament_group_id' => $roundOf32->id,
            'home_team_id' => $usa->id,
            'away_team_id' => $bosnia->id,
            'winner_team_id' => $usa->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'f32-pen-source-7',
            'round' => 'Sechzehntelfinale',
            'starts_at' => now()->subDay(),
            'status' => 'finished',
            'home_score' => 2,
            'away_score' => 0,
            'winner_source' => 'score',
        ]);
        WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'tournament_group_id' => $roundOf32->id,
            'home_team_id' => $belgium->id,
            'away_team_id' => $senegal->id,
            'winner_team_id' => $senegal->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'f32-pen-source-8',
            'round' => 'Sechzehntelfinale',
            'starts_at' => now()->subHours(12),
            'status' => 'finished',
            'home_score' => 2,
            'away_score' => 2,
            'home_penalty_score' => 4,
            'away_penalty_score' => 5,
            'winner_source' => 'penalties',
        ]);
        $nextMatch = WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'tournament_group_id' => $roundOf16->id,
            'home_team_id' => $usaBosnia->id,
            'away_team_id' => $senegal->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'r16-pen-4',
            'round' => 'Achtelfinale',
            'starts_at' => now()->addDays(2),
            'status' => 'scheduled',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/world-cup/bracket')
            ->assertOk()
            ->assertJsonPath('data.bracket.stages.0.matches.7.winner_side', 'away')
            ->assertJsonPath('data.bracket.stages.0.matches.7.winner_source', 'penalties')
            ->assertJsonPath('data.bracket.stages.0.matches.7.home_penalty_score', 4)
            ->assertJsonPath('data.bracket.stages.0.matches.7.away_penalty_score', 5)
            ->assertJsonPath('data.bracket.stages.1.matches.3.id', $nextMatch->id)
            ->assertJsonPath('data.bracket.stages.1.matches.3.slot_home_label', 'Estados Unidos')
            ->assertJsonPath('data.bracket.stages.1.matches.3.slot_away_label', 'Senegal');
    }

    public function test_bracket_does_not_fallback_to_first_remaining_match_for_wrong_slot(): void
    {
        $user = User::factory()->create();
        $edition = TournamentEdition::create([
            'name' => 'WM 2026',
            'season' => 2026,
            'provider' => 'openligadb',
            'provider_league_id' => 'wm26',
            'status' => 'synced',
            'last_synced_at' => now(),
        ]);
        $stage = TournamentGroup::create([
            'tournament_edition_id' => $edition->id,
            'name' => 'Quartas de final',
            'external_name' => 'Viertelfinale',
            'internal_code' => 'quarterfinal',
            'display_name' => 'Quartas de final',
        ]);
        $home = Team::create([
            'name' => 'BRA/NOR',
            'display_name_pt_br' => 'BRA/NOR',
            'code' => null,
            'provider' => 'openligadb',
            'provider_team_id' => '401',
        ]);
        $away = Team::create([
            'name' => 'MEX/ENG',
            'display_name_pt_br' => 'MEX/ENG',
            'code' => null,
            'provider' => 'openligadb',
            'provider_team_id' => '402',
        ]);
        $match = WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'tournament_group_id' => $stage->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'qf-right-upper',
            'round' => 'Viertelfinale',
            'starts_at' => now()->addDays(8),
            'status' => 'scheduled',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/world-cup/bracket')
            ->assertOk()
            ->assertJsonPath('data.bracket.stages.2.matches.1.id', null)
            ->assertJsonPath('data.bracket.stages.2.matches.2.id', $match->id);
    }

    public function test_provisional_knockout_teams_are_locked_for_predictions(): void
    {
        $user = User::factory()->create();
        $edition = TournamentEdition::create([
            'name' => 'WM 2026',
            'season' => 2026,
            'provider' => 'openligadb',
            'provider_league_id' => 'wm26',
            'status' => 'synced',
            'last_synced_at' => now(),
        ]);
        $stage = TournamentGroup::create([
            'tournament_edition_id' => $edition->id,
            'name' => 'Oitavas de final',
            'external_name' => 'Achtelfinale',
            'internal_code' => 'round_of_16',
            'display_name' => 'Oitavas de final',
        ]);
        $home = Team::create([
            'name' => 'ARG/CPV',
            'display_name_pt_br' => 'ARG/CPV',
            'code' => 'ARG',
            'provider' => 'openligadb',
            'provider_team_id' => '101',
        ]);
        $away = Team::create([
            'name' => 'AUS/EGY',
            'display_name_pt_br' => 'AUS/EGY',
            'code' => 'AUS',
            'provider' => 'openligadb',
            'provider_team_id' => '102',
        ]);
        $match = WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'tournament_group_id' => $stage->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'provisional-1',
            'starts_at' => now()->addDays(2),
            'status' => 'scheduled',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/world-cup/matches/{$match->id}")
            ->assertOk()
            ->assertJsonPath('data.match.match_state', 'awaiting_teams')
            ->assertJsonPath('data.match.prediction_status', 'locked')
            ->assertJsonPath('data.match.can_predict', false);
    }

    public function test_league_world_cup_groups_returns_twelve_official_groups(): void
    {
        $this->syncFakeWorldCup();
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $publicLeague = League::create([
            'owner_user_id' => $owner->id,
            'name' => 'Liga Publica Copa',
            'visibility' => 'public',
            'join_policy' => 'open',
            'status' => 'open',
        ]);

        $this->actingAs($viewer)
            ->getJson("/api/v1/leagues/{$publicLeague->id}/world-cup/groups")
            ->assertOk()
            ->assertJsonCount(12, 'data.groups')
            ->assertJsonPath('data.groups.0.code', 'A')
            ->assertJsonPath('data.groups.11.code', 'L');

        $this->actingAs($viewer)
            ->getJson("/api/v1/leagues/{$publicLeague->id}/world-cup/stages")
            ->assertOk()
            ->assertJsonCount(12, 'data.stages');
    }

    public function test_league_world_cup_groups_respects_prediction_lock_window(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $edition = TournamentEdition::create([
            'name' => 'WM 2026',
            'season' => 2026,
            'provider' => 'openligadb',
            'provider_league_id' => 'wm26',
            'status' => 'synced',
            'last_synced_at' => now(),
        ]);
        $group = TournamentGroup::create([
            'tournament_edition_id' => $edition->id,
            'name' => 'Fase de grupos',
            'external_name' => 'Group Stage',
            'internal_code' => 'group_stage',
            'display_name' => 'Fase de grupos',
        ]);
        $home = Team::create([
            'name' => 'Brasil',
            'display_name_pt_br' => 'Brasil',
            'provider' => 'openligadb',
            'provider_team_id' => '10',
        ]);
        $away = Team::create([
            'name' => 'Marrocos',
            'display_name_pt_br' => 'Marrocos',
            'provider' => 'openligadb',
            'provider_team_id' => '20',
        ]);
        WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'tournament_group_id' => $group->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'lock-window-1',
            'starts_at' => now()->addMinutes(20),
            'status' => 'scheduled',
        ]);

        $league = League::create([
            'owner_user_id' => $owner->id,
            'name' => 'Liga com trava antecipada',
            'visibility' => 'public',
            'join_policy' => 'open',
            'status' => 'open',
        ]);
        $league->settings()->create([
            'points_correct_outcome' => 3,
            'points_wrong_outcome' => 0,
            'points_exact_score' => 5,
            'points_correct_goal_difference' => 3,
            'points_correct_outcome_scoreline' => 2,
            'prediction_lock_minutes_before_start' => 30,
            'allow_prediction_cancellation' => true,
            'late_join_enabled' => true,
            'ranking_visibility' => 'members',
        ]);

        $this->actingAs($viewer)
            ->getJson("/api/v1/leagues/{$league->id}/world-cup/groups")
            ->assertOk()
            ->assertJsonPath('data.groups.2.matches.0.can_predict', false);
    }

    public function test_openligadb_budget_preserves_reserved_requests(): void
    {
        config()->set('services.openligadb.daily_limit', 1);
        config()->set('services.openligadb.reserved_requests', 0);
        config()->set('services.openligadb.world_cup.shortcut', 'wm26');
        config()->set('services.openligadb.world_cup.season', 2026);
        Http::fake($this->openLigaDbResponses());

        $this->artisan('world-cup:sync')
            ->assertSuccessful();

        $this->assertDatabaseHas('world_cup_sync_states', [
            'provider' => 'openligadb',
            'scope' => 'world_cup',
            'status' => 'failed',
        ]);
    }

    public function test_openligadb_budget_counts_calls_by_display_timezone_day(): void
    {
        config()->set('services.openligadb.display_timezone', 'America/Sao_Paulo');
        Carbon::setTestNow(Carbon::parse('2026-07-02 01:00:00', 'America/Sao_Paulo'));

        try {
            ApiSyncLog::create([
                'provider' => 'openligadb',
                'operation' => 'matches',
                'scope' => 'wm26:2026',
                'priority' => 'essential',
                'status' => 'success',
                'calls_count' => 1,
                'started_at' => Carbon::parse('2026-07-01 23:30:00', 'America/Sao_Paulo')->utc(),
            ]);
            ApiSyncLog::create([
                'provider' => 'openligadb',
                'operation' => 'matches',
                'scope' => 'wm26:2026',
                'priority' => 'essential',
                'status' => 'success',
                'calls_count' => 1,
                'started_at' => Carbon::parse('2026-07-02 00:10:00', 'America/Sao_Paulo')->utc(),
            ]);

            $this->actingAs(User::factory()->create())
                ->getJson('/api/v1/world-cup/sync-status')
                ->assertOk()
                ->assertJsonPath('data.budget.date', '2026-07-02')
                ->assertJsonPath('data.budget.timezone', 'America/Sao_Paulo')
                ->assertJsonPath('data.budget.calls_used_today', 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_world_cup_matches_only_sync_updates_finished_match_with_single_provider_call(): void
    {
        config()->set('services.openligadb.world_cup.shortcut', 'wm26');
        config()->set('services.openligadb.world_cup.season', 2026);
        $edition = TournamentEdition::create([
            'name' => 'WM 2026',
            'season' => 2026,
            'provider' => 'openligadb',
            'provider_league_id' => 'wm26',
            'status' => 'synced',
            'last_synced_at' => now(),
        ]);
        WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => '9002',
            'starts_at' => now()->subHours(2),
            'status' => 'in_progress_unconfirmed',
        ]);

        Http::fake([
            'https://api.openligadb.de/getmatchdata/wm26/2026' => Http::response([
                [
                    'matchID' => 9002,
                    'matchDateTime' => now()->subHours(2)->toIso8601String(),
                    'timeZoneID' => 'UTC',
                    'leagueShortcut' => 'wm26',
                    'leagueName' => 'FIFA World Cup',
                    'leagueSeason' => 2026,
                    'group' => [
                        'groupID' => 201,
                        'groupName' => 'Achtelfinale',
                        'groupOrderID' => 4,
                    ],
                    'team1' => [
                        'teamId' => 30,
                        'teamName' => 'Mexico',
                        'shortName' => 'MEX',
                    ],
                    'team2' => [
                        'teamId' => 40,
                        'teamName' => 'Canada',
                        'shortName' => 'CAN',
                    ],
                    'matchIsFinished' => true,
                    'matchResults' => [
                        [
                            'resultTypeID' => 2,
                            'resultOrderID' => 2,
                            'pointsTeam1' => 2,
                            'pointsTeam2' => 1,
                        ],
                    ],
                ],
            ]),
        ]);

        $this->artisan('world-cup:sync --matches-only --essential')
            ->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/getmatchdata/wm26/2026'));
        $this->assertDatabaseHas('matches', [
            'provider_fixture_id' => '9002',
            'status' => 'finished',
            'home_score' => 2,
            'away_score' => 1,
        ]);
    }

    public function test_recent_in_progress_match_remains_inside_extended_sync_window_after_three_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-03 22:05:00', 'America/Sao_Paulo'));

        try {
            $edition = TournamentEdition::create([
                'name' => 'WM 2026',
                'season' => 2026,
                'provider' => 'openligadb',
                'provider_league_id' => 'wm26',
                'status' => 'synced',
                'last_synced_at' => now(),
            ]);

            WorldCupMatch::create([
                'tournament_edition_id' => $edition->id,
                'provider' => 'openligadb',
                'provider_fixture_id' => 'scheduler-1',
                'starts_at' => Carbon::parse('2026-07-03 19:00:00', 'America/Sao_Paulo')->utc(),
                'status' => 'in_progress_unconfirmed',
            ]);

            $this->assertTrue(
                app(WorldCupSyncWindowService::class)->hasMatchesNeedingSync(360, 15),
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_world_cup_matches_only_sync_preserves_existing_team_flag_when_payload_omits_icon(): void
    {
        config()->set('services.openligadb.world_cup.shortcut', 'wm26');
        config()->set('services.openligadb.world_cup.season', 2026);
        TournamentEdition::create([
            'name' => 'WM 2026',
            'season' => 2026,
            'provider' => 'openligadb',
            'provider_league_id' => 'wm26',
            'status' => 'synced',
            'last_synced_at' => now(),
        ]);
        Team::create([
            'name' => 'Estados Unidos',
            'display_name_pt_br' => 'Estados Unidos',
            'provider' => 'openligadb',
            'provider_team_id' => '762',
            'code' => 'USA',
            'logo_url' => 'https://example.com/usa-flag.png',
        ]);

        Http::fake([
            'https://api.openligadb.de/getmatchdata/wm26/2026' => Http::response([
                [
                    'matchID' => 9101,
                    'matchDateTime' => now()->addDay()->toIso8601String(),
                    'timeZoneID' => 'UTC',
                    'leagueShortcut' => 'wm26',
                    'leagueName' => 'FIFA World Cup',
                    'leagueSeason' => 2026,
                    'group' => [
                        'groupID' => 201,
                        'groupName' => 'Achtelfinale',
                        'groupOrderID' => 4,
                    ],
                    'team1' => [
                        'teamId' => 762,
                        'teamName' => 'USA',
                        'shortName' => 'USA',
                    ],
                    'team2' => [
                        'teamId' => 2673,
                        'teamName' => 'Belgium',
                        'shortName' => 'BEL',
                    ],
                    'matchIsFinished' => false,
                    'matchResults' => [],
                ],
            ]),
        ]);

        $this->artisan('world-cup:sync --matches-only --essential')
            ->assertSuccessful();

        $this->assertDatabaseHas('teams', [
            'provider_team_id' => '762',
            'logo_url' => 'https://example.com/usa-flag.png',
        ]);
    }

    public function test_public_league_matches_are_visible_to_authenticated_users(): void
    {
        $this->syncFakeWorldCup();
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $publicLeague = League::create([
            'owner_user_id' => $owner->id,
            'name' => 'Liga Publica Copa',
            'visibility' => 'public',
            'join_policy' => 'open',
            'status' => 'open',
        ]);

        $this->actingAs($viewer)
            ->getJson("/api/v1/leagues/{$publicLeague->id}/matches")
            ->assertOk()
            ->assertJsonCount(1, 'data.matches');
    }

    public function test_world_cup_matches_can_be_filtered_by_current_period(): void
    {
        $user = User::factory()->create();
        $edition = TournamentEdition::create([
            'name' => 'WM 2026',
            'season' => 2026,
            'provider' => 'openligadb',
            'provider_league_id' => 'wm26',
            'status' => 'synced',
            'last_synced_at' => now(),
        ]);

        WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'current-live',
            'starts_at' => now()->subMinutes(10),
            'status' => 'in_progress_unconfirmed',
        ]);

        WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'current-today',
            'starts_at' => now()->startOfDay()->addHours(14),
            'status' => 'scheduled',
        ]);

        WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'future',
            'starts_at' => now()->addDays(4),
            'status' => 'scheduled',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/world-cup/matches?period=current')
            ->assertOk()
            ->assertJsonCount(2, 'data.matches')
            ->assertJsonPath('meta.filters.period', 'current')
            ->assertJsonMissing(['provider_fixture_id' => 'future']);
    }

    private function syncFakeWorldCup(): void
    {
        config()->set('services.openligadb.world_cup.shortcut', 'wm26');
        config()->set('services.openligadb.world_cup.season', 2026);

        Http::fake($this->openLigaDbResponses());

        $this->artisan('world-cup:sync')->assertSuccessful();
    }

    /**
     * @return array<string, mixed>
     */
    private function openLigaDbResponses(): array
    {
        return [
            'https://api.openligadb.de/getavailableleagues/2026' => Http::response([
                [
                    'leagueShortcut' => 'wm26',
                    'leagueName' => 'FIFA World Cup',
                    'leagueSeason' => 2026,
                ],
            ]),
            'https://api.openligadb.de/getavailableteams/wm26/2026' => Http::response([
                [
                    'teamId' => 10,
                    'teamName' => 'Brazil',
                    'shortName' => 'BRA',
                    'teamIconUrl' => 'https://example.com/brazil.png',
                ],
                [
                    'teamId' => 20,
                    'teamName' => 'Argentina',
                    'shortName' => 'ARG',
                    'teamIconUrl' => 'https://example.com/argentina.png',
                ],
            ]),
            'https://api.openligadb.de/getavailablegroups/wm26/2026' => Http::response([
                [
                    'groupID' => 101,
                    'groupName' => 'Group Stage - 1',
                    'groupOrderID' => 1,
                ],
            ]),
            'https://api.openligadb.de/getmatchdata/wm26/2026' => Http::response([
                [
                    'matchID' => 9001,
                    'matchDateTime' => '2026-07-11T19:00:00',
                    'timeZoneID' => 'UTC',
                    'leagueShortcut' => 'wm26',
                    'leagueName' => 'FIFA World Cup',
                    'leagueSeason' => 2026,
                    'lastUpdateDateTime' => '2026-07-01T17:30:00',
                    'group' => [
                        'groupID' => 101,
                        'groupName' => 'Group Stage - 1',
                        'groupOrderID' => 1,
                    ],
                    'team1' => [
                        'teamId' => 10,
                        'teamName' => 'Brazil',
                        'shortName' => 'BRA',
                        'teamIconUrl' => 'https://example.com/brazil.png',
                    ],
                    'team2' => [
                        'teamId' => 20,
                        'teamName' => 'Argentina',
                        'shortName' => 'ARG',
                        'teamIconUrl' => 'https://example.com/argentina.png',
                    ],
                    'matchIsFinished' => false,
                    'matchResults' => [],
                ],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function openLigaDbRichResponses(): array
    {
        return [
            'https://api.openligadb.de/getavailableleagues/2026' => Http::response([
                [
                    'leagueShortcut' => 'wm26',
                    'leagueName' => 'FIFA World Cup',
                    'leagueSeason' => 2026,
                ],
                [
                    'leagueShortcut' => 'bl1',
                    'leagueName' => 'Bundesliga',
                    'leagueSeason' => 2026,
                ],
            ]),
            'https://api.openligadb.de/getavailableteams/wm26/2026' => Http::response([
                [
                    'teamId' => 10,
                    'teamName' => 'Brazil',
                    'shortName' => 'BRA',
                    'teamIconUrl' => 'https://example.com/brazil.png',
                ],
                [
                    'teamId' => 20,
                    'teamName' => 'Argentina',
                    'shortName' => 'ARG',
                    'teamIconUrl' => 'https://example.com/argentina.png',
                ],
                [
                    'teamId' => 30,
                    'teamName' => 'Mexico',
                    'shortName' => 'MEX',
                    'teamIconUrl' => 'https://example.com/mexico.png',
                ],
            ]),
            'https://api.openligadb.de/getavailablegroups/wm26/2026' => Http::response([
                [
                    'groupID' => 101,
                    'groupName' => 'Group Stage - 1',
                    'groupOrderID' => 1,
                ],
                [
                    'groupID' => 201,
                    'groupName' => 'Achtelfinale',
                    'groupOrderID' => 4,
                ],
            ]),
            'https://api.openligadb.de/getmatchdata/wm26/2026' => Http::response([
                [
                    'matchID' => 9001,
                    'matchDateTime' => '2026-07-11T19:00:00',
                    'timeZoneID' => 'UTC',
                    'leagueShortcut' => 'wm26',
                    'leagueName' => 'FIFA World Cup',
                    'leagueSeason' => 2026,
                    'lastUpdateDateTime' => '2026-07-01T15:30:00',
                    'group' => [
                        'groupID' => 101,
                        'groupName' => 'Group Stage - 1',
                        'groupOrderID' => 1,
                    ],
                    'team1' => [
                        'teamId' => 10,
                        'teamName' => 'Brazil',
                        'shortName' => 'BRA',
                        'teamIconUrl' => 'https://example.com/brazil.png',
                    ],
                    'team2' => [
                        'teamId' => 20,
                        'teamName' => 'Argentina',
                        'shortName' => 'ARG',
                        'teamIconUrl' => 'https://example.com/argentina.png',
                    ],
                    'matchIsFinished' => false,
                    'matchResults' => [],
                ],
                [
                    'matchID' => 9002,
                    'matchDateTime' => '2026-07-12T16:00:00',
                    'timeZoneID' => 'UTC',
                    'leagueShortcut' => 'wm26',
                    'leagueName' => 'FIFA World Cup',
                    'leagueSeason' => 2026,
                    'lastUpdateDateTime' => '2026-07-01T18:30:00',
                    'group' => [
                        'groupID' => 101,
                        'groupName' => 'Group Stage - 1',
                        'groupOrderID' => 1,
                    ],
                    'team1' => [
                        'teamId' => 30,
                        'teamName' => 'Mexico',
                        'shortName' => 'MEX',
                        'teamIconUrl' => 'https://example.com/mexico.png',
                    ],
                    'team2' => [
                        'teamId' => 40,
                        'teamName' => 'Canada',
                        'shortName' => 'CAN',
                        'teamIconUrl' => 'https://example.com/canada.png',
                    ],
                    'matchIsFinished' => true,
                    'matchResults' => [
                        [
                            'resultTypeID' => 1,
                            'resultOrderID' => 1,
                            'pointsTeam1' => 1,
                            'pointsTeam2' => 1,
                        ],
                        [
                            'resultTypeID' => 2,
                            'resultOrderID' => 2,
                            'pointsTeam1' => 2,
                            'pointsTeam2' => 1,
                        ],
                    ],
                ],
                [
                    'matchID' => 9003,
                    'matchDateTime' => '2026-07-18T20:30:00',
                    'timeZoneID' => 'UTC',
                    'leagueShortcut' => 'wm26',
                    'leagueName' => 'FIFA World Cup',
                    'leagueSeason' => 2026,
                    'lastUpdateDateTime' => '2026-07-01T14:15:00',
                    'group' => [
                        'groupID' => 201,
                        'groupName' => 'Achtelfinale',
                        'groupOrderID' => 4,
                    ],
                    'team1' => [
                        'teamId' => 10,
                        'teamName' => 'Brazil',
                        'shortName' => 'BRA',
                        'teamIconUrl' => 'https://example.com/brazil.png',
                    ],
                    'team2' => [
                        'teamId' => 40,
                        'teamName' => 'Canada',
                        'shortName' => 'CAN',
                        'teamIconUrl' => 'https://example.com/canada.png',
                    ],
                    'matchIsFinished' => false,
                    'matchResults' => [],
                ],
            ]),
        ];
    }
}
