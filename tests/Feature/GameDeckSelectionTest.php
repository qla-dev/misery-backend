<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Game;
use App\Models\Stack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GameDeckSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_and_later_draws_use_only_random_cards_from_the_selected_stack(): void
    {
        $normal = Stack::where('slug', 'normal')->firstOrFail();
        $spicy = Stack::where('slug', 'spicy')->firstOrFail();
        foreach (range(1, 12) as $index) {
            Card::create(['title' => "Normal {$index}", 'score' => $index, 'deck' => 'normal', 'stack_id' => $normal->id]);
            Card::create(['title' => "Spicy {$index}", 'score' => $index, 'deck' => 'spicy', 'stack_id' => $spicy->id]);
        }

        $owner = User::factory()->create(['color' => 'yellow']);
        $opponent = User::factory()->create(['color' => 'blue']);
        $game = Game::create(['code' => 'DECK1234', 'owner_id' => $owner->id]);
        $game->members()->attach([$owner->id, $opponent->id]);

        $this->postJson("/api/games/{$game->id}/start", [
            'user_id' => $owner->id,
            'stack' => 'spicy',
            'target_score' => 7,
        ])->assertOk();

        $dealtCardIds = DB::table('game_cards')->where('game_id', $game->id)->pluck('card_id');
        $this->assertCount(7, $dealtCardIds);
        $this->assertSame(0, Card::whereIn('id', $dealtCardIds)->where('stack_id', '!=', $spicy->id)->count());

        $this->postJson("/api/games/{$game->id}/moves", [
            'player_id' => $owner->id,
            'correct' => false,
        ])->assertOk();
        $this->postJson("/api/games/{$game->id}/moves", [
            'player_id' => $opponent->id,
            'correct' => false,
        ])->assertOk();

        $game->refresh();
        $this->assertSame($spicy->id, $game->stack_id);
        $this->assertSame($spicy->id, $game->currentCard()->value('stack_id'));
    }
}
