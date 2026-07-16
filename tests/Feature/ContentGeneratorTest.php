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
            ->assertSee('Outfit');

        $this->withServerVariables($this->cmsServer())->get('/cms/content-silhouette')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
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
            'services.gemini_fallback.key' => 'image-test-key',
            'services.gemini_fallback.base_url' => 'https://generativelanguage.googleapis.com/v1',
            'services.gemini_fallback.image_model' => 'gemini-image-test',
        ]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'steps' => [['type' => 'model_output', 'content' => [[
                'type' => 'image',
                'mime_type' => 'image/png',
                'data' => base64_encode($png),
            ]]]],
        ])]);

        foreach (['primary', 'secondary'] as $slot) {
            $this->withServerVariables($this->cmsServer())->postJson('/cms/content/generate-silhouette', [
                'slot' => $slot,
                'description' => 'A person reacts to a terrible travel disaster.',
                'context' => 'A terrible trip becomes a party game story.',
            ])->assertOk()->assertJsonPath('provider', 'Gemini');
        }

        $this->assertCount(2, Storage::disk('public')->files('content/silhouettes'));
        Http::assertSent(fn ($request) => $request->url() === 'https://generativelanguage.googleapis.com/v1/interactions'
            && str_contains($request['input'][0]['text'], 'standalone editorial silhouette'));
    }
}
