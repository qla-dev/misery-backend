<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RevenueCatSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_sync_verified_misery_pro_to_profile(): void
    {
        config([
            'services.revenuecat.secret_api_key' => 'rc_secret_test',
            'services.revenuecat.pro_entitlement_id' => 'misery-pro',
        ]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Http::fake([
            "https://api.revenuecat.com/v1/subscribers/misery-user-{$user->id}" => Http::response([
                'subscriber' => ['entitlements' => [
                    'misery-pro' => [
                        'product_identifier' => 'misery_yearly',
                        'purchase_date' => now()->subDay()->toIso8601String(),
                        'expires_date' => now()->addYear()->toIso8601String(),
                    ],
                ]],
            ]),
        ]);

        $this->postJson('/api/auth/revenuecat/sync-pro')
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.app_user_id', "misery-user-{$user->id}")
            ->assertJsonPath('data.user.pro_status', 'yearly')
            ->assertJsonPath('data.user.revenuecat_product_id', 'misery_yearly');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'pro_status' => 'yearly',
            'revenuecat_product_id' => 'misery_yearly',
            'revenuecat_entitlement_id' => 'misery-pro',
        ]);
    }

    public function test_unsupported_entitlement_cannot_unlock_misery_pro(): void
    {
        config(['services.revenuecat.secret_api_key' => 'rc_secret_test']);
        $user = User::factory()->create(['pro_status' => 'monthly']);
        Sanctum::actingAs($user);
        Http::fake([
            "https://api.revenuecat.com/v1/subscribers/misery-user-{$user->id}" => Http::response([
                'subscriber' => ['entitlements' => [
                    'another-app-pro' => [
                        'product_identifier' => 'unrelated_product',
                        'purchase_date' => now()->subDay()->toIso8601String(),
                        'expires_date' => now()->addYear()->toIso8601String(),
                    ],
                ]],
            ]),
        ]);

        $this->postJson('/api/auth/revenuecat/sync-pro')
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.user.pro_status', 'inactive');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'pro_status' => 'inactive',
            'revenuecat_product_id' => null,
        ]);
    }

    public function test_revenuecat_sync_requires_authentication(): void
    {
        $this->postJson('/api/auth/revenuecat/sync-pro')->assertUnauthorized();
    }
}
