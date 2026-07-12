<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Stack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cms_requires_basic_auth(): void
    {
        $this->get('/cms')->assertUnauthorized()->assertHeader('WWW-Authenticate');
    }

    public function test_authenticated_cms_lists_and_updates_cards(): void
    {
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Bad day', 'subtitle' => 'Very bad', 'score' => 10, 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)->get('/cms/cards')->assertOk()->assertSee('Bad day');
        $this->withServerVariables($server)->put('/cms/cards/'.$card->id, [
            'title' => 'Worse day', 'subtitle' => 'Much worse', 'score' => 20.5,
            'stack_id' => $stack->id, 'image' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('cards', ['id' => $card->id, 'title' => 'Worse day', 'deck' => 'normal']);
    }

    public function test_generate_action_saves_transparent_png_path_on_card(): void
    {
        Storage::fake('public');
        config([
            'services.openrouter.key' => 'test-key',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.image_model' => 'openai/gpt-image-1',
        ]);
        Http::fake(['openrouter.ai/*' => Http::response(['data' => [['b64_json' => base64_encode('png-bytes')]]])]);
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Flat tire', 'subtitle' => 'In heavy rain', 'score' => 12, 'image' => '0', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)->post('/cms/cards/'.$card->id.'/generate')->assertRedirect();
        $path = $card->fresh()->image;
        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith('.png', $path);
        Http::assertSent(fn ($request) => $request->url() === 'https://openrouter.ai/api/v1/images'
            && $request['model'] === 'openai/gpt-image-1'
            && $request['background'] === 'transparent'
            && $request['output_format'] === 'png'
            && str_contains($request['prompt'], 'pure white #FFFFFF and primary amber #FACC15'));
    }
}
