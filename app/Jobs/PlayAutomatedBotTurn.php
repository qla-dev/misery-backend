<?php

namespace App\Jobs;

use App\Http\Controllers\Api\GameController;
use App\Models\Game;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class PlayAutomatedBotTurn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $gameId) {}

    public function handle(GameController $controller): void
    {
        Cache::lock("game:{$this->gameId}:bot-turn", 10)->block(3, function () use ($controller) {
            $game = Game::query()->with('currentPlayer')->find($this->gameId);
            if (! $game || ! $game->started || $game->terminated_at || $game->winner_id || ! $game->currentPlayer?->is_bot) {
                return;
            }

            $playerId = (int) $game->current_player_id;
            // Match tools/game-chaos: steal offers pass 28% of the time; every
            // attempted placement is correct 52% of the time.
            if ($game->is_steal_turn && random_int(1, 10_000) <= 2800) {
                $controller->passSteal(Request::create('/', 'POST', ['player_id' => $playerId]), $game);
                return;
            }

            $correct = random_int(1, 10_000) <= 5200;
            $controller->move(Request::create('/', 'POST', [
                'player_id' => $playerId,
                'correct' => $correct,
            ]), $game);
        });
    }
}
