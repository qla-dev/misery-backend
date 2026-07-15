<?php

namespace Tests\Feature;

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

        $this->withServerVariables([
            'PHP_AUTH_USER' => config('cms.username'),
            'PHP_AUTH_PW' => config('cms.password'),
        ])->get('/simulator')->assertOk()->assertSee('Create game');
    }
}
