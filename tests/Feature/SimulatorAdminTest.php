<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulatorAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_force_delete_a_started_game_from_the_simulator(): void
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

        $this->delete("/simulator/rooms/{$game->id}")->assertUnauthorized();
        $this->withServerVariables($server)
            ->delete("/simulator/rooms/{$game->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('games', ['id' => $game->id]);
    }
}
