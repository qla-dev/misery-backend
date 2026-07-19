<?php

namespace App\Services;

use App\Http\Controllers\Api\GameController;
use App\Jobs\PlayAutomatedBotTurn;
use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class BotTurnScheduler
{
    public function schedule(Game|int $game): void
    {
        $gameId = $game instanceof Game ? (int) $game->id : $game;
        $pendingKey = "game:{$gameId}:bot-turn-scheduled";

        // Shared hosting runs no persistent queue worker. Deduplicate the task,
        // then execute one bot decision after the HTTP response in this PHP
        // process so database-queued bot jobs can never sit unprocessed.
        if (! Cache::add($pendingKey, true, 10)) {
            return;
        }

        DB::afterCommit(function () use ($gameId, $pendingKey) {
            dispatch(function () use ($gameId, $pendingKey): void {
                try {
                    $minimum = (int) config('game.bot_turn_delay_min_ms', 3000);
                    $maximum = max($minimum, (int) config('game.bot_turn_delay_max_ms', 6000));
                    $delay = random_int($minimum, $maximum);
                    if ($delay > 0) {
                        usleep($delay * 1000);
                    }

                    (new PlayAutomatedBotTurn($gameId))->handle(app(GameController::class));
                } catch (Throwable $error) {
                    Log::error('Automated bot turn failed', [
                        'game_id' => $gameId,
                        'message' => $error->getMessage(),
                    ]);
                } finally {
                    Cache::forget($pendingKey);
                }
            })->afterResponse();
        });
    }
}
