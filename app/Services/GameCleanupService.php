<?php

namespace App\Services;

use App\Events\GameUpdated;
use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameCleanupService
{
    public function __construct(private readonly RealtimeTransportAllocator $transportAllocator) {}

    /** @return array{lobby_games_deleted:int, started_games_deleted:int, total_deleted:int} */
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
                && ! $game->terminated_at
                && $game->updated_at->lt($moveCutoff)
                && ! $game->moves()->where('created_at', '>=', $moveCutoff)->exists();
        });

        return [
            'lobby_games_deleted' => $lobbyDeleted,
            'started_games_deleted' => $startedDeleted,
            'total_deleted' => $lobbyDeleted + $startedDeleted,
        ];
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
        try {
            if ($driver === 'ably') {
                $this->transportAllocator->publishAblyGameUpdate($gameId, 'game.deleted');
            } elseif (in_array($driver, ['pusher', 'reverb'], true)) {
                GameUpdated::dispatch($gameId, 'game.deleted', $driver);
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
