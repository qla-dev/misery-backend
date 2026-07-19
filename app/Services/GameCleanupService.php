<?php

namespace App\Services;

use App\Events\GameUpdated;
use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameCleanupService
{
    public function __construct(private readonly RealtimeTransportAllocator $transportAllocator) {}

    /** @return array{lobby_games_deleted:int, started_games_deleted:int, synthetic_games_created:int, synthetic_games_deleted:int, active_public_listings:int, total_deleted:int} */
    public function cleanup(): array
    {
        $lobbyCutoff = now()->subSeconds(config('game.host_lobby_inactivity_timeout_seconds'));
        $moveCutoff = now()->subSeconds(config('game.started_game_move_timeout_seconds'));

        $lobbyIds = DB::table('games')
            ->leftJoin('members as host_member', function ($join) {
                $join->on('host_member.game_id', '=', 'games.id')
                    ->on('host_member.user_id', '=', 'games.owner_id');
            })
            ->where('games.started', false)
            ->whereNull('games.terminated_at')
            ->where(function ($query) use ($lobbyCutoff) {
                $query->whereNull('host_member.id')->orWhere('host_member.updated_at', '<', $lobbyCutoff);
            })
            ->pluck('games.id');

        $startedIds = Game::query()
            ->where('started', true)
            ->where('is_synthetic', false)
            ->whereNull('terminated_at')
            ->where('updated_at', '<', $moveCutoff)
            ->whereDoesntHave('moves', fn ($query) => $query->where('created_at', '>=', $moveCutoff))
            ->pluck('id');

        $lobbyDeleted = $this->deleteGames($lobbyIds->all(), function (Game $game) use ($lobbyCutoff): bool {
            if ($game->started || $game->terminated_at) {
                return false;
            }

            $hostIsPresent = DB::table('members')
                ->where('game_id', $game->id)
                ->where('user_id', $game->owner_id)
                ->where('updated_at', '>=', $lobbyCutoff)
                ->exists();

            return ! $hostIsPresent;
        });

        $startedDeleted = $this->deleteGames($startedIds->all(), function (Game $game) use ($moveCutoff): bool {
            return $game->started
                && ! $game->is_synthetic
                && ! $game->terminated_at
                && $game->updated_at->lt($moveCutoff)
                && ! $game->moves()->where('created_at', '>=', $moveCutoff)->exists();
        });

        $synthetic = $this->balanceSyntheticListings();

        return [
            'lobby_games_deleted' => $lobbyDeleted,
            'started_games_deleted' => $startedDeleted,
            'synthetic_games_created' => $synthetic['created'],
            'synthetic_games_deleted' => $synthetic['deleted'],
            'active_public_listings' => $synthetic['active'],
            'total_deleted' => $lobbyDeleted + $startedDeleted + $synthetic['deleted'],
        ];
    }

    /** @return array{created:int,deleted:int,active:int} */
    private function balanceSyntheticListings(): array
    {
        $minimum = (int) config('game.minimum_public_room_listings', 10);
        $realActive = Game::query()
            ->where('is_synthetic', false)
            ->where('is_private', false)
            ->whereNull('terminated_at')
            ->where(function ($query) {
                $query->where('started', false)->orWhere(function ($started) {
                    $started->where('started', true)->whereNull('winner_id');
                });
            })
            ->count();
        $desiredSynthetic = config('game.auto_creation', false)
            ? max(0, $minimum - $realActive)
            : 0;
        $syntheticIds = Game::query()
            ->where('is_synthetic', true)
            ->whereNull('terminated_at')
            ->oldest()
            ->pluck('id');
        $deleted = 0;
        if ($syntheticIds->count() > $desiredSynthetic) {
            $deleteIds = $syntheticIds->take($syntheticIds->count() - $desiredSynthetic);
            $deleted = Game::query()->whereIn('id', $deleteIds)->delete();
            $syntheticIds = $syntheticIds->diff($deleteIds)->values();
        }

        $created = 0;
        $missing = max(0, $desiredSynthetic - $syntheticIds->count());
        if ($missing > 0) {
            $systemOwner = User::query()->firstOrCreate(
                ['email' => 'synthetic-rooms@system.invalid'],
                ['name' => 'Synthetic Rooms', 'color' => 'neutral', 'is_bot' => true],
            );
            $names = config('game.synthetic_player_names');
            for ($index = 0; $index < $missing; $index++) {
                do {
                    $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ'), 0, 4)).random_int(1000, 9999);
                } while (Game::query()->where('code', $code)->exists());
                $game = Game::create([
                    'code' => $code,
                    'owner_id' => $systemOwner->id,
                    'started' => true,
                    'host_in_lobby' => false,
                    'is_private' => false,
                    'is_synthetic' => true,
                    'synthetic_host_name' => $names[array_rand($names)],
                    'synthetic_player_count' => random_int(0, 8),
                    'sync_driver' => 'polling',
                ]);
                $ageMinutes = random_int(2, 75);
                DB::table('games')->where('id', $game->id)->update([
                    'created_at' => now()->subMinutes($ageMinutes)->subSeconds(random_int(0, 59)),
                    'updated_at' => now(),
                ]);
                $created++;
            }
        }

        return ['created' => $created, 'deleted' => $deleted, 'active' => $realActive + $desiredSynthetic];
    }

    public function forceDelete(Game $game): void
    {
        $deletedGame = DB::transaction(function () use ($game): ?array {
            $lockedGame = Game::query()->lockForUpdate()->find($game->id);
            if (! $lockedGame) {
                return null;
            }

            $details = [
                'id' => (int) $lockedGame->id,
                'driver' => (string) ($lockedGame->sync_driver ?: 'polling'),
            ];
            $lockedGame->delete();

            return $details;
        });

        if ($deletedGame) {
            $this->broadcastDeletion($deletedGame['id'], $deletedGame['driver']);
        }
    }

    /** @param array<int, int|string> $gameIds */
    private function deleteGames(array $gameIds, callable $stillStale): int
    {
        $deleted = 0;

        foreach ($gameIds as $gameId) {
            $deletedGame = DB::transaction(function () use ($gameId, $stillStale): ?array {
                $game = Game::query()->lockForUpdate()->find($gameId);
                if (! $game || ! $stillStale($game)) {
                    return null;
                }

                $details = ['id' => (int) $game->id, 'driver' => (string) ($game->sync_driver ?: 'polling')];
                $game->delete();

                return $details;
            });

            if (! $deletedGame) {
                continue;
            }

            $deleted++;
            $this->broadcastDeletion($deletedGame['id'], $deletedGame['driver']);
        }

        return $deleted;
    }

    private function broadcastDeletion(int $gameId, string $driver): void
    {
        $driver = config('game.reverb_override') ? 'reverb' : $driver;
        $payload = app(GameRealtimePayload::class)->build($gameId, 'game.deleted');
        try {
            if ($driver === 'ably') {
                $this->transportAllocator->publishAblyGameUpdate($gameId, 'game.deleted', $payload);
            } elseif (in_array($driver, ['pusher', 'reverb'], true)) {
                GameUpdated::dispatch($gameId, 'game.deleted', $driver, $payload);
            }
        } catch (\Throwable $error) {
            Log::warning('Stale game deleted but realtime notification failed', [
                'game_id' => $gameId,
                'driver' => $driver,
                'message' => $error->getMessage(),
            ]);
        }
    }
}
