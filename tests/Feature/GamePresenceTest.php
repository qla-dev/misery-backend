<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GamePresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_non_host_is_removed_and_the_remaining_player_can_continue(): void
    {
        $host = User::factory()->create(['color' => 'yellow']);
        $inactive = User::factory()->create(['color' => 'blue']);
        $currentCard = Card::create(['title' => 'Current', 'score' => 10, 'status' => true]);
        Card::create(['title' => 'Next', 'score' => 20, 'status' => true]);
        $game = Game::create([
            'code' => 'LIVE1234',
            'owner_id' => $host->id,
            'started' => true,
            'current_card_id' => $currentCard->id,
            'current_player_id' => $inactive->id,
            'turn_owner_id' => $inactive->id,
        ]);
        $game->members()->attach([$host->id, $inactive->id]);
        DB::table('members')->where('game_id', $game->id)->where('user_id', $inactive->id)
            ->update(['updated_at' => now()->subSeconds(61)]);

        $this->getJson("/api/games/{$game->id}?user_id={$host->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.members')
            ->assertJsonPath('data.members.0.id', $host->id);

        $game->refresh();
        $this->assertNull($game->terminated_at);
        $this->assertSame($host->id, $game->current_player_id);
        $this->assertSame($host->id, $game->turn_owner_id);
        $this->assertFalse($game->is_steal_turn);
    }

    public function test_inactive_host_terminates_game_and_removes_every_member(): void
    {
        $host = User::factory()->create(['color' => 'yellow']);
        $guest = User::factory()->create(['color' => 'blue']);
        $game = Game::create(['code' => 'HOST1234', 'owner_id' => $host->id, 'started' => true]);
        $game->members()->attach([$host->id, $guest->id]);
        DB::table('members')->where('game_id', $game->id)->where('user_id', $host->id)
            ->update(['updated_at' => now()->subSeconds(61)]);

        $this->getJson("/api/games/{$game->id}?user_id={$guest->id}")
            ->assertOk()
            ->assertJsonPath('data.termination_reason', 'host_inactive')
            ->assertJsonCount(0, 'data.members');

        $this->assertDatabaseCount('members', 0);
        $this->assertNotNull($game->refresh()->terminated_at);
    }

    public function test_lobby_members_are_not_removed_by_request_driven_inactivity_checks(): void
    {
        config([
            'game.member_inactivity_timeout_seconds' => 60,
            'game.host_lobby_inactivity_timeout_seconds' => 120,
        ]);
        $host = User::factory()->create(['color' => 'yellow']);
        $guest = User::factory()->create(['color' => 'blue']);
        $game = Game::create(['code' => 'WAIT1234', 'owner_id' => $host->id, 'started' => false]);
        $game->members()->attach([$host->id, $guest->id]);

        DB::table('members')->where('game_id', $game->id)->where('user_id', $host->id)
            ->update(['updated_at' => now()->subSeconds(61)]);

        $this->getJson("/api/games/{$game->id}?user_id={$guest->id}")
            ->assertOk()
            ->assertJsonPath('data.terminated_at', null)
            ->assertJsonCount(2, 'data.members');

        DB::table('members')->where('game_id', $game->id)
            ->update(['updated_at' => now()->subSeconds(121)]);

        $this->getJson("/api/games/{$game->id}?user_id={$guest->id}")
            ->assertOk()
            ->assertJsonPath('data.termination_reason', null)
            ->assertJsonCount(2, 'data.members');

        $this->postJson("/api/games/{$game->id}/inactivity-timeout", ['user_id' => $guest->id])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Inactivity removal only applies after the game has started.');

        $this->postJson("/api/games/{$game->id}/inactivity-timeout", ['user_id' => $host->id])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Inactivity removal only applies after the game has started.');
        $this->assertNull($game->fresh()->terminated_at);
    }

    public function test_host_leaving_intentionally_terminates_game(): void
    {
        $host = User::factory()->create(['color' => 'yellow']);
        $guest = User::factory()->create(['color' => 'blue']);
        $game = Game::create(['code' => 'LEFT1234', 'owner_id' => $host->id, 'started' => true]);
        $game->members()->attach([$host->id, $guest->id]);

        $this->postJson("/api/games/{$game->id}/leave", ['user_id' => $host->id])
            ->assertOk()
            ->assertJsonPath('data.termination_reason', 'host_left')
            ->assertJsonCount(0, 'data.members');

        $this->assertDatabaseCount('members', 0);
    }

    public function test_turn_timeout_immediately_removes_an_inactive_guest(): void
    {
        $host = User::factory()->create(['color' => 'yellow']);
        $guest = User::factory()->create(['color' => 'blue']);
        $game = Game::create([
            'code' => 'IDLE1234',
            'owner_id' => $host->id,
            'started' => true,
            'current_player_id' => $guest->id,
            'turn_owner_id' => $guest->id,
        ]);
        $game->members()->attach([$host->id, $guest->id]);

        $this->postJson("/api/games/{$game->id}/inactivity-timeout", ['user_id' => $guest->id])
            ->assertOk()
            ->assertJsonCount(1, 'data.members')
            ->assertJsonPath('data.members.0.id', $host->id);

        $this->assertDatabaseMissing('members', ['game_id' => $game->id, 'user_id' => $guest->id]);
        $this->assertSame($host->id, $game->refresh()->current_player_id);
    }

    public function test_turn_timeout_terminates_the_game_for_an_inactive_host(): void
    {
        $host = User::factory()->create(['color' => 'yellow']);
        $guest = User::factory()->create(['color' => 'blue']);
        $game = Game::create([
            'code' => 'IDLEHOST',
            'owner_id' => $host->id,
            'started' => true,
            'current_player_id' => $host->id,
            'turn_owner_id' => $host->id,
        ]);
        $game->members()->attach([$host->id, $guest->id]);

        $this->postJson("/api/games/{$game->id}/inactivity-timeout", ['user_id' => $host->id])
            ->assertOk()
            ->assertJsonPath('data.termination_reason', 'host_inactive')
            ->assertJsonCount(0, 'data.members');

        $this->assertNotNull($game->refresh()->terminated_at);
    }

    public function test_stale_turn_timeout_cannot_remove_a_player_after_the_turn_advances(): void
    {
        $host = User::factory()->create(['color' => 'yellow']);
        $guest = User::factory()->create(['color' => 'blue']);
        $game = Game::create([
            'code' => 'STALE123',
            'owner_id' => $host->id,
            'started' => true,
            'current_player_id' => $host->id,
            'turn_owner_id' => $host->id,
        ]);
        $game->members()->attach([$host->id, $guest->id]);

        $this->postJson("/api/games/{$game->id}/inactivity-timeout", ['user_id' => $guest->id])
            ->assertStatus(409);

        $this->assertDatabaseHas('members', ['game_id' => $game->id, 'user_id' => $guest->id]);

        $game->update(['current_player_id' => $guest->id, 'turn_owner_id' => $guest->id, 'awaiting_finish' => true]);
        $this->postJson("/api/games/{$game->id}/inactivity-timeout", ['user_id' => $guest->id])
            ->assertStatus(409);

        $this->assertDatabaseHas('members', ['game_id' => $game->id, 'user_id' => $guest->id]);
    }

    public function test_host_can_remove_a_guest_only_while_in_the_lobby(): void
    {
        $host = User::factory()->create(['color' => 'yellow']);
        $guest = User::factory()->create(['color' => 'blue']);
        $game = Game::create(['code' => 'KICK1234', 'owner_id' => $host->id, 'started' => false]);
        $game->members()->attach([$host->id, $guest->id]);

        $this->postJson("/api/games/{$game->id}/kick", ['user_id' => $host->id, 'player_id' => $guest->id])
            ->assertOk()
            ->assertJsonCount(1, 'data.members')
            ->assertJsonPath('data.members.0.id', $host->id);

        $this->assertDatabaseMissing('members', ['game_id' => $game->id, 'user_id' => $guest->id]);

        $game->members()->attach($guest->id);
        $game->update(['started' => true]);
        $this->postJson("/api/games/{$game->id}/kick", ['user_id' => $host->id, 'player_id' => $guest->id])
            ->assertUnprocessable();
        $this->assertDatabaseHas('members', ['game_id' => $game->id, 'user_id' => $guest->id]);
    }

    public function test_only_the_host_can_delete_an_unstarted_lobby(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $game = Game::create(['code' => 'DELETE12', 'owner_id' => $host->id, 'started' => false]);
        $game->members()->attach([$host->id, $guest->id]);

        $this->deleteJson("/api/games/{$game->id}", ['user_id' => $guest->id])->assertForbidden();
        $this->assertDatabaseHas('games', ['id' => $game->id]);

        $this->deleteJson("/api/games/{$game->id}", ['user_id' => $host->id])->assertNoContent();
        $this->assertDatabaseMissing('games', ['id' => $game->id]);

        $startedGame = Game::create(['code' => 'NODELETE', 'owner_id' => $host->id, 'started' => true]);
        $startedGame->members()->attach($host->id);
        $this->deleteJson("/api/games/{$startedGame->id}", ['user_id' => $host->id])
            ->assertStatus(409)
            ->assertJsonPath('message', 'A room cannot be deleted after the game has started.');
        $this->assertDatabaseHas('games', ['id' => $startedGame->id]);
    }

    public function test_guest_with_active_native_pro_can_lock_their_lobby_and_hide_it_from_public_games(): void
    {
        $host = User::factory()->create(['color' => 'yellow']);
        $game = Game::create(['code' => 'LOCK1234', 'owner_id' => $host->id, 'started' => false]);
        $game->members()->attach($host->id);

        $this->postJson("/api/games/{$game->id}/lock", ['user_id' => $host->id, 'pro_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_private', true);

        $this->getJson('/api/games')
            ->assertOk()
            ->assertJsonMissing(['id' => $game->id]);

        $this->postJson("/api/games/{$game->id}/lock", ['user_id' => $host->id, 'pro_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_private', false);

        $this->getJson('/api/games')
            ->assertOk()
            ->assertJsonFragment(['id' => $game->id]);
    }

    public function test_non_pro_cannot_lock_a_lobby(): void
    {
        $host = User::factory()->create(['color' => 'yellow']);
        $game = Game::create(['code' => 'OPEN1234', 'owner_id' => $host->id, 'started' => false]);
        $game->members()->attach($host->id);

        $this->postJson("/api/games/{$game->id}/lock", ['user_id' => $host->id, 'pro_active' => false])
            ->assertForbidden();

        $this->assertFalse($game->refresh()->is_private);
    }

    public function test_guest_cannot_remove_another_lobby_player(): void
    {
        $host = User::factory()->create(['color' => 'yellow']);
        $guest = User::factory()->create(['color' => 'blue']);
        $otherGuest = User::factory()->create(['color' => 'emerald']);
        $game = Game::create(['code' => 'SAFE1234', 'owner_id' => $host->id, 'started' => false]);
        $game->members()->attach([$host->id, $guest->id, $otherGuest->id]);

        $this->postJson("/api/games/{$game->id}/kick", ['user_id' => $guest->id, 'player_id' => $otherGuest->id])
            ->assertForbidden();
        $this->assertDatabaseHas('members', ['game_id' => $game->id, 'user_id' => $otherGuest->id]);
    }
}
