<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class GameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hands = DB::table('game_cards')
            ->join('cards', 'cards.id', '=', 'game_cards.card_id')
            ->where('game_cards.game_id', $this->id)
            ->whereNotNull('game_cards.user_id')
            ->get(['game_cards.user_id', 'cards.id', 'cards.title', 'cards.title_bs', 'cards.subtitle', 'cards.subtitle_bs', 'cards.score', 'cards.image', 'cards.deck'])
            ->map(function ($card) {
                $card->score = (float) $card->score;
                $image = $card->image && $card->image !== '0' ? $card->image : null;
                if ($image && ! str_starts_with($image, 'http://') && ! str_starts_with($image, 'https://')) {
                    $image = '/card-images/'.ltrim(preg_replace('#^storage/#', '', $image), '/');
                }
                $card->image = $image;

                return $card;
            })
            ->groupBy('user_id');

        $syncDriver = $this->is_synthetic
            ? 'polling'
            : (config('game.reverb_override') ? 'reverb' : ($this->sync_driver ?: 'polling'));
        if ($this->is_synthetic) {
            $colors = ['yellow', 'blue', 'emerald', 'purple', 'rose', 'orange', 'brown', 'silver'];
            $names = config('game.synthetic_player_names');
            $count = max(0, min(8, (int) $this->synthetic_player_count));
            $nameOffset = (int) $this->id % count($names);
            $members = collect($count > 0 ? range(0, $count - 1) : [])->map(fn (int $index) => [
                'id' => -(((int) $this->id * 10) + $index + 1),
                'name' => $index === 0 ? $this->synthetic_host_name : $names[($nameOffset + $index) % count($names)],
                'email' => null,
                'color' => $colors[$index % count($colors)],
                'is_bot' => false,
            ]);
        } else {
            $members = UserResource::collection($this->whenLoaded('members'));
        }

        return [
            'id' => $this->id,
            'state_revision' => (int) DB::table('game_events')->where('game_id', $this->id)->max('id'),
            'state_sent_at' => now()->toISOString(),
            'code' => $this->code,
            'owner_id' => $this->owner_id,
            'started' => $this->started,
            'host_in_lobby' => $this->host_in_lobby,
            'is_private' => $this->is_private,
            'is_synthetic' => (bool) $this->is_synthetic,
            'synthetic_host_name' => $this->is_synthetic ? $this->synthetic_host_name : null,
            'terminated_at' => $this->terminated_at?->toISOString(),
            'termination_reason' => $this->termination_reason,
            'stack_id' => $this->stack_id,
            'stack' => $this->stack?->slug,
            'target_score' => $this->target_score,
            'winner_id' => $this->winner_id,
            'current_player_id' => $this->current_player_id,
            'turn_owner_id' => $this->turn_owner_id,
            'is_steal_turn' => $this->is_steal_turn,
            'sync_driver' => $syncDriver,
            'ingame_polling_interval_ms' => config('game.ingame_polling_interval_ms'),
            'pusher' => $syncDriver === 'pusher' ? [
                'key' => config('game.pusher_app_key'),
                'cluster' => config('game.pusher_app_cluster'),
                'channel' => "game.{$this->id}",
                'event' => 'game.updated',
                'heartbeat_interval_ms' => config('game.pusher_heartbeat_interval_ms'),
            ] : null,
            'ably' => $syncDriver === 'ably' ? [
                'channel' => "game.{$this->id}",
                'event' => 'game.updated',
                'token_endpoint' => "/games/{$this->id}/realtime-token",
                'heartbeat_interval_ms' => config('game.pusher_heartbeat_interval_ms'),
            ] : null,
            'reverb' => $syncDriver === 'reverb' ? [
                'key' => config('game.reverb_app_key'),
                'host' => config('game.reverb_host'),
                'port' => config('game.reverb_port'),
                'scheme' => config('game.reverb_scheme'),
                'channel' => "game.{$this->id}",
                'event' => 'game.updated',
                'heartbeat_interval_ms' => config('game.pusher_heartbeat_interval_ms'),
            ] : null,
            'current_card' => new CardResource($this->whenLoaded('currentCard')),
            'members' => $members,
            'lobby_member_ids' => $this->is_synthetic ? [] : DB::table('members')
                ->where('game_id', $this->id)
                ->where('in_lobby', true)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->values(),
            'hands' => $hands,
            'moves' => MoveResource::collection($this->whenLoaded('moves')),
            'events' => $this->relationLoaded('events')
                ? GameEventResource::collection($this->events->sortBy('id')->values())
                : [],
            'chat_messages' => $this->relationLoaded('messages')
                ? GameMessageResource::collection($this->messages->sortBy('id')->values())
                : [],
            'created_at' => $this->created_at,
        ];
    }
}
