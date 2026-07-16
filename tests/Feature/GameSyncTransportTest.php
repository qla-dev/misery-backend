<?php

namespace Tests\Feature;

use App\Events\GameUpdated;
use App\Models\Game;
use App\Models\Stack;
use App\Models\User;
use App\Services\RealtimeTransportAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GameSyncTransportTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_payload_exposes_polling_transport_without_pusher_secrets(): void
    {
        $host = User::factory()->create();
        $game = Game::create(['code' => 'SYNC1001', 'owner_id' => $host->id]);
        $game->members()->attach($host);

        $this->getJson("/api/games/{$game->id}?user_id={$host->id}")
            ->assertOk()
            ->assertJsonPath('data.sync_driver', 'polling')
            ->assertJsonPath('data.pusher', null)
            ->assertJsonMissing(['secret' => config('broadcasting.connections.pusher.secret')]);
    }

    public function test_pusher_transport_exposes_public_subscription_config_and_broadcasts_changes(): void
    {
        Event::fake([GameUpdated::class]);
        config([
            'game.pusher_app_key' => 'public-key',
            'game.pusher_app_cluster' => 'eu',
        ]);
        $host = User::factory()->create();
        $game = Game::create(['code' => 'SYNC1002', 'owner_id' => $host->id, 'sync_driver' => 'pusher']);
        $game->members()->attach($host);

        $this->getJson("/api/games/{$game->id}/snapshot")
            ->assertOk()
            ->assertJsonPath('data.sync_driver', 'pusher')
            ->assertJsonPath('data.pusher.key', 'public-key')
            ->assertJsonPath('data.pusher.cluster', 'eu')
            ->assertJsonPath('data.pusher.channel', "game.{$game->id}")
            ->assertJsonMissingPath('data.pusher.secret');

        $this->postJson("/api/games/{$game->id}/host-lobby-presence", [
            'user_id' => $host->id,
            'present' => false,
        ])->assertOk();

        Event::assertDispatched(GameUpdated::class, fn (GameUpdated $event) => $event->gameId === $game->id && $event->reason === 'host.presence' && $event->driver === 'pusher'
        );
    }

    public function test_pusher_heartbeat_updates_presence_without_returning_a_full_snapshot(): void
    {
        $host = User::factory()->create();
        $game = Game::create(['code' => 'SYNC1003', 'owner_id' => $host->id, 'sync_driver' => 'pusher']);
        $game->members()->attach($host, ['updated_at' => now()->subSeconds(20)]);
        $before = $game->members()->newPivotStatement()
            ->where('game_id', $game->id)
            ->where('user_id', $host->id)
            ->value('updated_at');

        $this->postJson("/api/games/{$game->id}/heartbeat", ['user_id' => $host->id])
            ->assertNoContent();

        $after = $game->members()->newPivotStatement()
            ->where('game_id', $game->id)
            ->where('user_id', $host->id)
            ->value('updated_at');
        $this->assertNotSame((string) $before, (string) $after);
    }

    public function test_allocator_uses_pusher_then_ably_then_polling_by_capacity(): void
    {
        config([
            'game.realtime_primary_provider' => 'pusher',
            'game.pusher_connection_capacity' => 100,
            'game.ably_connection_capacity' => 200,
        ]);
        $allocator = \Mockery::mock(RealtimeTransportAllocator::class)->makePartial();
        $allocator->shouldReceive('providerAvailable')->with('pusher')->andReturn(true);
        $allocator->shouldReceive('providerAvailable')->with('ably')->andReturn(true);
        $allocator->shouldReceive('usage')->with('pusher')->andReturn(99, 100, 100);
        $allocator->shouldReceive('usage')->with('ably')->andReturn(199, 200);

        $this->assertSame('pusher', $allocator->selectForNewRoom());
        $this->assertSame('ably', $allocator->selectForNewRoom());
        $this->assertSame('polling', $allocator->selectForNewRoom());
    }

    public function test_allocator_can_try_ably_before_pusher(): void
    {
        config([
            'game.realtime_primary_provider' => 'ably',
            'game.pusher_connection_capacity' => 100,
            'game.ably_connection_capacity' => 200,
        ]);
        $allocator = \Mockery::mock(RealtimeTransportAllocator::class)->makePartial();
        $allocator->shouldReceive('providerAvailable')->with('ably')->andReturn(true);
        $allocator->shouldReceive('usage')->with('ably')->andReturn(0);

        $this->assertSame('ably', $allocator->selectForNewRoom());
    }

    public function test_pusher_probe_client_is_constructed_with_supported_options(): void
    {
        config([
            'broadcasting.connections.pusher.app_id' => 'app-id',
            'broadcasting.connections.pusher.key' => 'app-key',
            'broadcasting.connections.pusher.secret' => 'app-secret',
            'broadcasting.connections.pusher.options' => [
                'cluster' => 'us2',
                'host' => 'api-us2.pusher.com',
                'port' => 443,
                'scheme' => 'https',
                'useTLS' => true,
            ],
            'game.provider_probe_timeout_ms' => 2500,
        ]);

        $method = new \ReflectionMethod(RealtimeTransportAllocator::class, 'pusher');
        $client = $method->invoke(app(RealtimeTransportAllocator::class));

        $this->assertInstanceOf(\Pusher\Pusher::class, $client);
    }

    public function test_reverb_override_bypasses_hosted_providers_and_polling(): void
    {
        Event::fake([GameUpdated::class]);
        config([
            'game.reverb_override' => true,
            'game.reverb_app_key' => 'public-reverb-key',
            'game.reverb_host' => 'ws.miserymeter.app',
            'game.reverb_port' => 443,
            'game.reverb_scheme' => 'https',
            'broadcasting.connections.reverb.app_id' => 'misery-meter',
            'broadcasting.connections.reverb.key' => 'public-reverb-key',
            'broadcasting.connections.reverb.secret' => 'server-secret',
            'broadcasting.connections.reverb.options.host' => 'ws.miserymeter.app',
        ]);
        $allocator = \Mockery::mock(RealtimeTransportAllocator::class)->makePartial();
        $allocator->shouldNotReceive('providerAvailable');
        $allocator->shouldNotReceive('usage');

        $this->assertSame('reverb', $allocator->selectForNewRoom());

        $host = User::factory()->create();
        $game = Game::create(['code' => 'SYNC1006', 'owner_id' => $host->id, 'sync_driver' => 'pusher']);
        $game->members()->attach($host);

        $this->getJson("/api/games/{$game->id}/snapshot")
            ->assertOk()
            ->assertJsonPath('data.sync_driver', 'reverb')
            ->assertJsonPath('data.reverb.key', 'public-reverb-key')
            ->assertJsonPath('data.reverb.host', 'ws.miserymeter.app')
            ->assertJsonPath('data.reverb.port', 443)
            ->assertJsonPath('data.pusher', null)
            ->assertJsonPath('data.ably', null)
            ->assertJsonMissingPath('data.reverb.secret');

        $this->postJson("/api/games/{$game->id}/host-lobby-presence", [
            'user_id' => $host->id,
            'present' => false,
        ])->assertOk();

        Event::assertDispatched(
            GameUpdated::class,
            fn (GameUpdated $event) => $event->gameId === $game->id && $event->driver === 'reverb'
        );

        $status = $allocator->status();
        $this->assertSame('reverb_override', $status['mode']);
        $this->assertSame(1, $status['providers']['reverb']['active_players']);
        $this->assertSame('bypassed', $status['providers']['pusher']['state']);
        $this->assertSame('bypassed', $status['providers']['ably']['state']);
        $this->assertSame('bypassed', $status['providers']['polling']['state']);
    }

    public function test_reverb_override_fails_closed_when_credentials_are_missing(): void
    {
        config([
            'game.reverb_override' => true,
            'broadcasting.connections.reverb.app_id' => null,
            'broadcasting.connections.reverb.key' => null,
            'broadcasting.connections.reverb.secret' => null,
            'broadcasting.connections.reverb.options.host' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('REVERB_OVERRIDE is enabled');

        app(RealtimeTransportAllocator::class)->selectForNewRoom();
    }

    public function test_room_creation_persists_the_allocator_choice(): void
    {
        Stack::firstOrCreate(['slug' => 'normal'], ['name' => 'Normal', 'color' => '#000000', 'icon_key' => 'layers']);
        $allocator = \Mockery::mock(RealtimeTransportAllocator::class);
        $allocator->shouldReceive('selectForNewRoom')->once()->andReturn('pusher');
        $this->app->instance(RealtimeTransportAllocator::class, $allocator);

        $this->postJson('/api/games', ['name' => 'Host', 'color' => 'yellow', 'stack' => 'normal'])
            ->assertCreated()
            ->assertJsonPath('game.sync_driver', 'pusher');

        $this->assertDatabaseHas('games', ['sync_driver' => 'pusher']);
    }

    public function test_simulator_can_request_an_initial_transport_for_a_room(): void
    {
        Stack::firstOrCreate(['slug' => 'normal'], ['name' => 'Normal', 'color' => '#000000', 'icon_key' => 'layers']);
        $allocator = \Mockery::mock(RealtimeTransportAllocator::class);
        $allocator->shouldReceive('selectForNewRoom')->once()->with('ably')->andReturn('ably');
        $this->app->instance(RealtimeTransportAllocator::class, $allocator);

        $this->postJson('/api/games', [
            'name' => 'Simulator Host',
            'color' => 'yellow',
            'stack' => 'normal',
            'sync_driver' => 'ably',
        ])->assertCreated()->assertJsonPath('game.sync_driver', 'ably');
    }

    public function test_native_join_is_rejected_with_a_stable_error_code_when_provider_is_full(): void
    {
        $host = User::factory()->create();
        $game = Game::create(['code' => 'FULL1001', 'owner_id' => $host->id, 'sync_driver' => 'pusher']);
        $game->members()->attach($host);

        $allocator = \Mockery::mock(RealtimeTransportAllocator::class);
        $allocator->shouldReceive('providerAvailable')->with('pusher')->once()->andReturn(true);
        $allocator->shouldReceive('providerAvailable')->with('ably')->once()->andReturn(true);
        $allocator->shouldReceive('canJoinWithoutReallocation')->once()->andReturn(false);
        $allocator->shouldNotReceive('ensureCapacityForJoin');
        $this->app->instance(RealtimeTransportAllocator::class, $allocator);

        $this->postJson('/api/games/code/FULL1001/join', [
            'name' => 'Native Player',
            'color' => 'blue',
            'client' => 'native',
        ])->assertStatus(409)
            ->assertJsonPath('error_code', 'realtime_provider_capacity_exceeded');

        $this->assertSame(1, $game->members()->count());
    }

    public function test_ably_room_returns_scoped_token_request_without_exposing_secret(): void
    {
        config(['broadcasting.connections.ably.key' => 'app.key:super-secret']);
        $host = User::factory()->create();
        $game = Game::create(['code' => 'SYNC1004', 'owner_id' => $host->id, 'sync_driver' => 'ably']);
        $game->members()->attach($host);

        $this->getJson("/api/games/{$game->id}/snapshot")
            ->assertOk()
            ->assertJsonPath('data.sync_driver', 'ably')
            ->assertJsonPath('data.ably.channel', "game.{$game->id}")
            ->assertJsonPath('data.pusher', null);

        $this->getJson("/api/games/{$game->id}/realtime-token?user_id={$host->id}")
            ->assertOk()
            ->assertJsonPath('keyName', 'app.key')
            ->assertJsonMissing(['secret' => 'super-secret']);
    }

    public function test_ably_room_updates_use_the_direct_ably_publisher(): void
    {
        Event::fake([GameUpdated::class]);
        $host = User::factory()->create();
        $game = Game::create(['code' => 'SYNC1007', 'owner_id' => $host->id, 'sync_driver' => 'ably']);
        $game->members()->attach($host);

        $allocator = \Mockery::mock(RealtimeTransportAllocator::class);
        $allocator->shouldReceive('publishAblyGameUpdate')
            ->once()
            ->with($game->id, 'host.presence');
        $this->app->instance(RealtimeTransportAllocator::class, $allocator);

        $this->postJson("/api/games/{$game->id}/host-lobby-presence", [
            'user_id' => $host->id,
            'present' => false,
        ])->assertOk();

        Event::assertNotDispatched(GameUpdated::class);
    }

    public function test_full_pusher_room_moves_to_ably_before_accepting_another_connection(): void
    {
        $host = User::factory()->create();
        $game = Game::create(['code' => 'SYNC1005', 'owner_id' => $host->id, 'sync_driver' => 'pusher']);
        $game->members()->attach($host);
        config(['game.pusher_connection_capacity' => 1, 'game.ably_connection_capacity' => 200]);

        $allocator = \Mockery::mock(RealtimeTransportAllocator::class)->makePartial();
        $allocator->shouldReceive('providerAvailable')->with('pusher')->andReturn(true);
        $allocator->shouldReceive('providerAvailable')->with('ably')->andReturn(true);
        $allocator->shouldReceive('usage')->with('pusher')->andReturn(1);
        $allocator->shouldReceive('usage')->with('ably')->andReturn(0);

        $this->assertFalse($allocator->canJoinWithoutReallocation($game));
        $this->assertSame('ably', $allocator->ensureCapacityForJoin($game));
        $this->assertSame('ably', $game->refresh()->sync_driver);
    }
}
