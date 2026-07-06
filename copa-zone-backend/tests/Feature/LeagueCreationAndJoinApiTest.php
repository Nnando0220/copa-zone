<?php

namespace Tests\Feature;

use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeagueCreationAndJoinApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_public_league_with_default_settings_and_owner_membership(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJsonWithCsrf('/api/v1/leagues', [
                'name' => 'Liga Publica dos Amigos',
                'visibility' => 'public',
                'max_members' => 24,
            ])
            ->assertCreated()
            ->assertJsonPath('data.league.name', 'Liga Publica dos Amigos')
            ->assertJsonPath('data.league.visibility', 'public')
            ->assertJsonPath('data.league.join_policy', 'open')
            ->assertJsonPath('data.league.invite_code', null)
            ->assertJsonPath('data.league.is_owner', true)
            ->assertJsonPath('data.league.membership.role', 'owner')
            ->assertJsonPath('data.league.settings.points_correct_outcome', 3)
            ->assertJsonPath('data.league.settings.points_wrong_outcome', 0)
            ->assertJsonPath('data.league.settings.allow_prediction_cancellation', true)
            ->assertJsonPath('data.league.settings.late_join_enabled', true)
            ->assertJsonPath('data.league.settings.ranking_visibility', 'members')
            ->assertJsonMissingPath('data.league.settings.initial_credits')
            ->assertJsonMissingPath('data.league.settings.minimum_stake')
            ->assertJsonMissingPath('data.league.settings.maximum_stake')
            ->assertJsonMissingPath('data.league.settings.reward_multiplier');

        $leagueId = $response->json('data.league.id');

        $this->assertTrue(Str::isUuid($user->id));
        $this->assertTrue(Str::isUuid($leagueId));
        $this->assertDatabaseHas('league_members', [
            'league_id' => $leagueId,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('league_settings', [
            'league_id' => $leagueId,
            'points_correct_outcome' => 3,
            'points_wrong_outcome' => 0,
        ]);
    }

    public function test_league_creation_does_not_create_wallet_or_balance(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJsonWithCsrf('/api/v1/leagues', [
                'name' => 'Liga Sem Carteira',
                'visibility' => 'public',
                'max_members' => 8,
            ])
            ->assertCreated();

        $leagueId = $response->json('data.league.id');

        $this->assertDatabaseHas('league_settings', [
            'league_id' => $leagueId,
            'points_correct_outcome' => 3,
            'points_wrong_outcome' => 0,
        ]);

        $this->assertFalse(Schema::hasColumn('league_settings', 'initial_credits'));
        $this->assertFalse(Schema::hasColumn('league_settings', 'minimum_stake'));
        $this->assertFalse(Schema::hasColumn('league_settings', 'maximum_stake'));
        $this->assertFalse(Schema::hasColumn('league_settings', 'reward_multiplier'));

        $settingsJson = $response->json('data.league.settings');
        $this->assertArrayNotHasKey('initial_credits', $settingsJson);
        $this->assertArrayNotHasKey('minimum_stake', $settingsJson);
        $this->assertArrayNotHasKey('maximum_stake', $settingsJson);
        $this->assertArrayNotHasKey('reward_multiplier', $settingsJson);
    }

    public function test_league_creation_rejects_more_than_thirty_two_members(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJsonWithCsrf('/api/v1/leagues', [
                'name' => 'Liga Grande Demais',
                'visibility' => 'public',
                'max_members' => 33,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('max_members');
    }

    public function test_private_league_gets_automatic_invite_code_and_is_not_publicly_listed(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $response = $this->actingAs($owner)
            ->postJsonWithCsrf('/api/v1/leagues', [
                'name' => 'Liga Privada',
                'visibility' => 'private',
                'max_members' => 16,
            ])
            ->assertCreated()
            ->assertJsonPath('data.league.visibility', 'private')
            ->assertJsonPath('data.league.join_policy', 'invite_code');

        $inviteCode = $response->json('data.league.invite_code');

        $this->assertIsString($inviteCode);
        $this->assertSame(8, strlen($inviteCode));

        $this->actingAs($outsider)
            ->getJson('/api/v1/leagues/public')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_can_join_public_league(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $league = League::create([
            'owner_user_id' => $owner->id,
            'name' => 'Liga Publica',
            'visibility' => 'public',
            'join_policy' => 'open',
            'status' => 'open',
            'max_members' => 4,
        ]);

        LeagueMember::create([
            'league_id' => $league->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($participant)
            ->postJsonWithCsrf("/api/v1/leagues/{$league->id}/join")
            ->assertOk()
            ->assertJsonPath('data.league.membership.role', 'participant')
            ->assertJsonPath('data.league.members_count', 2);
    }

    public function test_user_can_join_private_league_by_code(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $league = League::create([
            'owner_user_id' => $owner->id,
            'name' => 'Liga Privada',
            'visibility' => 'private',
            'join_policy' => 'invite_code',
            'invite_code' => 'ABC12345',
            'status' => 'open',
            'max_members' => 4,
        ]);

        LeagueMember::create([
            'league_id' => $league->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($participant)
            ->postJsonWithCsrf('/api/v1/leagues/join-by-code', ['invite_code' => 'abc12345'])
            ->assertOk()
            ->assertJsonPath('data.league.id', $league->id)
            ->assertJsonPath('data.league.membership.role', 'participant');
    }

    public function test_private_invite_code_is_only_returned_to_league_owner(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $league = League::create([
            'owner_user_id' => $owner->id,
            'name' => 'Liga com Convite',
            'visibility' => 'private',
            'join_policy' => 'invite_code',
            'invite_code' => 'SAFE2026',
            'status' => 'open',
            'max_members' => 8,
        ]);

        LeagueMember::create([
            'league_id' => $league->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        LeagueMember::create([
            'league_id' => $league->id,
            'user_id' => $participant->id,
            'role' => 'participant',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)
            ->getJson("/api/v1/leagues/{$league->id}")
            ->assertOk()
            ->assertJsonPath('data.league.invite_code', 'SAFE2026')
            ->assertJsonPath('data.league.is_owner', true);

        $this->actingAs($participant)
            ->getJson("/api/v1/leagues/{$league->id}")
            ->assertOk()
            ->assertJsonPath('data.league.invite_code', null)
            ->assertJsonPath('data.league.is_owner', false);
    }

    public function test_user_can_preview_private_league_by_code_before_joining(): void
    {
        $owner = User::factory()->create(['name' => 'Gestor CopaZone']);
        $participant = User::factory()->create();
        $league = League::create([
            'owner_user_id' => $owner->id,
            'name' => 'Liga do Convite',
            'visibility' => 'private',
            'join_policy' => 'invite_code',
            'invite_code' => 'PREV1234',
            'status' => 'open',
            'max_members' => 8,
        ]);

        LeagueMember::create([
            'league_id' => $league->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($participant)
            ->postJsonWithCsrf('/api/v1/leagues/invites/preview', ['invite_code' => 'prev1234'])
            ->assertOk()
            ->assertJsonPath('data.league.id', $league->id)
            ->assertJsonPath('data.league.name', 'Liga do Convite')
            ->assertJsonPath('data.league.owner_name', 'Gestor CopaZone')
            ->assertJsonPath('meta.already_member', false);
    }

    public function test_preview_marks_when_user_already_participates_in_private_league(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $league = League::create([
            'owner_user_id' => $owner->id,
            'name' => 'Liga Repetida',
            'visibility' => 'private',
            'join_policy' => 'invite_code',
            'invite_code' => 'JOINED99',
            'status' => 'open',
            'max_members' => 8,
        ]);

        LeagueMember::create([
            'league_id' => $league->id,
            'user_id' => $participant->id,
            'role' => 'participant',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($participant)
            ->postJsonWithCsrf('/api/v1/leagues/invites/preview', ['invite_code' => 'joined99'])
            ->assertOk()
            ->assertJsonPath('data.league.id', $league->id)
            ->assertJsonPath('meta.already_member', true)
            ->assertJsonPath('message', 'Você já faz parte dessa liga.');
    }

    public function test_user_cannot_join_same_league_twice(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $league = League::create([
            'owner_user_id' => $owner->id,
            'name' => 'Liga Publica',
            'visibility' => 'public',
            'join_policy' => 'open',
            'status' => 'open',
            'max_members' => 4,
        ]);

        LeagueMember::create([
            'league_id' => $league->id,
            'user_id' => $participant->id,
            'role' => 'participant',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($participant)
            ->postJsonWithCsrf("/api/v1/leagues/{$league->id}/join")
            ->assertUnprocessable();
    }

    public function test_user_cannot_join_full_league(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $league = League::create([
            'owner_user_id' => $owner->id,
            'name' => 'Liga Cheia',
            'visibility' => 'public',
            'join_policy' => 'open',
            'status' => 'open',
            'max_members' => 1,
        ]);

        LeagueMember::create([
            'league_id' => $league->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($participant)
            ->postJsonWithCsrf("/api/v1/leagues/{$league->id}/join")
            ->assertUnprocessable();
    }

    public function test_private_league_is_not_visible_to_non_member(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $league = League::create([
            'owner_user_id' => $owner->id,
            'name' => 'Liga Privada',
            'visibility' => 'private',
            'join_policy' => 'invite_code',
            'invite_code' => 'CODE1234',
            'status' => 'open',
        ]);

        $this->actingAs($outsider)
            ->getJson("/api/v1/leagues/{$league->id}")
            ->assertNotFound();
    }
}
