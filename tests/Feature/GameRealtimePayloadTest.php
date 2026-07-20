<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Game;
use App\Models\User;
use App\Services\GameRealtimePayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GameRealtimePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_pusher_payload_contains_authoritative_state_and_stays_below_ten_kilobytes(): void
    {
        $members = User::factory()->count(8)->create([
            'name' => str_repeat('N', 255),
        ]);
        $currentCard = Card::create([
            'title' => str_repeat('T', 255),
            'title_bs' => str_repeat('B', 255),
            'subtitle' => str_repeat('S', 255),
            'subtitle_bs' => str_repeat('Ć', 1000),
            'score' => 50,
            'image' => str_repeat('i', 255),
        ]);
        $game = Game::create([
            'code' => 'RTSIZE01',
            'owner_id' => $members->first()->id,
            'started' => true,
            'current_card_id' => $currentCard->id,
            'current_player_id' => $members->first()->id,
            'turn_owner_id' => $members->first()->id,
        ]);
        foreach ($members as $member) {
            $game->members()->attach($member, ['in_lobby' => true]);
        }

        foreach (range(1, 4) as $index) {
            $card = Card::create([
                'title' => str_repeat((string) $index, 255),
                'title_bs' => str_repeat('Č', 255),
                'score' => 10 * $index,
                'image' => str_repeat('p', 255),
            ]);
            DB::table('game_cards')->insert([
                'game_id' => $game->id,
                'user_id' => $members[$index]->id,
                'card_id' => $card->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $game->events()->create([
                'type' => 'MOVE_RESULT',
                'payload' => [
                    'card_id' => $card->id,
                    'player_id' => $members[$index]->id,
                    'player_name' => $members[$index]->name,
                    'correct' => true,
                    'score' => $card->score,
                ],
            ]);
        }

        $payload = app(GameRealtimePayload::class)->build($game->id, 'move.correct');

        $this->assertSame($game->id, $payload['state']['id']);
        $this->assertSame($game->events()->max('id'), $payload['state_revision']);
        $this->assertCount(8, $payload['state']['members']);
        $this->assertCount(4, $payload['events']);
        $this->assertArrayHasKey('card', $payload['events'][3]['payload']);
        $this->assertLessThan(10_000, strlen(json_encode($payload, JSON_UNESCAPED_UNICODE)));
    }

    public function test_heartbeat_returns_every_event_after_the_supplied_cursor(): void
    {
        $host = User::factory()->create();
        $game = Game::create(['code' => 'RTCURSOR', 'owner_id' => $host->id]);
        $game->members()->attach($host);
        $first = $game->events()->create(['type' => 'TURN_STARTED', 'payload' => []]);
        $game->events()->create(['type' => 'TURN_HOLD', 'payload' => []]);
        $game->events()->create(['type' => 'STEAL_OFFERED', 'payload' => []]);

        $payload = app(GameRealtimePayload::class)->build($game->id, 'heartbeat', $first->id);

        $this->assertCount(2, $payload['events']);
        $this->assertSame($game->events()->max('id'), $payload['state_revision']);
        $this->assertTrue(collect($payload['events'])->every(fn ($event) => $event['id'] > $first->id));
    }
}
