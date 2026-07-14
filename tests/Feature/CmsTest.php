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

    public function test_generate_action_saves_black_background_jpeg_path_on_card(): void
    {
        Storage::fake('public');
        config([
            'services.openrouter.key' => 'test-key',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.image_model' => 'openai/gpt-image-1',
        ]);
        Http::fake(['openrouter.ai/*' => Http::response(['data' => [['b64_json' => $this->testPngBase64()]]])]);
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Flat tire', 'subtitle' => 'In heavy rain', 'score' => 12, 'image' => '0', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)->post('/cms/cards/'.$card->id.'/generate')->assertRedirect();
        $path = $card->fresh()->image;
        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith('.jpg', $path);
        Http::assertSent(function ($request) {
            $reference = $request['input_references'][0]['image_url']['url'];
            $referencePng = base64_decode(str_replace('data:image/png;base64,', '', $reference), true);

            return $request->url() === 'https://openrouter.ai/api/v1/images'
            && $request['model'] === 'openai/gpt-image-1'
            && $request['quality'] === 'high'
            && $request['background'] === 'opaque'
            && $request['output_format'] === 'jpeg'
            && count($request['input_references']) === 5
            && str_starts_with($request['input_references'][1]['image_url']['url'], 'data:image/jpeg;base64,')
            && str_starts_with($request['input_references'][2]['image_url']['url'], 'data:image/jpeg;base64,')
            && str_starts_with($request['input_references'][3]['image_url']['url'], 'data:image/jpeg;base64,')
            && str_starts_with($request['input_references'][4]['image_url']['url'], 'data:image/jpeg;base64,')
            && str_starts_with($reference, 'data:image/png;base64,')
            && is_string($referencePng)
            && str_starts_with($referencePng, "\x89PNG\r\n\x1a\n")
            && str_contains($request['prompt'], 'Never return an amber-only image')
            && str_contains($request['prompt'], 'no eyes')
            && str_contains($request['prompt'], 'every visible person, object, and detail must be a solid filled')
            && str_contains($request['prompt'], 'main human silhouette must ALWAYS be solid pure white #FFFFFF')
            && str_contains($request['prompt'], 'event-specific element that causes or represents the misery must be solid primary amber #FACC15')
            && str_contains($request['prompt'], 'main-silhouette PNG')
            && str_contains($request['prompt'], 'highly creative')
            && str_contains($request['prompt'], 'always depict at least two clearly visible people')
            && str_contains($request['prompt'], 'Never generate a one-person scene')
            && str_contains($request['prompt'], 'obvious main character and primary victim')
            && str_contains($request['prompt'], 'the second person must be a supporting character who reacts')
            && str_contains($request['prompt'], 'when the situation naturally affects a pair or group')
            && str_contains($request['prompt'], 'Grounding rule: nothing may float')
            && str_contains($request['prompt'], 'smooth antialiased curves')
            && str_contains($request['prompt'], 'all four corners and every pixel along all four outer edges')
            && str_contains($request['prompt'], 'Never place the scene inside a rounded rectangle')
            && str_contains($request['prompt'], 'no white corner wedges')
            && str_contains($request['prompt'], 'Absolute frame ban')
            && str_contains($request['prompt'], 'inner border, outer border, white border, amber border')
            && str_contains($request['prompt'], 'White is reserved exclusively for human silhouettes')
            && str_contains($request['prompt'], 'Never create a large white rectangle, block, slab')
            && str_contains($request['prompt'], 'not a collage, infographic, diagram, collection of icons')
            && str_contains($request['prompt'], 'no object of any kind may appear as a free-floating icon')
            && str_contains($request['prompt'], 'Never use a huge foreground block, podium, platform slab')
            && str_contains($request['prompt'], 'fills and visually balances the entire square')
            && str_contains($request['prompt'], 'always include meaningful scene-specific background')
            && str_contains($request['prompt'], 'Three-part composition recipe')
            && str_contains($request['prompt'], 'closely follow the attached good-example image for scale, staging, visual hierarchy')
            && str_contains($request['prompt'], 'ignore and omit its timer display, digits')
            && str_contains($request['prompt'], 'no words, letters, numbers, digits, decimal points, scores')
            && str_contains($request['prompt'], 'never depict a finished game card, card shell')
            && str_contains($request['prompt'], 'exactly these three flat colors')
            && str_contains($request['prompt'], 'Absolute gradient ban')
            && str_contains($request['prompt'], 'Flat solid fills only')
            && str_contains($request['prompt'], 'pure black #000000')
            && str_contains($request['prompt'], 'no pixelation');
        });
    }

    public function test_generate_action_falls_back_to_direct_gemini_when_openrouter_has_insufficient_credit(): void
    {
        Storage::fake('public');
        config([
            'services.openrouter.key' => 'openrouter-test-key',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.gemini_fallback.key' => 'gemini-test-key',
            'services.gemini_fallback.base_url' => 'https://generativelanguage.googleapis.com/v1',
            'services.gemini_fallback.image_model' => 'gemini-3.1-flash-image',
        ]);
        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => ['message' => 'Insufficient credits']], 402),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'inlineData' => [
                                'mimeType' => 'image/png',
                                'data' => $this->testPngBase64(),
                            ],
                        ]],
                    ],
                ]],
            ]),
        ]);
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Flat tire', 'subtitle' => 'In heavy rain', 'score' => 12, 'image' => '0', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)
            ->post('/cms/cards/'.$card->id.'/generate')
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'direct Gemini fallback'));

        Storage::disk('public')->assertExists($card->fresh()->image);
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'generativelanguage.googleapis.com')) {
                return false;
            }

            return $request->url() === 'https://generativelanguage.googleapis.com/v1/models/gemini-3.1-flash-image:generateContent'
                && $request->hasHeader('x-goog-api-key', 'gemini-test-key')
                && $request['generationConfig']['responseModalities'] === ['IMAGE']
                && $request['generationConfig']['imageConfig']['aspectRatio'] === '1:1'
                && $request['generationConfig']['imageConfig']['imageSize'] === '2K'
                && $request['contents'][0]['parts'][2]['inlineData']['mimeType'] === 'image/png'
                && $request['contents'][0]['parts'][4]['inlineData']['mimeType'] === 'image/jpeg'
                && $request['contents'][0]['parts'][6]['inlineData']['mimeType'] === 'image/jpeg'
                && $request['contents'][0]['parts'][7]['inlineData']['mimeType'] === 'image/jpeg'
                && $request['contents'][0]['parts'][8]['inlineData']['mimeType'] === 'image/jpeg';
        });
    }

    public function test_svg_generation_saves_sanitized_cms_only_artwork(): void
    {
        Storage::fake('public');
        config([
            'services.gemini.key' => 'gemini-text-key',
            'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1',
            'services.gemini.text_model' => 'gemini-3.1-flash-lite',
        ]);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024"><circle cx="300" cy="300" r="120" fill="white"/><path fill="#facc15" d="M500 100h180l-80 250h100L420 700l80-260H390z"/><rect x="120" y="720" width="780" height="60" fill="#525252"/></svg>';
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => "```svg\n{$svg}\n```"]]]]],
            ]),
        ]);
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Lose your keys', 'subtitle' => 'Locked outside', 'score' => 18, 'image' => '0', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)
            ->post('/cms/cards/'.$card->id.'/generate-svg')
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'SVG illustration generated'));

        $path = $card->fresh()->svg_img;
        $this->assertStringStartsWith('cards/generated-svg/card-'.$card->id.'-', $path);
        Storage::disk('public')->assertExists($path);
        $storedSvg = Storage::disk('public')->get($path);
        $this->assertStringContainsString('fill="#FFFFFF"', $storedSvg);
        $this->assertStringContainsString('fill="#FACC15"', $storedSvg);
        $this->assertStringNotContainsString('<script', $storedSvg);
        $this->get('/card-images/'.$path)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox")
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        Http::assertSent(fn ($request) => $request->url() === 'https://generativelanguage.googleapis.com/v1/models/gemini-3.1-flash-lite:generateContent'
            && $request->hasHeader('x-goog-api-key', 'gemini-text-key')
            && ! isset($request['systemInstruction'])
            && ! isset($request['generationConfig'])
            && str_contains(data_get($request, 'contents.0.parts.0.text'), 'Lose your keys')
            && str_contains(data_get($request, 'contents.0.parts.0.text'), '<svg'));
    }

    public function test_svg_generation_rejects_executable_svg(): void
    {
        Storage::fake('public');
        config([
            'services.gemini.key' => 'gemini-text-key',
            'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1',
            'services.gemini.text_model' => 'gemini-3.1-flash-lite',
        ]);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><circle cx="50" cy="50" r="20" fill="#FFFFFF"/><path fill="#FACC15" d="M0 0h10v10z"/></svg>']]]]],
            ]),
        ]);
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Unsafe SVG test', 'score' => 18, 'image' => '0', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)
            ->from('/cms/cards/'.$card->id.'/edit')
            ->post('/cms/cards/'.$card->id.'/generate-svg')
            ->assertRedirect('/cms/cards/'.$card->id.'/edit')
            ->assertSessionHasErrors('generation');

        $this->assertNull($card->fresh()->svg_img);
        $this->assertSame([], Storage::disk('public')->allFiles('cards/generated-svg'));
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

    private function testPngBase64(): string
    {
        $image = imagecreatetruecolor(2, 2);
        imagefill($image, 0, 0, imagecolorallocate($image, 250, 204, 21));
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return base64_encode($png);
    }
}
