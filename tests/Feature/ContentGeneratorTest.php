<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentGeneratorTest extends TestCase
{
    private function cmsServer(): array
    {
        return ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];
    }

    public function test_content_studio_exposes_hq_post_and_story_workflow(): void
    {
        $this->withServerVariables($this->cmsServer())->get('/cms/content')
            ->assertOk()
            ->assertSee('Content studio')
            ->assertSee('1080 × 1350')
            ->assertSee('1080 × 1920')
            ->assertSee('Export HQ PNG')
            ->assertSee('Locked brand guidance')
            ->assertSee('Bebas Neue')
            ->assertSee('Outfit')
            ->assertSee('Amatic SC')
            ->assertSee('Misery yellow')
            ->assertSee('styleTitleFont')
            ->assertSee('styleSubtitleColor');

        $this->withServerVariables($this->cmsServer())->get('/cms/content-silhouette')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');

        $this->withServerVariables($this->cmsServer())->get('/cms/content-logo-letter')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_content_studio_generates_editable_social_copy_with_gemini(): void
    {
        config([
            'services.gemini.key' => 'content-test-key',
            'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1',
            'services.gemini.text_model' => 'gemini-test-model',
            'services.openrouter.key' => null,
        ]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode([
                'eyebrow' => 'THE MISERY ARCHIVE',
                'title' => 'A WAVE NOBODY ORDERED',
                'subtitle' => 'Boston learned that even molasses can move far too fast when a storage tank gives up.',
                'detail' => 'BOSTON · 15 JANUARY 1919',
                'cta' => 'WOULD YOU TRY YOUR LUCK?',
                'caption' => 'A sticky chapter from the Misery Archive. #MiseryMeter',
                'silhouette_position' => 'left',
                'silhouette_scale' => 'large',
                'accent_style' => 'timeline',
            ], JSON_UNESCAPED_UNICODE)]]]]],
        ])]);

        $this->withServerVariables($this->cmsServer())->postJson('/cms/content/generate', [
            'mode' => 'history',
            'format' => 'story',
            'language' => 'en',
            'brief' => '',
        ])->assertOk()
            ->assertJsonPath('provider', 'Gemini')
            ->assertJsonPath('content.title', 'A WAVE NOBODY ORDERED')
            ->assertJsonPath('content.silhouette_position', 'left')
            ->assertJsonPath('content.accent_style', 'timeline');

        Http::assertSent(function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'];

            return str_contains($prompt, 'Instagram Story 1080x1920')
                && str_contains($prompt, 'mandatory real Misery Meter wordmark')
                && str_contains($prompt, 'one or two white/yellow human silhouettes')
                && str_contains($prompt, 'Bebas Neue title')
                && str_contains($prompt, 'Outfit subtitle/body');
        });
    }

    public function test_custom_content_requires_a_brief(): void
    {
        $this->withServerVariables($this->cmsServer())->postJson('/cms/content/generate', [
            'mode' => 'custom',
            'format' => 'post',
            'language' => 'bs',
            'brief' => '',
        ])->assertUnprocessable()->assertJsonValidationErrors('brief');
    }

    public function test_content_studio_generates_both_editable_silhouette_slots_with_gemini(): void
    {
        Storage::fake('public');
        config([
            'services.openrouter.key' => 'image-test-key',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.image_model' => 'google/gemini-image-test',
            'services.gemini_fallback.key' => null,
        ]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Http::fake(['openrouter.ai/*' => Http::response([
            'data' => [[
                'media_type' => 'image/png',
                'b64_json' => base64_encode($png),
            ]],
        ])]);

        foreach (['primary', 'secondary'] as $slot) {
            $this->withServerVariables($this->cmsServer())->postJson('/cms/content/generate-silhouette', [
                'slot' => $slot,
                'description' => 'A person reacts to a terrible travel disaster.',
                'context' => 'A terrible trip becomes a party game story.',
            ])->assertOk()
                ->assertJsonPath('provider', 'Gemini via OpenRouter')
                ->assertJsonPath('model', 'google/gemini-image-test');
        }

        $this->assertCount(2, Storage::disk('public')->files('content/silhouettes'));
        Http::assertSent(fn ($request) => $request->url() === 'https://openrouter.ai/api/v1/images'
            && $request['model'] === 'google/gemini-image-test'
            && str_contains($request['prompt'], 'standalone editorial silhouette')
            && collect($request['input_references'])->contains(fn ($reference) => str_starts_with($reference['image_url']['url'], 'data:image/png;base64,'))
            && collect($request['input_references'])->every(fn ($reference) => ! str_starts_with($reference['image_url']['url'], 'data:image/svg+xml;base64,')));
    }
}
