<?php

namespace Tests\Feature;

use App\Models\StoreOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_and_legal_pages_serve_the_react_build(): void
    {
        $this->get('/')->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->get('/privacy')->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->get('/terms')->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function test_simulator_requires_the_same_basic_auth_as_cms(): void
    {
        $this->get('/simulator')->assertUnauthorized()->assertHeader('WWW-Authenticate');

        $this->withServerVariables([
            'PHP_AUTH_USER' => config('cms.username'),
            'PHP_AUTH_PW' => config('cms.password'),
        ])->get('/simulator')->assertOk()->assertSee('Create game');
    }

    public function test_store_order_uses_server_price_and_is_saved_pending(): void
    {
        config(['shop.game_price' => 49.90]);

        $this->postJson('/api/store-orders', [
            'name' => 'Amel Test',
            'email' => 'amel@example.com',
            'phone' => '+387 61 123 456',
            'address' => 'Testna 1, Sarajevo',
            'quantity' => 2,
            'language' => 'bs',
            'total' => 0.01,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $order = StoreOrder::firstOrFail();
        $this->assertSame('49.90', $order->unit_price);
        $this->assertSame('99.80', $order->total);
        $this->assertSame('pending', $order->status);
    }
}
