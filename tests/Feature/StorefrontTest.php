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
}
