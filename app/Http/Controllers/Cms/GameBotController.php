<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameBotController extends Controller
{
    private const COLORS = ['blue', 'emerald', 'purple', 'rose', 'orange', 'brown', 'silver'];

    public function store(Request $request, Game $game): JsonResponse
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:7'],
        ]);
        $count = (int) $data['count'];

        DB::transaction(function () use ($game, $count) {
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
            abort_if($memberCount + $count > config('game.max_players'), 422, 'The lobby does not have enough free player slots.');

            $usedNames = DB::table('members')
                ->join('users', 'users.id', '=', 'members.user_id')
                ->where('members.game_id', $game->id)
                ->pluck('users.name')
                ->all();
            $names = array_values(array_diff(array_unique(config('game.synthetic_player_names', [])), $usedNames));
            abort_if(count($names) < $count, 422, 'There are not enough bot names configured.');
            shuffle($names);

            foreach (array_slice(self::COLORS, 0, $count) as $index => $color) {
                $bot = User::create([
                    'name' => $names[$index],
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

        return response()->json([
            'message' => $count.' autonomous '.($count === 1 ? 'bot was' : 'bots were').' added to the lobby.',
            'count' => $count,
        ]);
    }
}
