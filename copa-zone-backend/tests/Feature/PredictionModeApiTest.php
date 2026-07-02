<?php

namespace Tests\Feature;

use App\Events\LeagueRankingUpdated;
use App\Events\WorldCupPredictionLockReached;
use App\Events\WorldCupPredictionScored;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\Prediction;
use App\Models\Team;
use App\Models\TournamentEdition;
use App\Models\TournamentGroup;
use App\Models\User;
use App\Models\WorldCupMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PredictionModeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_and_update_score_prediction_before_lock(): void
    {
        $user = User::factory()->create();
        $league = $this->createLeagueFor($user);
        $match = $this->createMatch(now()->addDay());

        $this->actingAs($user)
            ->putJson("/api/v1/leagues/{$league->id}/matches/{$match->id}/prediction", [
                'predicted_home_score' => 2,
                'predicted_away_score' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.prediction.predicted_home_score', 2)
            ->assertJsonPath('data.prediction.prediction_version', 1);

        $this->actingAs($user)
            ->putJson("/api/v1/leagues/{$league->id}/matches/{$match->id}/prediction", [
                'predicted_home_score' => 1,
                'predicted_away_score' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.prediction.predicted_home_score', 1)
            ->assertJsonPath('data.prediction.prediction_version', 2);

        $this->assertDatabaseCount('predictions', 1);
    }

    public function test_prediction_is_blocked_after_match_start(): void
    {
        $user = User::factory()->create();
        $league = $this->createLeagueFor($user);
        $match = $this->createMatch(now()->subMinute(), 'in_progress_unconfirmed');

        $this->actingAs($user)
            ->putJson("/api/v1/leagues/{$league->id}/matches/{$match->id}/prediction", [
                'predicted_home_score' => 2,
                'predicted_away_score' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('match');
    }

    public function test_prediction_is_blocked_when_knockout_teams_are_not_defined(): void
    {
        $user = User::factory()->create();
        $league = $this->createLeagueFor($user);
        $match = $this->createMatch(now()->addDay(), 'scheduled', 'ARG/CPV', 'AUS/EGY');

        $this->actingAs($user)
            ->putJson("/api/v1/leagues/{$league->id}/matches/{$match->id}/prediction", [
                'predicted_home_score' => 2,
                'predicted_away_score' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('match');
    }

    public function test_prediction_is_allowed_when_knockout_placeholder_was_resolved_by_previous_winners(): void
    {
        $user = User::factory()->create();
        $league = $this->createLeagueFor($user);
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
            'code' => 'USA/BIH',
            'provider' => 'openligadb',
            'provider_team_id' => '505',
        ]);

        WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'tournament_group_id' => $roundOf32->id,
            'home_team_id' => $usa->id,
            'away_team_id' => $bosnia->id,
            'winner_team_id' => $usa->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'f32-source-7',
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
            'winner_team_id' => $belgium->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'f32-source-8',
            'round' => 'Sechzehntelfinale',
            'starts_at' => now()->subDay(),
            'status' => 'finished',
            'home_score' => 2,
            'away_score' => 2,
            'home_penalty_score' => 3,
            'away_penalty_score' => 2,
            'winner_source' => 'penalties',
        ]);
        $match = WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'tournament_group_id' => $roundOf16->id,
            'home_team_id' => $composite->id,
            'away_team_id' => $belgium->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'r16-source-4',
            'round' => 'Achtelfinale',
            'starts_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $this->actingAs($user)
            ->putJson("/api/v1/leagues/{$league->id}/matches/{$match->id}/prediction", [
                'predicted_home_score' => 1,
                'predicted_away_score' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('data.prediction.predicted_home_score', 1);
    }

    public function test_knockout_draw_prediction_requires_winner_side(): void
    {
        $user = User::factory()->create();
        $league = $this->createLeagueFor($user);
        $match = $this->createKnockoutMatch(now()->addDay());

        $this->actingAs($user)
            ->putJson("/api/v1/leagues/{$league->id}/matches/{$match->id}/prediction", [
                'predicted_home_score' => 1,
                'predicted_away_score' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('predicted_winner_side');
    }

    public function test_penalty_shootout_scoring_requires_correct_predicted_winner(): void
    {
        $owner = User::factory()->create();
        $wrongUser = User::factory()->create();
        $league = $this->createLeagueFor($owner);
        $wrongMember = LeagueMember::create([
            'league_id' => $league->id,
            'user_id' => $wrongUser->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        $match = $this->createKnockoutMatch(now()->addDay());

        $this->actingAs($owner)
            ->putJson("/api/v1/leagues/{$league->id}/matches/{$match->id}/prediction", [
                'predicted_home_score' => 2,
                'predicted_away_score' => 2,
                'predicted_winner_side' => 'away',
            ])
            ->assertCreated();

        $this->actingAs($wrongUser)
            ->putJson("/api/v1/leagues/{$league->id}/matches/{$match->id}/prediction", [
                'predicted_home_score' => 2,
                'predicted_away_score' => 2,
                'predicted_winner_side' => 'home',
            ])
            ->assertCreated();

        $match->forceFill([
            'status' => 'finished',
            'home_score' => 2,
            'away_score' => 2,
            'home_penalty_score' => 4,
            'away_penalty_score' => 5,
            'winner_team_id' => $match->away_team_id,
            'winner_source' => 'penalties',
        ])->save();

        $this->artisan('world-cup:score-predictions')
            ->expectsOutput('Palpites apurados: 2')
            ->assertSuccessful();

        $ownerMember = $league->members()->where('user_id', $owner->id)->firstOrFail();
        $rightPrediction = Prediction::query()
            ->where('league_member_id', $ownerMember->id)
            ->where('match_id', $match->id)
            ->firstOrFail();
        $wrongPrediction = Prediction::query()
            ->where('league_member_id', $wrongMember->id)
            ->where('match_id', $match->id)
            ->firstOrFail();

        $this->assertSame(5, $rightPrediction->points_awarded);
        $this->assertSame('exact_score', $rightPrediction->score_reason);
        $this->assertSame(0, $wrongPrediction->points_awarded);
        $this->assertSame('wrong', $wrongPrediction->score_reason);
    }

    public function test_rescore_applies_knockout_winner_rule_to_already_settled_predictions(): void
    {
        $user = User::factory()->create();
        $league = $this->createLeagueFor($user);
        $match = $this->createKnockoutMatch(now()->subDay(), 'finished');
        $match->forceFill([
            'home_score' => 2,
            'away_score' => 2,
            'home_penalty_score' => 4,
            'away_penalty_score' => 5,
            'winner_team_id' => $match->away_team_id,
            'winner_source' => 'penalties',
        ])->save();
        $member = $league->members()->where('user_id', $user->id)->firstOrFail();
        $prediction = Prediction::create([
            'league_id' => $league->id,
            'league_member_id' => $member->id,
            'match_id' => $match->id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 2,
            'predicted_winner_side' => null,
            'status' => 'settled',
            'submitted_at' => now()->subDays(2),
            'locked_at' => now()->subDay(),
            'scored_at' => now()->subHour(),
            'points_awarded' => 5,
            'score_reason' => 'exact_score',
            'prediction_version' => 1,
        ]);

        $this->artisan('world-cup:score-predictions')
            ->expectsOutput('Palpites apurados: 0')
            ->assertSuccessful();

        $this->assertSame(5, $prediction->refresh()->points_awarded);

        $this->artisan('world-cup:score-predictions --rescore')
            ->expectsOutput('Palpites apurados: 1')
            ->assertSuccessful();

        $prediction->refresh();

        $this->assertSame(0, $prediction->points_awarded);
        $this->assertSame('wrong', $prediction->score_reason);
    }

    public function test_finished_match_scores_predictions_and_rebuilds_ranking(): void
    {
        $user = User::factory()->create();
        $league = $this->createLeagueFor($user);
        $match = $this->createMatch(now()->addDay());

        $this->actingAs($user)
            ->putJson("/api/v1/leagues/{$league->id}/matches/{$match->id}/prediction", [
                'predicted_home_score' => 3,
                'predicted_away_score' => 1,
            ])
            ->assertCreated();

        $match->forceFill([
            'status' => 'finished',
            'home_score' => 3,
            'away_score' => 1,
        ])->save();

        $this->artisan('world-cup:score-predictions')
            ->expectsOutput('Palpites apurados: 1')
            ->assertSuccessful();

        $prediction = Prediction::firstOrFail();

        $this->assertSame('settled', $prediction->status);
        $this->assertSame(5, $prediction->points_awarded);
        $this->assertSame('exact_score', $prediction->score_reason);

        $this->actingAs($user)
            ->getJson("/api/v1/leagues/{$league->id}/ranking")
            ->assertOk()
            ->assertJsonPath('data.rankings.0.total_points', 5)
            ->assertJsonPath('data.rankings.0.exact_scores', 1);

        $payload = (new LeagueRankingUpdated($league->refresh()))->broadcastWith();
        $channels = (new LeagueRankingUpdated($league->refresh()))->broadcastOn();

        $this->assertSame($league->id, $payload['league_id']);
        $this->assertSame($user->id, $payload['rankings'][0]['member']['user']['id']);
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame("private-league.{$league->id}", $channels[0]->name);
    }

    public function test_prediction_scored_event_is_only_sent_to_private_league_channel(): void
    {
        $user = User::factory()->create();
        $league = $this->createLeagueFor($user);
        $match = $this->createMatch(now()->subDay(), 'finished');
        $member = $league->members()->where('user_id', $user->id)->firstOrFail();
        $prediction = Prediction::create([
            'league_id' => $league->id,
            'league_member_id' => $member->id,
            'match_id' => $match->id,
            'predicted_home_score' => 1,
            'predicted_away_score' => 0,
            'status' => 'settled',
            'submitted_at' => now()->subDays(2),
            'locked_at' => now()->subDay(),
            'scored_at' => now(),
            'points_awarded' => 5,
            'score_reason' => 'exact_score',
            'prediction_version' => 1,
        ]);

        $channels = (new WorldCupPredictionScored($prediction))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame("private-league.{$league->id}", $channels[0]->name);
    }

    public function test_prediction_lock_reached_event_is_broadcast_once(): void
    {
        Cache::flush();
        Event::fake([WorldCupPredictionLockReached::class]);

        $user = User::factory()->create();
        $league = $this->createLeagueFor($user);
        $league->settings()->update([
            'prediction_lock_minutes_before_start' => 30,
        ]);
        $match = $this->createMatch(now()->addMinutes(30));

        $this->artisan('world-cup:broadcast-prediction-locks')
            ->expectsOutput('Eventos de bloqueio emitidos: 1')
            ->assertSuccessful();

        $this->artisan('world-cup:broadcast-prediction-locks')
            ->expectsOutput('Eventos de bloqueio emitidos: 0')
            ->assertSuccessful();

        Event::assertDispatchedTimes(WorldCupPredictionLockReached::class, 1);
        Event::assertDispatched(
            WorldCupPredictionLockReached::class,
            fn (WorldCupPredictionLockReached $event): bool => $event->league->is($league) && $event->match->is($match),
        );
    }

    private function createLeagueFor(User $user): League
    {
        $this->actingAs($user)
            ->postJson('/api/v1/leagues', [
                'name' => 'Liga dos Palpites',
                'visibility' => 'public',
                'max_members' => 8,
            ])
            ->assertCreated();

        return League::firstOrFail();
    }

    private function createMatch($startsAt, string $status = 'scheduled', string $homeName = 'Brasil', string $awayName = 'Argentina'): WorldCupMatch
    {
        $edition = TournamentEdition::create([
            'name' => 'WM 2026',
            'season' => 2026,
            'provider' => 'openligadb',
            'provider_league_id' => 'wm26',
            'status' => 'synced',
        ]);
        $group = TournamentGroup::create([
            'tournament_edition_id' => $edition->id,
            'name' => 'Fase de grupos',
            'external_name' => 'Group Stage',
            'internal_code' => 'group_stage',
            'display_name' => 'Fase de grupos',
        ]);
        $home = Team::create([
            'name' => $homeName,
            'display_name_pt_br' => $homeName,
            'provider' => 'openligadb',
            'provider_team_id' => '10',
        ]);
        $away = Team::create([
            'name' => $awayName,
            'display_name_pt_br' => $awayName,
            'provider' => 'openligadb',
            'provider_team_id' => '20',
        ]);

        return WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'tournament_group_id' => $group->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'match-1',
            'starts_at' => $startsAt,
            'status' => $status,
        ]);
    }

    private function createKnockoutMatch($startsAt, string $status = 'scheduled'): WorldCupMatch
    {
        $edition = TournamentEdition::create([
            'name' => 'WM 2026',
            'season' => 2026,
            'provider' => 'openligadb',
            'provider_league_id' => 'wm26',
            'status' => 'synced',
        ]);
        $group = TournamentGroup::create([
            'tournament_edition_id' => $edition->id,
            'name' => 'Oitavas de final',
            'external_name' => 'Achtelfinale',
            'internal_code' => 'round_of_16',
            'display_name' => 'Oitavas de final',
        ]);
        $home = Team::create([
            'name' => 'Belgica',
            'display_name_pt_br' => 'Belgica',
            'provider' => 'openligadb',
            'provider_team_id' => '110',
        ]);
        $away = Team::create([
            'name' => 'Senegal',
            'display_name_pt_br' => 'Senegal',
            'provider' => 'openligadb',
            'provider_team_id' => '120',
        ]);

        return WorldCupMatch::create([
            'tournament_edition_id' => $edition->id,
            'tournament_group_id' => $group->id,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'provider' => 'openligadb',
            'provider_fixture_id' => 'knockout-match-1',
            'round' => 'Achtelfinale',
            'starts_at' => $startsAt,
            'status' => $status,
        ]);
    }
}
