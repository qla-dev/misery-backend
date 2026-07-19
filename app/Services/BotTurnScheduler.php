<?php

namespace App\Services;

use App\Jobs\PlayAutomatedBotTurn;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

class BotTurnScheduler
{
    public function schedule(Game|int $game): void
    {
        $gameId = $game instanceof Game ? (int) $game->id : $game;

        DB::afterCommit(function () use ($gameId) {
            PlayAutomatedBotTurn::dispatch($gameId)->delay(now()->addMilliseconds(random_int(650, 1600)));
        });
    }
}
