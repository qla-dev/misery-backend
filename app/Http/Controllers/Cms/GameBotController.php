<?php

namespace App\Http\Controllers\Cms;

use App\Events\GameUpdated;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use App\Services\GameRealtimePayload;
use App\Services\RealtimeTransportAllocator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            $names = $this->selectNames($count, $usedNames);

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

        $this->broadcastBotsAdded($game->refresh());

        return response()->json([
            'message' => $count.' autonomous '.($count === 1 ? 'bot was' : 'bots were').' added to the lobby.',
            'count' => $count,
        ]);
    }

    private function selectNames(int $count, array $usedNames): array
    {
        $allNames = array_values(array_diff(array_unique(config('game.synthetic_player_names', [])), $usedNames));
        abort_if(count($allNames) < $count, 422, 'There are not enough bot names configured.');
        shuffle($allNames);
        if ($count < 2) {
            return array_slice($allNames, 0, $count);
        }

        $usNames = array_values(array_intersect($allNames, config('game.us_player_names', [])));
        $bosnianNames = array_values(array_intersect($allNames, config('game.bosnian_player_names', [])));
        abort_if($usNames === [] || $bosnianNames === [], 422, 'Both Bosnian and US bot name sets must be configured.');
        shuffle($usNames);
        shuffle($bosnianNames);
        $selected = [$bosnianNames[0], $usNames[0]];
        $remaining = array_values(array_diff($allNames, $selected));
        shuffle($remaining);

        return [...$selected, ...array_slice($remaining, 0, $count - 2)];
    }

    private function broadcastBotsAdded(Game $game): void
    {
        $driver = config('game.reverb_override') ? 'reverb' : ($game->sync_driver ?: 'polling');
        if (! in_array($driver, ['pusher', 'ably', 'reverb'], true)) {
            return;
        }

        DB::afterCommit(function () use ($game, $driver) {
            dispatch(function () use ($game, $driver) {
                try {
                    $payload = app(GameRealtimePayload::class)->build((int) $game->id, 'bots.added');
                    if ($driver === 'ably') {
                        app(RealtimeTransportAllocator::class)->publishAblyGameUpdate((int) $game->id, 'bots.added', $payload);
                    } else {
                        GameUpdated::dispatch((int) $game->id, 'bots.added', $driver, $payload);
                    }
                } catch (\Throwable $error) {
                    Log::error('Bot lobby realtime update failed', [
                        'game_id' => $game->id,
                        'driver' => $driver,
                        'message' => $error->getMessage(),
                    ]);
                }
            })->afterResponse();
        });
    }
}
