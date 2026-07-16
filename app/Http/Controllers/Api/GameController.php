<?php

namespace App\Http\Controllers\Api;

use App\Events\GameUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\GameMessageResource;
use App\Http\Resources\GameResource;
use App\Http\Resources\MoveResource;
use App\Http\Resources\UserResource;
use App\Models\Card;
use App\Models\Game;
use App\Models\GameMessage;
use App\Models\Move;
use App\Models\Stack;
use App\Models\User;
use App\Services\RealtimeTransportAllocator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameController extends Controller
{
    private const COLORS = ['yellow', 'blue', 'emerald', 'purple', 'rose', 'orange', 'brown', 'silver'];

    public function __construct(private readonly RealtimeTransportAllocator $transportAllocator) {}

    private function full(Game $game): GameResource
    {
        return new GameResource($game->load([
            'members',
            'currentCard',
            'moves' => fn ($q) => $q->with(['player', 'card'])->latest(),
            'messages' => fn ($q) => $q->with('user')->latest()->limit(100),
        ]));
    }

    private function broadcastGameUpdated(Game|int $game, string $reason, ?string $driverOverride = null): void
    {
        $gameId = $game instanceof Game ? (int) $game->id : $game;
        $driver = config('game.reverb_override')
            ? 'reverb'
            : ($driverOverride ?? ($game instanceof Game ? $game->sync_driver : 'polling'));
        if (! in_array($driver, ['pusher', 'ably', 'reverb'], true)) {
            return;
        }

        DB::afterCommit(function () use ($gameId, $reason, $driver) {
            try {
                if ($driver === 'ably') {
                    $this->transportAllocator->publishAblyGameUpdate($gameId, $reason);
                } else {
                    GameUpdated::dispatch($gameId, $reason, $driver);
                }
            } catch (\Throwable $error) {
                Log::error('Realtime game update failed', [
                    'game_id' => $gameId,
                    'driver' => $driver,
                    'reason' => $reason,
                    'message' => $error->getMessage(),
                ]);
            }
        });
    }

    private function memberIds(Game $game): array
    {
        return DB::table('members')->where('game_id', $game->id)->orderBy('created_at')->orderBy('id')->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    private function nextMemberId(array $memberIds, int $currentId): int
    {
        $index = array_search($currentId, $memberIds, true);

        return $memberIds[(($index === false ? 0 : $index + 1) % count($memberIds))];
    }

    private function nextRemainingMemberId(array $originalMemberIds, array $remainingMemberIds, int $currentId): int
    {
        $currentIndex = array_search($currentId, $originalMemberIds, true);
        $count = count($originalMemberIds);
        for ($offset = 1; $offset <= $count; $offset++) {
            $candidate = $originalMemberIds[(($currentIndex === false ? -1 : $currentIndex) + $offset) % $count];
            if (in_array($candidate, $remainingMemberIds, true)) {
                return $candidate;
            }
        }

        return $remainingMemberIds[0];
    }

    private function terminateGame(Game $game, string $reason): void
    {
        $game->update([
            'terminated_at' => now(),
            'termination_reason' => $reason,
            'host_in_lobby' => false,
            'current_player_id' => null,
            'turn_owner_id' => null,
            'awaiting_finish' => false,
            'is_steal_turn' => false,
        ]);
        DB::table('members')->where('game_id', $game->id)->delete();
        Log::info('Game terminated', ['game_id' => $game->id, 'reason' => $reason]);
    }

    private function normalizeAfterMembersRemoved(Game $game, array $originalMemberIds, array $removedMemberIds): void
    {
        DB::table('game_cards')->where('game_id', $game->id)->whereIn('user_id', $removedMemberIds)->delete();
        $remaining = $this->memberIds($game);
        if (! $remaining) {
            $game->update(['current_player_id' => null, 'turn_owner_id' => null, 'awaiting_finish' => false, 'is_steal_turn' => false]);

            return;
        }

        $currentId = (int) $game->current_player_id;
        $ownerId = (int) $game->turn_owner_id;
        $currentRemoved = in_array($currentId, $removedMemberIds, true);
        $ownerRemoved = in_array($ownerId, $removedMemberIds, true);
        if (! $currentRemoved && ! $ownerRemoved) {
            return;
        }

        if ($ownerRemoved || ! $game->is_steal_turn) {
            $nextOwnerId = $this->nextRemainingMemberId($originalMemberIds, $remaining, $ownerId ?: $currentId);
            if ($game->started) {
                $this->drawNextCard($game);
            }
            $game->current_player_id = $nextOwnerId;
            $game->turn_owner_id = $nextOwnerId;
            $game->is_steal_turn = false;
        } else {
            $nextActorId = $this->nextRemainingMemberId($originalMemberIds, $remaining, $currentId);
            if ($nextActorId === $ownerId) {
                $nextOwnerId = $this->nextRemainingMemberId($originalMemberIds, $remaining, $ownerId);
                if ($game->started) {
                    $this->drawNextCard($game);
                }
                $game->current_player_id = $nextOwnerId;
                $game->turn_owner_id = $nextOwnerId;
                $game->is_steal_turn = false;
            } else {
                $game->current_player_id = $nextActorId;
                $game->is_steal_turn = true;
            }
        }
        $game->awaiting_finish = false;
        $game->save();
    }

    private function refreshPresence(Game $game, int $requestingUserId): Game
    {
        if ($game->terminated_at) {
            return $game;
        }

        if (! $game->started) {
            DB::table('members')
                ->where('game_id', $game->id)
                ->where('user_id', $requestingUserId)
                ->update(['updated_at' => now()]);

            return $game->refresh();
        }

        $hasInactiveMembers = $this->inactiveMemberIds($game)->isNotEmpty();

        if (! $hasInactiveMembers) {
            DB::table('members')
                ->where('game_id', $game->id)
                ->where('user_id', $requestingUserId)
                ->update(['updated_at' => now()]);

            return $game->refresh();
        }

        return DB::transaction(function () use ($game, $requestingUserId) {
            $game = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            if ($game->terminated_at) {
                return $game;
            }

            $originalMemberIds = $this->memberIds($game);
            $inactiveIds = $this->inactiveMemberIds($game)->all();

            if (in_array((int) $game->owner_id, $inactiveIds, true)) {
                $this->terminateGame($game, 'host_inactive');

                return $game->refresh();
            }

            if ($inactiveIds) {
                DB::table('members')->where('game_id', $game->id)->whereIn('user_id', $inactiveIds)->delete();
                $this->normalizeAfterMembersRemoved($game, $originalMemberIds, $inactiveIds);
                Log::info('Inactive game members removed', ['game_id' => $game->id, 'user_ids' => $inactiveIds]);
            }

            DB::table('members')
                ->where('game_id', $game->id)
                ->where('user_id', $requestingUserId)
                ->update(['updated_at' => now()]);

            return $game->refresh();
        });
    }

    private function inactiveMemberIds(Game $game): Collection
    {
        $memberCutoff = now()->subSeconds(config('game.member_inactivity_timeout_seconds'));

        return DB::table('members')
            ->where('game_id', $game->id)
            ->where('updated_at', '<', $memberCutoff)
            ->pluck('user_id')->map(fn ($id) => (int) $id);
    }

    private function ensurePlayable(Game $game): void
    {
        abort_if($game->terminated_at, 410, 'This game was ended by the host.');
    }

    private function drawNextCard(Game $game): void
    {
        $used = DB::table('game_cards')->where('game_id', $game->id)->pluck('card_id');
        $next = Card::whereNotIn('id', $used)
            ->where('status', true)
            ->when($game->stack_id, fn ($query) => $query->where('stack_id', $game->stack_id))
            ->inRandomOrder()->first();
        if (! $next) {
            return;
        }
        DB::table('game_cards')->insert(['game_id' => $game->id, 'user_id' => null, 'card_id' => $next->id, 'created_at' => now(), 'updated_at' => now()]);
        $game->current_card_id = $next->id;
    }

    private function advanceStealOrRound(Game $game, bool $successful): void
    {
        $members = $this->memberIds($game);
        $ownerId = (int) ($game->turn_owner_id ?: $game->current_player_id);
        $nextActorId = $this->nextMemberId($members, (int) $game->current_player_id);
        $roundComplete = $successful || $nextActorId === $ownerId;

        if ($roundComplete) {
            $nextOwnerId = $this->nextMemberId($members, $ownerId);
            $this->drawNextCard($game);
            $game->current_player_id = $nextOwnerId;
            $game->turn_owner_id = $nextOwnerId;
            $game->is_steal_turn = false;
        } else {
            $game->current_player_id = $nextActorId;
            $game->is_steal_turn = true;
        }
        $game->awaiting_finish = false;
        $game->save();
    }

    public function index()
    {
        return GameResource::collection(
            Game::where('started', false)->where('is_private', false)->whereNull('terminated_at')->with('members')->has('members', '<', config('game.max_players'))->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'color' => 'nullable|in:'.implode(',', self::COLORS),
            'stack' => 'sometimes|string|exists:stacks,slug',
            'sync_driver' => 'sometimes|nullable|in:pusher,ably,polling,reverb',
        ]);
        $stack = Stack::where('slug', $data['stack'] ?? 'normal')->firstOrFail();

        return Cache::lock('realtime-transport-allocation', 15)->block(10, function () use ($data, $stack) {
            $syncDriver = isset($data['sync_driver'])
                ? $this->transportAllocator->selectForNewRoom($data['sync_driver'])
                : $this->transportAllocator->selectForNewRoom();

            return DB::transaction(function () use ($data, $stack, $syncDriver) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'] ?? null,
                    'color' => $data['color'] ?? self::COLORS[0],
                ]);
                do {
                    $letters = '';
                    for ($i = 0; $i < 4; $i++) {
                        $letters .= chr(random_int(65, 90));
                    }
                    $chars = str_split($letters.random_int(1000, 9999));
                    shuffle($chars);
                    $code = implode($chars);
                } while (Game::whereCode($code)->exists());
                $game = Game::create(['code' => $code, 'owner_id' => $user->id, 'stack_id' => $stack->id, 'sync_driver' => $syncDriver]);
                $game->members()->attach($user);

                return response()->json(['game' => $this->full($game), 'user' => new UserResource($user)], 201);
            });
        });
    }

    public function show(Request $request, Game $game)
    {
        $data = $request->validate(['user_id' => 'nullable|integer']);
        if (isset($data['user_id'])) {
            $game = $this->refreshPresence($game, (int) $data['user_id']);
        }

        return $this->full($game);
    }

    public function snapshot(Game $game)
    {
        return $this->full($game);
    }

    public function heartbeat(Request $request, Game $game)
    {
        $data = $request->validate(['user_id' => 'required|integer']);

        abort_unless(
            DB::table('members')
                ->where('game_id', $game->id)
                ->where('user_id', $data['user_id'])
                ->exists(),
            403,
            'Only room members can send a heartbeat.'
        );

        DB::table('members')
            ->where('game_id', $game->id)
            ->where('user_id', $data['user_id'])
            ->update(['updated_at' => now()]);

        return response()->noContent();
    }

    public function realtimeToken(Request $request, Game $game)
    {
        $data = $request->validate(['user_id' => 'required|integer']);
        abort_unless($game->sync_driver === 'ably', 409, 'This room does not use Ably.');
        abort_unless(
            DB::table('members')->where('game_id', $game->id)->where('user_id', $data['user_id'])->exists(),
            403,
            'Only room members can connect.'
        );

        return response()->json($this->transportAllocator->createAblyTokenRequest($game, (int) $data['user_id']));
    }

    public function sendMessage(Request $request, Game $game)
    {
        $request->merge(['message' => trim((string) $request->input('message'))]);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'message' => ['required', 'string', 'max:20'],
        ]);

        $this->ensurePlayable($game);
        abort_unless($game->started, 422, 'Game has not started.');
        abort_unless(
            DB::table('members')->where('game_id', $game->id)->where('user_id', $data['user_id'])->exists(),
            403,
            'Only players in this room can send messages.'
        );

        $message = GameMessage::create([
            'game_id' => $game->id,
            'user_id' => $data['user_id'],
            'message' => $data['message'],
        ]);
        $this->broadcastGameUpdated($game, 'message.created');

        return (new GameMessageResource($message->load('user')))->response()->setStatusCode(201);
    }

    public function byCode(string $code)
    {
        return $this->full(Game::where('code', strtoupper($code))->firstOrFail());
    }

    public function setHostLobbyPresence(Request $request, Game $game)
    {
        $data = $request->validate(['user_id' => 'required|integer', 'present' => 'required|boolean']);
        abort_unless((int) $game->owner_id === (int) $data['user_id'], 403, 'Only the room owner can update host presence.');
        $game->update(['host_in_lobby' => $data['present']]);
        $this->broadcastGameUpdated($game, 'host.presence');

        return $this->full($game);
    }

    public function lockRoom(Request $request, Game $game)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'pro_active' => ['required', 'boolean'],
        ]);

        abort_unless((int) $game->owner_id === (int) $data['user_id'], 403, 'Only the room owner can lock this room.');
        abort_if($game->started, 422, 'A room can only be locked before the game starts.');
        $this->ensurePlayable($game);
        abort_unless($game->is_private || $data['pro_active'], 403, 'An active Misery PRO subscription is required to create a private room.');

        $game->update(['is_private' => ! $game->is_private]);
        $this->broadcastGameUpdated($game, 'room.locked');

        return $this->full($game->refresh());
    }

    public function kickPlayer(Request $request, Game $game)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'player_id' => ['required', 'integer', 'different:user_id'],
        ]);

        return DB::transaction(function () use ($data, $game) {
            $game = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            $this->ensurePlayable($game);
            abort_if($game->started, 422, 'Players can only be removed while the game is in the lobby.');
            abort_unless((int) $game->owner_id === (int) $data['user_id'], 403, 'Only the room host can remove players.');
            abort_if((int) $game->owner_id === (int) $data['player_id'], 422, 'The room host cannot be removed.');
            abort_unless(
                DB::table('members')->where('game_id', $game->id)->where('user_id', $data['player_id'])->exists(),
                404,
                'Player is not in this room.'
            );

            DB::table('members')->where('game_id', $game->id)->where('user_id', $data['player_id'])->delete();
            DB::table('game_cards')->where('game_id', $game->id)->where('user_id', $data['player_id'])->delete();
            Log::info('Lobby player removed by host', [
                'game_id' => $game->id,
                'host_id' => (int) $data['user_id'],
                'player_id' => (int) $data['player_id'],
            ]);
            $this->broadcastGameUpdated($game, 'member.kicked');

            return $this->full($game->refresh());
        });
    }

    public function update(Request $request, Game $game)
    {
        $game->update($request->validate(['started' => 'sometimes|boolean']));
        $this->broadcastGameUpdated($game, 'game.updated');

        return $this->full($game);
    }

    public function destroy(Request $request, Game $game)
    {
        $data = $request->validate(['user_id' => 'required|integer']);
        abort_unless((int) $data['user_id'] === (int) $game->owner_id, 403, 'Only the host can delete this room.');
        abort_if($game->started, 409, 'A room cannot be deleted after the game has started.');

        $gameId = (int) $game->id;
        $driver = $game->sync_driver;
        $game->delete();
        $this->broadcastGameUpdated($gameId, 'game.deleted', $driver);

        return response()->noContent();
    }

    public function join(Request $request, string $code)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'color' => 'nullable|in:'.implode(',', self::COLORS),
            'client' => 'sometimes|nullable|in:native,web,simulator',
        ]);
        if (! config('game.reverb_override')) {
            $this->transportAllocator->providerAvailable('pusher');
            $this->transportAllocator->providerAvailable('ably');
        }

        return Cache::lock('realtime-transport-allocation', 15)->block(10, function () use ($data, $code) {
            return DB::transaction(function () use ($data, $code) {
                $game = Game::where('code', strtoupper($code))->lockForUpdate()->firstOrFail();
                $this->ensurePlayable($game);
                abort_if($game->started, 422, 'Game already started.');
                abort_if($game->members()->count() >= config('game.max_players'), 422, 'No more available seats in this room.');
                if (($data['client'] ?? null) === 'native' && ! $this->transportAllocator->canJoinWithoutReallocation($game)) {
                    return response()->json([
                        'message' => 'Započeta igra je na serveru koji je opterećen. Izaberi drugu igru ili pokušaj kasnije.',
                        'error_code' => 'realtime_provider_capacity_exceeded',
                    ], 409);
                }
                $previousDriver = $game->sync_driver;
                $this->transportAllocator->ensureCapacityForJoin($game);
                if ($game->sync_driver !== $previousDriver) {
                    $this->broadcastGameUpdated($game, 'transport.changed', $previousDriver);
                }
                $used = $game->members()->pluck('color')->filter()->all();
                $requested = $data['color'] ?? self::COLORS[0];
                $data['color'] = in_array($requested, $used, true) ? collect(self::COLORS)->first(fn ($color) => ! in_array($color, $used, true)) : $requested;
                $user = User::create($data);
                $game->members()->attach($user);
                $this->broadcastGameUpdated($game, 'member.joined');

                return response()->json(['game' => $this->full($game), 'user' => new UserResource($user), 'color_changed' => $requested !== $data['color']], 201);
            });
        });
    }

    public function start(Request $request, Game $game)
    {
        $this->ensurePlayable($game);
        $data = $request->validate(['user_id' => 'required|integer', 'stack' => 'sometimes|string|exists:stacks,slug', 'target_score' => 'required|integer|min:1|max:50']);
        $userId = (int) $data['user_id'];
        $stack = Stack::where('slug', $data['stack'] ?? 'normal')->firstOrFail();

        Log::info('Game start requested', [
            'game_id' => $game->id,
            'request_user_id' => $userId,
            'owner_id' => (int) $game->owner_id,
            'started' => (bool) $game->started,
        ]);

        if ((int) $game->owner_id !== $userId) {
            Log::warning('Game start rejected: requester is not owner', [
                'game_id' => $game->id,
                'request_user_id' => $userId,
                'owner_id' => (int) $game->owner_id,
            ]);
            abort(403, 'Only the room owner can start the game.');
        }

        return DB::transaction(function () use ($data, $game, $stack) {
            $game = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            $this->ensurePlayable($game);
            if ($game->started && $game->winner_id) {
                DB::table('game_cards')->where('game_id', $game->id)->delete();
                $game->moves()->delete();
                $game->update([
                    'started' => false,
                    'current_card_id' => null,
                    'current_player_id' => null,
                    'turn_owner_id' => null,
                    'winner_id' => null,
                    'awaiting_finish' => false,
                    'is_steal_turn' => false,
                ]);
            }
            if (! $game->started) {
                $game->update(['stack_id' => $stack->id, 'target_score' => $data['target_score'], 'winner_id' => null]);
            }
            $game->load('members');
            $memberCount = $game->members->count();

            if ($memberCount < 2) {
                Log::warning('Game start rejected: not enough players', [
                    'game_id' => $game->id,
                    'member_count' => $memberCount,
                ]);
                abort(422, 'At least two players are required to start.');
            }

            if (! $game->started) {
                $needed = $memberCount * 3 + 1;
                $cards = Card::where('stack_id', $game->stack_id)->where('status', true)->inRandomOrder()->limit($needed)->get();
                if ($cards->count() < $needed) {
                    Log::warning('Game start rejected: not enough cards', [
                        'game_id' => $game->id,
                        'member_count' => $memberCount,
                        'cards_needed' => $needed,
                        'cards_found' => $cards->count(),
                    ]);
                    abort(422, 'Not enough cards. Run the card seeder.');
                }
                foreach ($game->members as $member) {
                    foreach ($cards->splice(0, 3) as $card) {
                        DB::table('game_cards')->insert(['game_id' => $game->id, 'user_id' => $member->id, 'card_id' => $card->id, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
                $current = $cards->shift();
                $firstPlayerId = $this->memberIds($game)[0];
                DB::table('game_cards')->insert(['game_id' => $game->id, 'user_id' => null, 'card_id' => $current->id, 'created_at' => now(), 'updated_at' => now()]);
                $game->update(['started' => true, 'host_in_lobby' => false, 'current_card_id' => $current->id, 'current_player_id' => $firstPlayerId, 'turn_owner_id' => $firstPlayerId, 'awaiting_finish' => false, 'is_steal_turn' => false]);

                Log::info('Game started successfully', [
                    'game_id' => $game->id,
                    'member_count' => $memberCount,
                    'first_player_id' => $firstPlayerId,
                    'current_card_id' => $current->id,
                ]);
            }
            $this->broadcastGameUpdated($game, 'game.started');

            return $this->full($game);
        });
    }

    public function move(Request $request, Game $game)
    {
        $this->ensurePlayable($game);
        $data = $request->validate(['player_id' => 'required|integer', 'correct' => 'required|boolean']);

        return DB::transaction(function () use ($data, $game) {
            $game = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            $this->ensurePlayable($game);
            abort_unless($game->started, 422, 'Game has not started.');
            abort_if($game->winner_id, 409, 'This game has already finished.');
            abort_unless((int) $game->current_player_id === (int) $data['player_id'], 409, 'It is not your turn.');
            abort_if($game->awaiting_finish, 409, 'Finish the current turn first.');
            $move = Move::create(['game_id' => $game->id, 'player_id' => $data['player_id'], 'card_id' => $game->current_card_id, 'correct' => $data['correct']]);
            if ($data['correct']) {
                DB::table('game_cards')->where('game_id', $game->id)->where('card_id', $game->current_card_id)->update(['user_id' => $data['player_id'], 'updated_at' => now()]);
            }
            if ($data['correct']) {
                $ownedCardCount = DB::table('game_cards')
                    ->where('game_id', $game->id)
                    ->where('user_id', $data['player_id'])
                    ->count();
                $points = max(0, $ownedCardCount - 3);
                if ($points >= $game->target_score) {
                    $game->update(['winner_id' => $data['player_id'], 'awaiting_finish' => false, 'is_steal_turn' => false]);
                } else {
                    $game->update(['awaiting_finish' => true]);
                }
            } else {
                // An incorrect placement never needs a separate confirmation.
                // Move immediately to the next stealer, or start the next round
                // once every eligible player has attempted the card.
                $this->advanceStealOrRound($game, false);
            }
            $this->broadcastGameUpdated($game, 'move.created');

            return response()->json(['move' => new MoveResource($move->load(['player', 'card'])), 'game' => $this->full($game)]);
        });
    }

    public function finishTurn(Request $request, Game $game)
    {
        $this->ensurePlayable($game);
        $data = $request->validate(['player_id' => 'required|integer']);

        return DB::transaction(function () use ($data, $game) {
            $game = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            $this->ensurePlayable($game);
            abort_unless((int) $game->current_player_id === (int) $data['player_id'], 409, 'Only the active player can finish this turn.');
            abort_unless($game->awaiting_finish, 409, 'There is no completed turn to finish.');
            $move = Move::where('game_id', $game->id)->latest()->firstOrFail();
            $this->advanceStealOrRound($game, (bool) $move->correct);
            $this->broadcastGameUpdated($game, 'turn.finished');

            return response()->json(['game' => $this->full($game)]);
        });
    }

    public function passSteal(Request $request, Game $game)
    {
        $this->ensurePlayable($game);
        $data = $request->validate(['player_id' => 'required|integer']);

        return DB::transaction(function () use ($data, $game) {
            $game = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            $this->ensurePlayable($game);
            abort_unless($game->is_steal_turn && ! $game->awaiting_finish, 409, 'There is no steal to pass.');
            abort_unless((int) $game->current_player_id === (int) $data['player_id'], 409, 'Only the active stealer can pass.');
            $this->advanceStealOrRound($game, false);
            $this->broadcastGameUpdated($game, 'steal.passed');

            return response()->json(['game' => $this->full($game)]);
        });
    }

    public function inactivityTimeout(Request $request, Game $game)
    {
        $data = $request->validate(['user_id' => 'required|integer']);
        $userId = (int) $data['user_id'];

        return DB::transaction(function () use ($game, $userId) {
            $game = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            if ($game->terminated_at) {
                return $this->full($game);
            }

            abort_unless($game->started, 409, 'Inactivity removal only applies after the game has started.');
            $originalMemberIds = $this->memberIds($game);
            abort_unless(in_array($userId, $originalMemberIds, true), 404, 'Player is not in this room.');

            if ($userId === (int) $game->owner_id) {
                $this->terminateGame($game, 'host_inactive');
            } else {
                DB::table('members')->where('game_id', $game->id)->where('user_id', $userId)->delete();
                $this->normalizeAfterMembersRemoved($game, $originalMemberIds, [$userId]);
                Log::info('Inactive game member removed after turn timeout', ['game_id' => $game->id, 'user_id' => $userId]);
            }
            $this->broadcastGameUpdated($game, 'member.inactive');

            return $this->full($game->refresh());
        });
    }

    public function leave(Request $request, Game $game)
    {
        $data = $request->validate(['user_id' => 'required|integer']);
        $userId = (int) $data['user_id'];

        return DB::transaction(function () use ($game, $userId) {
            $game = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            if ($game->terminated_at) {
                return $this->full($game);
            }
            $originalMemberIds = $this->memberIds($game);
            abort_unless(in_array($userId, $originalMemberIds, true), 404, 'Player is not in this room.');

            if ($userId === (int) $game->owner_id) {
                $this->terminateGame($game, 'host_left');
            } else {
                DB::table('members')->where('game_id', $game->id)->where('user_id', $userId)->delete();
                $this->normalizeAfterMembersRemoved($game, $originalMemberIds, [$userId]);
                Log::info('Player left game', ['game_id' => $game->id, 'user_id' => $userId]);
            }
            $this->broadcastGameUpdated($game, 'member.left');

            return $this->full($game->refresh());
        });
    }
}
