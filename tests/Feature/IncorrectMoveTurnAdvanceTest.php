<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IncorrectMoveTurnAdvanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_wrong_owner_and_steal_attempts_advance_without_finishing_manually(): void
    {
        $owner = User::factory()->create(['color' => 'yellow']);
        $stealer = User::factory()->create(['color' => 'blue']);
        $currentCard = Card::create(['title' => 'Current', 'score' => 10]);
        $nextCard = Card::create(['title' => 'Next', 'score' => 20]);
        $game = Game::create([
            'code' => 'ABCD1234',
            'owner_id' => $owner->id,
            'started' => true,
            'current_card_id' => $currentCard->id,
            'current_player_id' => $owner->id,
            'turn_owner_id' => $owner->id,
        ]);
        $game->members()->attach([$owner->id, $stealer->id]);
        DB::table('game_cards')->insert([
            'game_id' => $game->id,
            'user_id' => null,
            'card_id' => $currentCard->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/api/games/{$game->id}/moves", [
            'player_id' => $owner->id,
            'correct' => false,
        ])->assertOk();

        $game->refresh();
        $this->assertFalse($game->awaiting_finish);
        $this->assertTrue($game->is_steal_turn);
        $this->assertSame($stealer->id, $game->current_player_id);

        $this->postJson("/api/games/{$game->id}/moves", [
            'player_id' => $stealer->id,
            'correct' => false,
        ])->assertOk();

        $game->refresh();
        $this->assertFalse($game->awaiting_finish);
        $this->assertFalse($game->is_steal_turn);
        $this->assertSame($stealer->id, $game->current_player_id);
        $this->assertSame($stealer->id, $game->turn_owner_id);
        $this->assertSame($nextCard->id, $game->current_card_id);
    }
}
