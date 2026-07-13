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
        Http::assertSent(function ($request) {
            $reference = $request['input_references'][0]['image_url']['url'];
            $referenceSvg = base64_decode(str_replace('data:image/svg+xml;base64,', '', $reference), true);

            return $request->url() === 'https://openrouter.ai/api/v1/images'
            && $request['model'] === 'openai/gpt-image-1'
            && $request['background'] === 'transparent'
            && $request['output_format'] === 'png'
            && str_starts_with($reference, 'data:image/svg+xml;base64,')
            && is_string($referenceSvg)
            && str_contains($referenceSvg, 'Misery Meter main silhouette')
            && str_contains($request['prompt'], 'Never return an amber-only image')
            && str_contains($request['prompt'], 'no eyes')
            && str_contains($request['prompt'], 'every visible person, object, and detail must be a solid filled')
            && str_contains($request['prompt'], 'main human silhouette must ALWAYS be solid pure white #FFFFFF')
            && str_contains($request['prompt'], 'event-specific element that causes or represents the misery must be solid primary amber #FACC15')
            && str_contains($request['prompt'], 'main-silhouette SVG')
            && str_contains($request['prompt'], 'highly creative')
            && str_contains($request['prompt'], 'one person, two people, or three people')
            && str_contains($request['prompt'], 'do not force every situation into a one-person scene')
            && str_contains($request['prompt'], 'people and objects must not look like they are floating')
            && str_contains($request['prompt'], 'road, lane marking, sidewalk, floor')
            && str_contains($request['prompt'], 'extremely simple, clean, geometric, and vector-like')
            && str_contains($request['prompt'], 'never compete with the main action')
            && str_contains($request['prompt'], 'dark #262626 and medium #525252')
            && str_contains($request['prompt'], 'no larger than 100 KB');
        });
    }

    public function test_generate_action_returns_to_cms_with_a_configuration_error(): void
    {
        config(['services.openrouter.key' => null]);
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Flat tire', 'score' => 12, 'image' => '0', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)
            ->from('/cms/cards/'.$card->id.'/edit')
            ->post('/cms/cards/'.$card->id.'/generate')
            ->assertRedirect('/cms/cards/'.$card->id.'/edit')
            ->assertSessionHasErrors('generation');
    }

    public function test_generate_action_returns_to_cms_when_provider_returns_no_image(): void
    {
        config(['services.openrouter.key' => 'test-key']);
        Http::fake(['openrouter.ai/*' => Http::response(['data' => []])]);
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Flat tire', 'score' => 12, 'image' => '0', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)
            ->from('/cms/cards/'.$card->id.'/edit')
            ->post('/cms/cards/'.$card->id.'/generate')
            ->assertRedirect('/cms/cards/'.$card->id.'/edit')
            ->assertSessionHasErrors('generation');
    }

    public function test_generated_card_images_are_served_without_public_storage_symlink(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cards/generated/example.png', 'png-bytes');

        $this->get('/card-images/cards/generated/example.png')
            ->assertOk()
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    }
}
