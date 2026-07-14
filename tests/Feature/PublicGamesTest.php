<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicGamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_games_only_include_rooms_with_fewer_than_eight_players(): void
    {
        $create = $this->postJson('/api/games', ['name' => 'Host', 'color' => 'yellow'])
            ->assertCreated();
        $code = $create->json('game.code');

        foreach (range(2, 8) as $player) {
            $this->postJson("/api/games/code/{$code}/join", [
                'name' => "Player {$player}",
                'color' => 'yellow',
            ])->assertCreated();
        }

        $this->getJson('/api/games')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson("/api/games/code/{$code}/join", [
            'name' => 'Player 9',
            'color' => 'blue',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'No more available seats in this room.');
    }

    public function test_room_deep_link_has_an_app_fallback(): void
    {
        $this->get('/code/ABCD1234')
            ->assertOk()
            ->assertSee('miseryindex:///code/ABCD1234', false);
    }
}
