<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameResource;
use App\Http\Resources\MoveResource;
use App\Http\Resources\UserResource;
use App\Models\Card;
use App\Models\Game;
use App\Models\Move;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameController extends Controller
{
    private const COLORS = ['yellow', 'blue', 'emerald', 'purple', 'rose', 'orange', 'brown', 'silver'];

    private function full(Game $game): GameResource
    {
        return new GameResource($game->load(['members', 'currentCard', 'moves' => fn ($q) => $q->with(['player', 'card'])->latest()]));
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

    private function drawNextCard(Game $game): void
    {
        $used = DB::table('game_cards')->where('game_id', $game->id)->pluck('card_id');
        $next = Card::whereNotIn('id', $used)
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
            Game::where('started', false)->with('members')->withCount('members')->having('members_count', '<', 8)->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'nullable|email', 'color' => 'nullable|in:'.implode(',', self::COLORS)]);

        return DB::transaction(function () use ($data) {
            $data['color'] = $data['color'] ?? self::COLORS[0];
            $user = User::create($data);
            do {
                $letters = '';
                for ($i = 0; $i < 4; $i++) {
                    $letters .= chr(random_int(65, 90));
                }
                $chars = str_split($letters.random_int(1000, 9999));
                shuffle($chars);
                $code = implode($chars);
            } while (Game::whereCode($code)->exists());
            $game = Game::create(['code' => $code, 'owner_id' => $user->id]);
            $game->members()->attach($user);

            return response()->json(['game' => $this->full($game), 'user' => new UserResource($user)], 201);
        });
    }

    public function show(Game $game)
    {
        return $this->full($game);
    }

    public function byCode(string $code)
    {
        return $this->full(Game::where('code', strtoupper($code))->firstOrFail());
    }

    public function update(Request $request, Game $game)
    {
        $game->update($request->validate(['started' => 'sometimes|boolean']));

        return $this->full($game);
    }

    public function destroy(Game $game)
    {
        $game->delete();

        return response()->noContent();
    }

    public function join(Request $request, string $code)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'nullable|email', 'color' => 'nullable|in:'.implode(',', self::COLORS)]);

        return DB::transaction(function () use ($data, $code) {
            $game = Game::where('code', strtoupper($code))->lockForUpdate()->firstOrFail();
            abort_if($game->started, 422, 'Game already started.');
            abort_if($game->members()->count() >= 8, 422, 'No more available seats in this room.');
            $used = $game->members()->pluck('color')->filter()->all();
            $requested = $data['color'] ?? self::COLORS[0];
            $data['color'] = in_array($requested, $used, true) ? collect(self::COLORS)->first(fn ($color) => ! in_array($color, $used, true)) : $requested;
            $user = User::create($data);
            $game->members()->attach($user);

            return response()->json(['game' => $this->full($game), 'user' => new UserResource($user), 'color_changed' => $requested !== $data['color']], 201);
        });
    }

    public function start(Request $request, Game $game)
    {
        $data = $request->validate(['user_id' => 'required|integer', 'stack' => 'sometimes|string|exists:stacks,slug']);
        $userId = (int) $data['user_id'];
        $stack = \App\Models\Stack::where('slug', $data['stack'] ?? 'normal')->firstOrFail();

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

        return DB::transaction(function () use ($game, $stack) {
            $game = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            if (! $game->started) $game->update(['stack_id' => $stack->id]);
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
                $cards = Card::where('stack_id', $game->stack_id)->inRandomOrder()->limit($needed)->get();
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
                $game->update(['started' => true, 'current_card_id' => $current->id, 'current_player_id' => $firstPlayerId, 'turn_owner_id' => $firstPlayerId, 'awaiting_finish' => false, 'is_steal_turn' => false]);

                Log::info('Game started successfully', [
                    'game_id' => $game->id,
                    'member_count' => $memberCount,
                    'first_player_id' => $firstPlayerId,
                    'current_card_id' => $current->id,
                ]);
            }

            return $this->full($game);
        });
    }

    public function move(Request $request, Game $game)
    {
        $data = $request->validate(['player_id' => 'required|integer', 'correct' => 'required|boolean']);

        return DB::transaction(function () use ($data, $game) {
            $game = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            abort_unless($game->started, 422, 'Game has not started.');
            abort_unless((int) $game->current_player_id === (int) $data['player_id'], 409, 'It is not your turn.');
            abort_if($game->awaiting_finish, 409, 'Finish the current turn first.');
            $move = Move::create(['game_id' => $game->id, 'player_id' => $data['player_id'], 'card_id' => $game->current_card_id, 'correct' => $data['correct']]);
            if ($data['correct']) {
                DB::table('game_cards')->where('game_id', $game->id)->where('card_id', $game->current_card_id)->update(['user_id' => $data['player_id'], 'updated_at' => now()]);
            }
            if ($data['correct']) {
                $game->update(['awaiting_finish' => true]);
            } else {
                // An incorrect placement never needs a separate confirmation.
                // Move immediately to the next stealer, or start the next round
                // once every eligible player has attempted the card.
                $this->advanceStealOrRound($game, false);
            }

            return response()->json(['move' => new MoveResource($move->load(['player', 'card'])), 'game' => $this->full($game)]);
        });
    }

    public function finishTurn(Request $request, Game $game)
    {
        $data = $request->validate(['player_id' => 'required|integer']);

        return DB::transaction(function () use ($data, $game) {
            $game = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $game->current_player_id === (int) $data['player_id'], 409, 'Only the active player can finish this turn.');
            abort_unless($game->awaiting_finish, 409, 'There is no completed turn to finish.');
            $move = Move::where('game_id', $game->id)->latest()->firstOrFail();
            $this->advanceStealOrRound($game, (bool) $move->correct);

            return response()->json(['game' => $this->full($game)]);
        });
    }

    public function passSteal(Request $request, Game $game)
    {
        $data = $request->validate(['player_id' => 'required|integer']);

        return DB::transaction(function () use ($data, $game) {
            $game = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            abort_unless($game->is_steal_turn && ! $game->awaiting_finish, 409, 'There is no steal to pass.');
            abort_unless((int) $game->current_player_id === (int) $data['player_id'], 409, 'Only the active stealer can pass.');
            $this->advanceStealOrRound($game, false);

            return response()->json(['game' => $this->full($game)]);
        });
    }
}
