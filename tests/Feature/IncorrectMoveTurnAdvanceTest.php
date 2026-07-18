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

    public function test_correct_move_completes_the_round_without_finishing_manually(): void
    {
        $owner = User::factory()->create(['color' => 'yellow']);
        $nextPlayer = User::factory()->create(['color' => 'blue']);
        $currentCard = Card::create(['title' => 'Current', 'score' => 10]);
        $nextCard = Card::create(['title' => 'Next', 'score' => 20]);
        $game = Game::create([
            'code' => 'TURN1234',
            'owner_id' => $owner->id,
            'started' => true,
            'target_score' => 5,
            'current_card_id' => $currentCard->id,
            'current_player_id' => $owner->id,
            'turn_owner_id' => $owner->id,
        ]);
        $game->members()->attach([$owner->id, $nextPlayer->id]);
        DB::table('game_cards')->insert([
            'game_id' => $game->id,
            'user_id' => null,
            'card_id' => $currentCard->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson("/api/games/{$game->id}/moves", [
            'player_id' => $owner->id,
            'correct' => true,
        ])->assertOk()
            ->assertJsonPath('game.is_steal_turn', false)
            ->assertJsonPath('game.current_player_id', $nextPlayer->id)
            ->assertJsonPath('game.turn_owner_id', $nextPlayer->id)
            ->assertJsonPath('game.current_card.id', $nextCard->id);

        $game->refresh();
        $this->assertFalse($game->is_steal_turn);
        $this->assertSame($nextPlayer->id, $game->current_player_id);
        $this->assertSame($nextPlayer->id, $game->turn_owner_id);
        $this->assertSame($nextCard->id, $game->current_card_id);
        $this->assertDatabaseHas('game_cards', [
            'game_id' => $game->id,
            'user_id' => $owner->id,
            'card_id' => $currentCard->id,
        ]);
    }

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

        $response = $this->postJson("/api/games/{$game->id}/moves", [
            'player_id' => $owner->id,
            'correct' => false,
        ])->assertOk()
            ->assertJsonPath('move.is_steal', false);
        $this->assertSame(
            ['MOVE_RESULT', 'TURN_HOLD', 'STEAL_OFFERED'],
            collect($response->json('game.events'))->pluck('type')->all(),
        );

        $game->refresh();
        $this->assertTrue($game->is_steal_turn);
        $this->assertSame($stealer->id, $game->current_player_id);

        $response = $this->postJson("/api/games/{$game->id}/moves", [
            'player_id' => $stealer->id,
            'correct' => false,
        ])->assertOk()
            ->assertJsonPath('move.is_steal', true);
        $this->assertSame(
            ['MOVE_RESULT', 'TURN_HOLD', 'STEAL_OFFERED', 'MOVE_RESULT', 'TURN_ENDED', 'TURN_STARTED'],
            collect($response->json('game.events'))->pluck('type')->all(),
        );

        $game->refresh();
        $this->assertFalse($game->is_steal_turn);
        $this->assertSame($stealer->id, $game->current_player_id);
        $this->assertSame($stealer->id, $game->turn_owner_id);
        $this->assertSame($nextCard->id, $game->current_card_id);
    }

    public function test_first_three_cards_are_zero_points_and_target_finishes_game_immediately(): void
    {
        $player = User::factory()->create(['color' => 'yellow']);
        $opponent = User::factory()->create(['color' => 'blue']);
        $setupCards = collect([10, 20, 30])->map(fn (int $score) => Card::create([
            'title' => "Setup {$score}",
            'score' => $score,
        ]));
        $winningCard = Card::create(['title' => 'Winning card', 'score' => 40]);
        $game = Game::create([
            'code' => 'WXYZ5678',
            'owner_id' => $player->id,
            'started' => true,
            'target_score' => 1,
            'current_card_id' => $winningCard->id,
            'current_player_id' => $player->id,
            'turn_owner_id' => $player->id,
        ]);
        $game->members()->attach([$player->id, $opponent->id]);
        foreach ($setupCards as $card) {
            DB::table('game_cards')->insert([
                'game_id' => $game->id,
                'user_id' => $player->id,
                'card_id' => $card->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('game_cards')->insert([
            'game_id' => $game->id,
            'user_id' => null,
            'card_id' => $winningCard->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/games/{$game->id}/moves", [
            'player_id' => $player->id,
            'correct' => true,
        ])->assertOk();
        $this->assertSame(
            ['MOVE_RESULT', 'GAME_FINISHED'],
            collect($response->json('game.events'))->pluck('type')->all(),
        );

        $game->refresh();
        $this->assertSame($player->id, $game->winner_id);
        $this->assertSame(4, DB::table('game_cards')->where('game_id', $game->id)->where('user_id', $player->id)->count());
    }
}
