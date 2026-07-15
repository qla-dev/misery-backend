<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CardLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_payload_includes_english_and_bosnian_copy_for_current_and_lane_cards(): void
    {
        $player = User::factory()->create(['color' => 'yellow']);
        $card = Card::create([
            'title' => 'Flat tire',
            'title_bs' => 'Probušena guma',
            'subtitle' => 'Rain starts falling.',
            'subtitle_bs' => 'Počinje padati kiša.',
            'score' => 12.34,
        ]);
        $game = Game::create([
            'code' => 'LANG1234',
            'owner_id' => $player->id,
            'started' => true,
            'current_card_id' => $card->id,
            'current_player_id' => $player->id,
            'turn_owner_id' => $player->id,
        ]);
        $game->members()->attach($player);
        DB::table('game_cards')->insert([
            'game_id' => $game->id,
            'user_id' => $player->id,
            'card_id' => $card->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/games/'.$game->id.'?user_id='.$player->id)
            ->assertOk()
            ->assertJsonPath('data.current_card.title', 'Flat tire')
            ->assertJsonPath('data.current_card.title_bs', 'Probušena guma')
            ->assertJsonPath('data.hands.'.$player->id.'.0.title', 'Flat tire')
            ->assertJsonPath('data.hands.'.$player->id.'.0.title_bs', 'Probušena guma')
            ->assertJsonPath('data.hands.'.$player->id.'.0.subtitle', 'Rain starts falling.')
            ->assertJsonPath('data.hands.'.$player->id.'.0.subtitle_bs', 'Počinje padati kiša.');
    }
}
