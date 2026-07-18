<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulatorAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulator_sends_csrf_token_with_admin_requests(): void
    {
        $server = [
            'PHP_AUTH_USER' => config('cms.username'),
            'PHP_AUTH_PW' => config('cms.password'),
        ];

        $this->withServerVariables($server)
            ->get('/simulator')
            ->assertOk()
            ->assertSee("'X-CSRF-TOKEN':csrfToken", false);
    }

    public function test_simulator_uses_the_atomic_move_flow_without_finish_turn(): void
    {
        $server = [
            'PHP_AUTH_USER' => config('cms.username'),
            'PHP_AUTH_PW' => config('cms.password'),
        ];

        $this->withServerVariables($server)
            ->get('/simulator')
            ->assertOk()
            ->assertSee('movePending=true', false)
            ->assertDontSee('finishTurn', false)
            ->assertDontSee('awaiting_finish', false)
            ->assertDontSee('/finish-turn', false);
    }

    public function test_admin_api_can_force_delete_a_started_game_without_csrf(): void
    {
        $host = User::create(['name' => 'Host']);
        $game = Game::create([
            'code' => 'ABCD1234',
            'owner_id' => $host->id,
            'started' => true,
        ]);
        $server = [
            'PHP_AUTH_USER' => config('cms.username'),
            'PHP_AUTH_PW' => config('cms.password'),
        ];

        $this->deleteJson("/api/admin/simulator/rooms/{$game->id}")->assertUnauthorized();
        $this->withServerVariables($server)
            ->deleteJson("/api/admin/simulator/rooms/{$game->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('games', ['id' => $game->id]);
    }

    public function test_simulator_kill_button_uses_the_csrf_free_admin_api_route(): void
    {
        $server = [
            'PHP_AUTH_USER' => config('cms.username'),
            'PHP_AUTH_PW' => config('cms.password'),
        ];

        $this->withServerVariables($server)
            ->get('/simulator')
            ->assertOk()
            ->assertSee('simulatorRoomDeleteUrl.replace', false)
            ->assertSee('admin\\/simulator\\/rooms', false);
    }

    public function test_legacy_admin_delete_route_remains_available_for_existing_clients(): void
    {
        $host = User::create(['name' => 'Legacy Host']);
        $game = Game::create([
            'code' => 'OLDX1234',
            'owner_id' => $host->id,
            'started' => true,
        ]);
        $server = [
            'PHP_AUTH_USER' => config('cms.username'),
            'PHP_AUTH_PW' => config('cms.password'),
        ];

        $this->withServerVariables($server)
            ->delete("/simulator/rooms/{$game->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('games', ['id' => $game->id]);
    }
}
