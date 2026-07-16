<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_and_legal_pages_serve_the_react_build(): void
    {
        $this->get('/')->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->get('/cookies')->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->get('/privacy')->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->get('/terms')->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $this->assertStringContainsString('The party game of terrible decisions', file_get_contents(public_path('dist/index.html')));
        $this->assertStringContainsString('Cookie Policy | Misery Meter', file_get_contents(public_path('dist/cookies/index.html')));
        $this->assertStringContainsString('Privacy Policy | Misery Meter', file_get_contents(public_path('dist/privacy/index.html')));
        $this->assertStringContainsString('Terms of Use | Misery Meter', file_get_contents(public_path('dist/terms/index.html')));
        $this->assertStringContainsString('https://miserymeter.app/', file_get_contents(public_path('dist/index.html')));
        $this->assertStringContainsString('https://miserymeter.app/cookies', file_get_contents(public_path('dist/cookies/index.html')));
        $this->assertStringContainsString('https://miserymeter.app/privacy', file_get_contents(public_path('dist/privacy/index.html')));
        $this->assertStringContainsString('https://miserymeter.app/terms', file_get_contents(public_path('dist/terms/index.html')));
    }

    public function test_landing_social_image_is_a_large_png(): void
    {
        $response = $this->get('/misery-og.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $size = getimagesizefromstring($response->getContent());

        $this->assertSame(1200, $size[0]);
        $this->assertSame(630, $size[1]);
    }

    public function test_simulator_requires_the_same_basic_auth_as_cms(): void
    {
        $this->get('/simulator')->assertUnauthorized()->assertHeader('WWW-Authenticate');
        $this->get('/simulator/realtime-status')->assertUnauthorized()->assertHeader('WWW-Authenticate');
        $this->get('/simulator/rooms')->assertUnauthorized()->assertHeader('WWW-Authenticate');
        $user = User::factory()->create();
        $game = Game::create(['code' => 'SPEC1001', 'owner_id' => $user->id]);
        $game->members()->attach($user);
        $this->get("/simulator/rooms/{$game->id}")->assertUnauthorized()->assertHeader('WWW-Authenticate');

        $this->withServerVariables([
            'PHP_AUTH_USER' => config('cms.username'),
            'PHP_AUTH_PW' => config('cms.password'),
        ])->get('/simulator')
            ->assertOk()
            ->assertSee('Create game')
            ->assertSee('Game chat')
            ->assertSee('Leave room')
            ->assertSee('onclick="move(true)"', false)
            ->assertSee('onclick="move(false)"', false)
            ->assertSee('Correct')
            ->assertSee('Wrong')
            ->assertSee('Spectator')
            ->assertSee('openSpectator', false)
            ->assertSee('Game finished', false)
            ->assertSee('Live transport state')
            ->assertSee('cdn.ably.com/lib/ably.min-2.js', false)
            ->assertSee('connectPusherProtocol', false)
            ->assertSee("'/messages'", false);
    }

    public function test_simulator_reports_live_transport_allocation_and_all_rooms(): void
    {
        config([
            'game.pusher_connection_capacity' => 100,
            'game.ably_connection_capacity' => 200,
        ]);
        $pusherPlayer = User::factory()->create();
        $pollingPlayer = User::factory()->create();
        $pusherGame = Game::create(['code' => 'PUSH1001', 'owner_id' => $pusherPlayer->id, 'started' => true, 'sync_driver' => 'pusher']);
        $pollingGame = Game::create(['code' => 'POLL1001', 'owner_id' => $pollingPlayer->id, 'sync_driver' => 'polling']);
        $pusherGame->members()->attach($pusherPlayer, ['updated_at' => now()]);
        $pollingGame->members()->attach($pollingPlayer, ['updated_at' => now()]);

        $auth = $this->withServerVariables([
            'PHP_AUTH_USER' => config('cms.username'),
            'PHP_AUTH_PW' => config('cms.password'),
        ]);

        $auth->getJson('/simulator/realtime-status')
            ->assertOk()
            ->assertJsonPath('providers.pusher.active_players', 1)
            ->assertJsonPath('providers.pusher.limit', 100)
            ->assertJsonPath('providers.polling.active_players', 1)
            ->assertJsonPath('providers.polling.limit', null);

        $auth->getJson('/simulator/rooms')
            ->assertOk()
            ->assertJsonFragment(['code' => 'PUSH1001', 'started' => true, 'sync_driver' => 'pusher'])
            ->assertJsonFragment(['code' => 'POLL1001', 'started' => false, 'sync_driver' => 'polling']);
    }

    public function test_admin_spectator_snapshot_is_read_only_and_exposes_finished_state(): void
    {
        $winner = User::factory()->create();
        $game = Game::create([
            'code' => 'DONE1001',
            'owner_id' => $winner->id,
            'started' => true,
            'winner_id' => $winner->id,
            'sync_driver' => 'polling',
        ]);
        $game->members()->attach($winner, ['updated_at' => now()->subMinute()]);
        $memberUpdatedAt = $game->members()->firstOrFail()->pivot->updated_at->toISOString();

        $this->withServerVariables([
            'PHP_AUTH_USER' => config('cms.username'),
            'PHP_AUTH_PW' => config('cms.password'),
        ])->getJson("/simulator/rooms/{$game->id}")
            ->assertOk()
            ->assertJsonPath('data.code', 'DONE1001')
            ->assertJsonPath('data.winner_id', $winner->id)
            ->assertJsonCount(1, 'data.members');

        $this->assertSame(1, $game->members()->count());
        $this->assertSame($memberUpdatedAt, $game->members()->firstOrFail()->pivot->updated_at->toISOString());
    }
}
