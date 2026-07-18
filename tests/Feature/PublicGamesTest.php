<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
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
            ->assertSee('miseryindex:///code/ABCD1234', false)
            ->assertSee('Join my Misery Meter room', false)
            ->assertSee(url('/favicon.ico'), false)
            ->assertSee(url('/misery-og.png'), false);

        $this->get('/favicon.ico')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_public_games_include_active_started_games_without_finished_games(): void
    {
        $lobbyHost = User::factory()->create();
        $startedHost = User::factory()->create();
        $lobby = Game::create(['code' => 'OPEN1234', 'owner_id' => $lobbyHost->id, 'started' => false]);
        $started = Game::create(['code' => 'PLAY1234', 'owner_id' => $startedHost->id, 'started' => true]);
        $finished = Game::create(['code' => 'DONE1234', 'owner_id' => $startedHost->id, 'started' => true, 'winner_id' => $startedHost->id]);
        $replay = Game::create(['code' => 'AGAIN123', 'owner_id' => $startedHost->id, 'started' => true, 'winner_id' => $startedHost->id]);
        $lobby->members()->attach($lobbyHost);
        $started->members()->attach($startedHost);
        $finished->members()->attach($startedHost, ['in_lobby' => false]);
        $replay->members()->attach($startedHost, ['in_lobby' => true]);

        $this->getJson('/api/games')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['code' => 'OPEN1234', 'started' => false])
            ->assertJsonFragment(['code' => 'PLAY1234', 'started' => true])
            ->assertJsonFragment(['code' => 'AGAIN123', 'winner_id' => $startedHost->id])
            ->assertJsonMissing(['code' => 'DONE1234']);
    }

    public function test_new_player_can_join_replay_lobby_but_not_active_game(): void
    {
        $host = User::factory()->create(['color' => 'yellow']);
        $active = Game::create(['code' => 'ACTIVE12', 'owner_id' => $host->id, 'started' => true]);
        $active->members()->attach($host, ['in_lobby' => false]);

        $this->postJson('/api/games/code/ACTIVE12/join', ['name' => 'Blocked'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Game already started.');

        $replay = Game::create(['code' => 'REPLAY12', 'owner_id' => $host->id, 'started' => true, 'winner_id' => $host->id]);
        $replay->members()->attach($host, ['in_lobby' => true]);

        $joined = $this->postJson('/api/games/code/REPLAY12/join', ['name' => 'New player', 'color' => 'blue'])
            ->assertCreated()
            ->assertJsonPath('game.started', true)
            ->assertJsonPath('game.winner_id', $host->id);

        $this->assertDatabaseHas('members', [
            'game_id' => $replay->id,
            'user_id' => $joined->json('user.id'),
            'in_lobby' => true,
        ]);
    }
}
