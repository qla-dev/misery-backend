<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Game;
use App\Models\Stack;
use App\Models\User;
use Database\Seeders\CardSeeder;
use Database\Seeders\CardScoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CardSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_reseed_replaces_cards_and_clears_all_game_runtime_rows(): void
    {
        Storage::fake('public');
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create([
            'title' => 'Duplicate old card',
            'subtitle' => 'This must disappear.',
            'score' => 50,
            'image' => 'cards/generated/old.png',
            'svg_img' => 'cards/generated-svg/old.svg',
            'deck' => 'normal',
            'stack_id' => $stack->id,
        ]);
        Storage::disk('public')->put($card->image, 'old-png');
        Storage::disk('public')->put($card->svg_img, '<svg/>');
        $user = User::factory()->create();
        $game = Game::create([
            'code' => 'RESET001',
            'owner_id' => $user->id,
            'current_card_id' => $card->id,
            'stack_id' => $stack->id,
        ]);
        DB::table('members')->insert(['game_id' => $game->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('game_cards')->insert(['game_id' => $game->id, 'user_id' => $user->id, 'card_id' => $card->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('moves')->insert(['game_id' => $game->id, 'player_id' => $user->id, 'card_id' => $card->id, 'correct' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->seed(CardSeeder::class);

        $this->assertSame(100, Card::count());
        $this->assertSame(100, Card::distinct()->count('title'));
        $this->assertSame(100, Card::distinct()->count('score'));
        $scores = Card::orderBy('score')->pluck('score')->map(fn ($score) => (float) $score);
        $this->assertGreaterThanOrEqual(0.01, $scores->first());
        $this->assertSame(99.99, $scores->last());
        $this->assertTrue($scores->every(fn (float $score) => round($score, 2) !== floor($score)));
        $this->assertLessThan(
            (float) Card::where('title', 'Break a Front Tooth on a First Date')->value('score'),
            (float) Card::where('title', 'Lose a Contact Lens Before a Live Interview')->value('score')
        );
        $this->assertLessThan(
            (float) Card::where('title', 'A Flash Flood Rushes Into the Canyon')->value('score'),
            (float) Card::where('title', 'A Pipe Bursts Above Your Bedroom')->value('score')
        );
        $this->assertDatabaseMissing('cards', ['title' => 'Duplicate old card']);
        $this->assertDatabaseCount('games', 0);
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('game_cards', 0);
        $this->assertDatabaseCount('moves', 0);
        Storage::disk('public')->assertMissing('cards/generated/old.png');
        Storage::disk('public')->assertMissing('cards/generated-svg/old.svg');
    }

    public function test_score_only_seed_preserves_cards_artwork_and_games(): void
    {
        $this->seed(CardSeeder::class);
        $card = Card::where('title', 'Lose a Contact Lens Before a Live Interview')->firstOrFail();
        $card->update(['image' => 'cards/uploads/keep.png', 'score' => 50]);
        $user = User::factory()->create();
        $game = Game::create([
            'code' => 'KEEP0001',
            'owner_id' => $user->id,
            'current_card_id' => $card->id,
            'stack_id' => $card->stack_id,
        ]);

        $this->seed(CardScoreSeeder::class);

        $card->refresh();
        $this->assertSame('cards/uploads/keep.png', $card->image);
        $this->assertSame(0.55, (float) $card->score);
        $this->assertTrue(Game::whereKey($game->id)->exists());
        $this->assertSame(100, Card::count());
    }
}
