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

        $this->assertSame(300, Card::count());
        $this->assertSame(300, Card::distinct()->count('title'));
        $this->assertSame(100, Card::where('stack_id', Stack::where('slug', 'normal')->value('id'))->count());
        $this->assertSame(100, Card::where('stack_id', Stack::where('slug', 'spicy')->value('id'))->count());
        $this->assertSame(100, Card::where('stack_id', Stack::where('slug', '18-plus')->value('id'))->count());
        $this->assertSame(100, Card::where('stack_id', Stack::where('slug', 'normal')->value('id'))->distinct()->count('score'));
        $scores = Card::where('stack_id', Stack::where('slug', 'normal')->value('id'))->orderBy('score')->pluck('score')->map(fn ($score) => (float) $score);
        $this->assertGreaterThanOrEqual(0.01, $scores->first());
        $this->assertSame(99.99, $scores->last());
        $this->assertTrue($scores->every(fn (float $score) => round($score, 2) !== floor($score)));
        $this->assertSame(
            0.76,
            (float) Card::where('title', 'Throw a Surprise Party for the Wrong Person')->value('score')
        );
        $this->assertLessThan(
            (float) Card::where('title', 'Break a Front Tooth on a First Date')->value('score'),
            (float) Card::where('title', 'Lose a Contact Lens Before a Live Interview')->value('score')
        );
        $this->assertLessThan(
            (float) Card::where('title', 'A Flash Flood Rushes Into the Canyon')->value('score'),
            (float) Card::where('title', 'A Pipe Bursts Above Your Bedroom')->value('score')
        );
        $severityCheckpoints = [
            'Throw a Surprise Party for the Wrong Person',
            'Lose a Contact Lens Before a Live Interview',
            'Break a Front Tooth on a First Date',
            'Your Kayak Drifts Away From Shore',
            'A Pipe Bursts Above Your Bedroom',
            'Delete Your Finished Thesis Without a Backup',
            'Airline Loses the Bag With Your Medicine',
            'Get Trapped in an Elevator for Twelve Hours',
            'Ceiling Collapses During Dinner',
            'Discover Someone Stole Your Identity',
            'Wire Your House Deposit to the Wrong Account',
            'Have an Allergic Reaction at a Restaurant',
            'A Flash Flood Rushes Into the Canyon',
        ];
        $checkpointScores = Card::whereIn('title', $severityCheckpoints)->pluck('score', 'title');
        for ($index = 1; $index < count($severityCheckpoints); $index++) {
            $this->assertGreaterThan(
                (float) $checkpointScores[$severityCheckpoints[$index - 1]],
                (float) $checkpointScores[$severityCheckpoints[$index]]
            );
        }
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
        $this->assertSame(6.55, (float) $card->score);
        $this->assertTrue(Game::whereKey($game->id)->exists());
        $this->assertSame(300, Card::count());
    }

    public function test_seeded_premium_packs_can_start_with_eight_players(): void
    {
        Storage::fake('public');
        $this->seed(CardSeeder::class);

        foreach (['spicy', '18-plus'] as $packIndex => $slug) {
            $players = User::factory()->count(8)->create();
            $game = Game::create([
                'code' => $packIndex === 0 ? 'SPCY8001' : 'ADLT8001',
                'owner_id' => $players->first()->id,
            ]);
            $game->members()->attach($players->pluck('id'));

            $this->postJson("/api/games/{$game->id}/start", [
                'user_id' => $players->first()->id,
                'stack' => $slug,
                'target_score' => 12,
            ])->assertOk()->assertJsonPath('data.stack', $slug);

            $this->assertSame(25, DB::table('game_cards')->where('game_id', $game->id)->count());
        }
    }
}
