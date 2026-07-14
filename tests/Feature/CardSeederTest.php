<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Game;
use App\Models\Stack;
use App\Models\User;
use Database\Seeders\CardSeeder;
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
        $this->assertDatabaseMissing('cards', ['title' => 'Duplicate old card']);
        $this->assertDatabaseCount('games', 0);
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('game_cards', 0);
        $this->assertDatabaseCount('moves', 0);
        Storage::disk('public')->assertMissing('cards/generated/old.png');
        Storage::disk('public')->assertMissing('cards/generated-svg/old.svg');
    }
}
