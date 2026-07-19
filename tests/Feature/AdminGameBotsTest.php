<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\GameController;
use App\Jobs\PlayAutomatedBotTurn;
use App\Http\Middleware\CmsBasicAuth;
use App\Models\Card;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminGameBotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_seven_bots_only_to_an_open_single_player_lobby(): void
    {
        $host = User::factory()->create(['color' => 'yellow']);
        $game = Game::create(['code' => 'BOTS1234', 'owner_id' => $host->id, 'started' => false]);
        $game->members()->attach($host, ['in_lobby' => true]);

        $this->withoutMiddleware(CmsBasicAuth::class)
            ->postJson(route('simulator.rooms.bots.store', $game))
            ->assertOk();

        $this->assertSame(8, $game->members()->count());
        $this->assertSame(7, $game->members()->where('is_bot', true)->count());
        $this->assertSame(8, DB::table('members')->where('game_id', $game->id)->where('in_lobby', true)->count());

        $this->withoutMiddleware(CmsBasicAuth::class)
            ->postJson(route('simulator.rooms.bots.store', $game))
            ->assertStatus(422);
    }

    public function test_backend_job_plays_the_active_bot_through_the_real_move_controller(): void
    {
        Queue::fake();
        $bot = User::factory()->create(['name' => 'BOT 1', 'is_bot' => true, 'color' => 'blue']);
        $human = User::factory()->create(['color' => 'yellow']);
        $current = Card::create(['title' => 'Current', 'score' => 10]);
        Card::create(['title' => 'Next', 'score' => 20]);
        $game = Game::create([
            'code' => 'AUTO1234',
            'owner_id' => $human->id,
            'started' => true,
            'target_score' => 5,
            'current_card_id' => $current->id,
            'current_player_id' => $bot->id,
            'turn_owner_id' => $bot->id,
        ]);
        $game->members()->attach([$bot->id, $human->id]);
        DB::table('game_cards')->insert([
            'game_id' => $game->id,
            'user_id' => null,
            'card_id' => $current->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new PlayAutomatedBotTurn($game->id))->handle(app(GameController::class));

        $this->assertDatabaseHas('moves', ['game_id' => $game->id, 'player_id' => $bot->id]);
        Queue::assertPushed(PlayAutomatedBotTurn::class);
    }
}
