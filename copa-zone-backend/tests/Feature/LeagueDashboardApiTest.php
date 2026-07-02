<?php

namespace Tests\Feature;

use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeagueDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_user_leagues_and_public_suggestions(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $privateLeague = League::create([
            'owner_user_id' => $otherUser->id,
            'name' => 'Liga Privada dos Amigos',
            'visibility' => 'private',
            'join_policy' => 'invite_code',
            'invite_code' => 'AMIGOS26',
            'status' => 'open',
        ]);

        LeagueMember::create([
            'league_id' => $privateLeague->id,
            'user_id' => $user->id,
            'role' => 'participant',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $publicLeague = League::create([
            'owner_user_id' => $otherUser->id,
            'name' => 'Liga Publica Brasil',
            'visibility' => 'public',
            'join_policy' => 'open',
            'status' => 'open',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.my_leagues_count', 1)
            ->assertJsonPath('data.summary.private_leagues_count', 1)
            ->assertJsonPath('data.my_leagues.0.name', $privateLeague->name)
            ->assertJsonPath('data.public_leagues.0.name', $publicLeague->name);
    }

    public function test_public_leagues_do_not_expose_private_leagues(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();

        League::create([
            'owner_user_id' => $owner->id,
            'name' => 'Liga Publica',
            'visibility' => 'public',
            'status' => 'open',
        ]);

        League::create([
            'owner_user_id' => $owner->id,
            'name' => 'Liga Privada',
            'visibility' => 'private',
            'status' => 'open',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/leagues/public')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Liga Publica');
    }

    public function test_public_leagues_page_includes_public_leagues_user_already_participates_in(): void
    {
        $owner = User::factory()->create();

        $league = League::create([
            'owner_user_id' => $owner->id,
            'name' => 'Minha Liga Publica',
            'visibility' => 'public',
            'join_policy' => 'open',
            'status' => 'open',
        ]);

        LeagueMember::create([
            'league_id' => $league->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($owner)
            ->getJson('/api/v1/leagues/public')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Minha Liga Publica')
            ->assertJsonPath('data.0.membership.role', 'owner')
            ->assertJsonPath('data.0.is_owner', true);
    }

    public function test_private_league_is_visible_in_my_leagues_only_for_member(): void
    {
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $owner = User::factory()->create();

        $privateLeague = League::create([
            'owner_user_id' => $owner->id,
            'name' => 'Liga Privada Inscrita',
            'visibility' => 'private',
            'status' => 'open',
        ]);

        LeagueMember::create([
            'league_id' => $privateLeague->id,
            'user_id' => $member->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($member)
            ->getJson('/api/v1/leagues')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Liga Privada Inscrita');

        $this->actingAs($outsider)
            ->getJson('/api/v1/leagues')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
