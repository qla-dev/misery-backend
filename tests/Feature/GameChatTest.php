<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_member_can_send_a_short_message_and_poll_it_with_the_game(): void
    {
        [$game, $player] = $this->startedGame();

        $this->postJson("/api/games/{$game->id}/messages", [
            'user_id' => $player->id,
            'message' => '  Hajde igraj!  ',
        ])->assertCreated()
            ->assertJsonPath('data.message', 'Hajde igraj!')
            ->assertJsonPath('data.user.id', $player->id);

        $this->getJson("/api/games/{$game->id}?user_id={$player->id}")
            ->assertOk()
            ->assertJsonPath('data.chat_messages.0.message', 'Hajde igraj!')
            ->assertJsonPath('data.chat_messages.0.user.name', $player->name);
    }

    public function test_message_is_limited_to_twenty_characters_and_non_members_cannot_send(): void
    {
        [$game, $player] = $this->startedGame();
        $outsider = User::factory()->create();

        $this->postJson("/api/games/{$game->id}/messages", [
            'user_id' => $player->id,
            'message' => str_repeat('a', 21),
        ])->assertUnprocessable()->assertJsonValidationErrors('message');

        $this->postJson("/api/games/{$game->id}/messages", [
            'user_id' => $outsider->id,
            'message' => 'Pozdrav',
        ])->assertForbidden();
    }

    private function startedGame(): array
    {
        $player = User::factory()->create(['color' => 'yellow']);
        $game = Game::create([
            'code' => 'CHAT1234',
            'owner_id' => $player->id,
            'started' => true,
        ]);
        $game->members()->attach($player);

        return [$game, $player];
    }
}
