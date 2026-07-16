<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Move;
use App\Models\User;
use App\Services\GameCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GameCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_deletes_only_orphaned_lobbies_and_started_games_without_recent_moves(): void
    {
        config([
            'game.host_lobby_inactivity_timeout_seconds' => 120,
            'game.started_game_move_timeout_seconds' => 180,
        ]);

        $staleLobby = $this->game('OLDL1001', false);
        DB::table('members')->where('game_id', $staleLobby->id)->update(['updated_at' => now()->subSeconds(121)]);

        $activeLobby = $this->game('LIVE1001', false);
        $guest = User::factory()->create();
        $activeLobby->members()->attach($guest, ['updated_at' => now()->subHour()]);

        $staleStarted = $this->game('OLDS1001', true);
        DB::table('games')->where('id', $staleStarted->id)->update(['updated_at' => now()->subSeconds(181)]);

        $recentlyStarted = $this->game('NEWS1001', true);
        DB::table('games')->where('id', $recentlyStarted->id)->update(['updated_at' => now()->subSeconds(60)]);

        $startedWithRecentMove = $this->game('MOVE1001', true);
        Move::create(['game_id' => $startedWithRecentMove->id, 'player_id' => $startedWithRecentMove->owner_id, 'correct' => true]);
        DB::table('games')->where('id', $startedWithRecentMove->id)->update(['updated_at' => now()->subSeconds(181)]);

        $startedWithOldMove = $this->game('OLDM1001', true);
        $oldMove = Move::create(['game_id' => $startedWithOldMove->id, 'player_id' => $startedWithOldMove->owner_id, 'correct' => false]);
        DB::table('moves')->where('id', $oldMove->id)->update(['created_at' => now()->subSeconds(181), 'updated_at' => now()->subSeconds(181)]);
        DB::table('games')->where('id', $startedWithOldMove->id)->update(['updated_at' => now()->subSeconds(181)]);

        $finishedGame = $this->game('DONE1001', true);
        DB::table('games')->where('id', $finishedGame->id)->update([
            'winner_id' => $finishedGame->owner_id,
            'updated_at' => now()->subSeconds(181),
        ]);

        $result = app(GameCleanupService::class)->cleanup();

        $this->assertSame(1, $result['lobby_games_deleted']);
        $this->assertSame(3, $result['started_games_deleted']);
        $this->assertDatabaseMissing('games', ['id' => $staleLobby->id]);
        $this->assertDatabaseMissing('games', ['id' => $staleStarted->id]);
        $this->assertDatabaseMissing('games', ['id' => $startedWithOldMove->id]);
        $this->assertDatabaseMissing('games', ['id' => $finishedGame->id]);
        $this->assertDatabaseHas('games', ['id' => $activeLobby->id]);
        $this->assertDatabaseHas('games', ['id' => $recentlyStarted->id]);
        $this->assertDatabaseHas('games', ['id' => $startedWithRecentMove->id]);
        $this->assertDatabaseHas('members', ['game_id' => $activeLobby->id, 'user_id' => $guest->id]);
    }

    private function game(string $code, bool $started): Game
    {
        $host = User::factory()->create();
        $game = Game::create(['code' => $code, 'owner_id' => $host->id, 'started' => $started, 'sync_driver' => 'polling']);
        $game->members()->attach($host, ['updated_at' => now()]);

        return $game;
    }
}
