<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_login_creates_user_and_bearer_token(): void
    {
        config()->set('services.google.client_ids', ['google-client-id']);
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google-user-1',
                'aud' => 'google-client-id',
                'email' => 'player@example.com',
                'email_verified' => 'true',
                'name' => 'Test Player',
                'exp' => time() + 3600,
            ]),
        ]);

        $response = $this->postJson('/api/auth/google', ['id_token' => 'valid-token']);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Test Player')
            ->assertJsonPath('user.email', 'player@example.com')
            ->assertJsonPath('is_new_user', true)
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('users', [
            'email' => 'player@example.com',
            'google_id' => 'google-user-1',
        ]);

        $token = $response->json('token');
        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'player@example.com');
    }

    public function test_google_login_reuses_existing_social_user(): void
    {
        config()->set('services.google.client_ids', ['google-client-id']);
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'google-user-1',
                'aud' => 'google-client-id',
                'email' => 'player@example.com',
                'email_verified' => true,
                'name' => 'Test Player',
                'exp' => time() + 3600,
            ]),
        ]);

        $this->postJson('/api/auth/google', ['id_token' => 'first'])->assertOk();
        $this->postJson('/api/auth/google', ['id_token' => 'second'])
            ->assertOk()
            ->assertJsonPath('is_new_user', false);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_social_auth_has_no_password_login_route(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'player@example.com',
            'password' => 'password',
        ])->assertMethodNotAllowed();
    }

    public function test_me_requires_a_bearer_token(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }
}
