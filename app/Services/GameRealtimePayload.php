<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

class GameRealtimePayload
{
    private const MAX_EVENT_COUNT = 4;

    public function build(int $gameId, string $reason, ?int $afterEventId = null): array
    {
        $game = Game::query()->with(['currentCard', 'stack', 'members'])->find($gameId);
        if (! $game) {
            return [
                'version' => 1,
                'game_id' => $gameId,
                'reason' => $reason,
                'sent_at' => now()->toISOString(),
                'state_revision' => 0,
                'deleted' => true,
                'state' => null,
                'events' => [],
                'chat_message' => null,
            ];
        }

        $eventQuery = $game->events();
        $stateRevision = (int) $game->events()->max('id');
        $events = $afterEventId === null
            ? $eventQuery->latest('id')->limit(self::MAX_EVENT_COUNT)->get()->sortBy('id')->values()
            : $eventQuery->where('id', '>', $afterEventId)->oldest('id')->limit(200)->get()->values();
        $eventsWithCards = $events->where('type', 'MOVE_RESULT');
        if ($afterEventId === null) {
            $eventsWithCards = $eventsWithCards->take(-1);
        }
        $eventCards = Card::query()
            ->whereIn('id', $eventsWithCards->pluck('payload')->map(fn ($payload) => $payload['card_id'] ?? null)->filter()->unique())
            ->get()
            ->keyBy('id');
        $handCardIds = DB::table('game_cards')
            ->where('game_id', $gameId)
            ->whereNotNull('user_id')
            ->orderBy('card_id')
            ->get(['user_id', 'card_id'])
            ->groupBy('user_id')
            ->map(fn ($cards) => $cards->pluck('card_id')->map(fn ($id) => (int) $id)->values()->all());
        $lobbyMemberIds = DB::table('members')
            ->where('game_id', $gameId)
            ->where('in_lobby', true)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $chatMessage = null;
        if ($reason === 'message.created') {
            $message = $game->messages()->with('user')->latest('id')->first();
            if ($message) {
                $chatMessage = [
                    'id' => (int) $message->id,
                    'game_id' => (int) $message->game_id,
                    'user_id' => (int) $message->user_id,
                    'message' => $message->message,
                    'user' => $this->user($message->user),
                    'created_at' => $message->created_at?->toISOString(),
                ];
            }
        }

        return [
            'version' => 1,
            'game_id' => $gameId,
            'reason' => $reason,
            'sent_at' => now()->toISOString(),
            'state_revision' => $stateRevision,
            'deleted' => false,
            'state' => [
                'id' => (int) $game->id,
                'code' => $game->code,
                'owner_id' => (int) $game->owner_id,
                'started' => (bool) $game->started,
                'host_in_lobby' => (bool) $game->host_in_lobby,
                'is_private' => (bool) $game->is_private,
                'terminated_at' => $game->terminated_at?->toISOString(),
                'termination_reason' => $game->termination_reason,
                'stack_id' => $game->stack_id ? (int) $game->stack_id : null,
                'stack' => $game->stack?->slug,
                'target_score' => (int) $game->target_score,
                'winner_id' => $game->winner_id ? (int) $game->winner_id : null,
                'current_player_id' => $game->current_player_id ? (int) $game->current_player_id : null,
                'turn_owner_id' => $game->turn_owner_id ? (int) $game->turn_owner_id : null,
                'is_steal_turn' => (bool) $game->is_steal_turn,
                'current_card' => $game->currentCard ? $this->card($game->currentCard) : null,
                'members' => $game->members->map(fn ($user) => $this->user($user))->values()->all(),
                'lobby_member_ids' => $lobbyMemberIds,
                'hand_card_ids' => $handCardIds->all(),
            ],
            'events' => $events->map(function ($event) use ($eventCards, $afterEventId) {
                $payload = $event->payload ?? [];
                if ($afterEventId === null) {
                    unset($payload['player_name']);
                }
                $cardId = $payload['card_id'] ?? null;
                if ($event->type === 'MOVE_RESULT' && $cardId && $eventCards->has($cardId)) {
                    $payload['card'] = $this->card($eventCards->get($cardId), false);
                }

                return [
                    'id' => (int) $event->id,
                    'type' => $event->type,
                    'target_user_id' => $event->target_user_id ? (int) $event->target_user_id : null,
                    'payload' => $payload,
                    'created_at' => $event->created_at?->toISOString(),
                ];
            })->all(),
            'chat_message' => $chatMessage,
        ];
    }

    private function user($user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'color' => $user->color,
            'is_bot' => (bool) $user->is_bot,
        ];
    }

    private function card(Card $card, bool $includeSubtitle = true): array
    {
        $image = $card->image && $card->image !== '0' ? $card->image : null;
        if ($image && ! str_starts_with($image, 'http://') && ! str_starts_with($image, 'https://')) {
            $image = '/card-images/'.ltrim(preg_replace('#^storage/#', '', $image), '/');
        }

        return [
            'id' => (int) $card->id,
            'title' => $card->title,
            'title_bs' => $card->title_bs,
            'subtitle' => $includeSubtitle ? $card->subtitle : null,
            'subtitle_bs' => $includeSubtitle ? $card->subtitle_bs : null,
            'score' => (float) $card->score,
            'image' => $image,
            'deck' => $card->deck,
        ];
    }
}
