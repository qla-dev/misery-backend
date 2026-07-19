<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GameBotController extends Controller
{
    private const COLORS = ['blue', 'emerald', 'purple', 'rose', 'orange', 'brown', 'silver'];

    public function store(Game $game): JsonResponse
    {
        DB::transaction(function () use ($game) {
            $game = Game::query()->lockForUpdate()->findOrFail($game->id);
            abort_if($game->started || $game->terminated_at, 422, 'Bots can only be added to an open lobby.');

            $memberCount = DB::table('members')->where('game_id', $game->id)->count();
            $existingBotCount = DB::table('members')
                ->join('users', 'users.id', '=', 'members.user_id')
                ->where('members.game_id', $game->id)
                ->where('users.is_bot', true)
                ->count();
            abort_if($existingBotCount > 0, 422, 'This lobby already has bots.');
            abort_unless($memberCount === 1, 422, 'The lobby must contain exactly one human player.');
            abort_if($memberCount + 7 > config('game.max_players'), 422, 'The lobby needs seven free player slots.');

            foreach (self::COLORS as $index => $color) {
                $bot = User::create([
                    'name' => 'BOT '.($index + 1),
                    'color' => $color,
                    'is_bot' => true,
                ]);
                DB::table('members')->insert([
                    'game_id' => $game->id,
                    'user_id' => $bot->id,
                    'in_lobby' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json(['message' => 'Seven autonomous bots were added to the lobby.']);
    }
}
