<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScreenshotMakerTest extends TestCase
{
    private function cmsServer(): array
    {
        return ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];
    }

    public function test_screenshot_maker_has_ten_editable_gemini_frames(): void
    {
        Storage::fake('public');

        $this->withServerVariables($this->cmsServer())->get('/cms/screenshot-maker')
            ->assertOk()
            ->assertSee('Screenshot Maker')
            ->assertSee('FRAME 01')
            ->assertSee('FRAME 10')
            ->assertSee('Generate frame 1 with Gemini')
            ->assertSee('Generate frame 10 with Gemini')
            ->assertSee('YELLOW RAINBOW')
            ->assertSee('Text position')
            ->assertSee('Phone angle');
    }

    public function test_references_are_saved_and_sent_to_gemini_for_exact_apple_output(): void
    {
        Storage::fake('public');
        config([
            'services.openrouter.key' => 'screenshot-test-key',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.image_model' => 'google/gemini-image-test',
            'services.gemini_fallback.key' => null,
        ]);

        $this->withServerVariables($this->cmsServer())->post('/cms/screenshot-maker/references', [
            'references' => [UploadedFile::fake()->image('app-store-reference.jpg', 300, 600)],
        ])->assertOk();
        $this->assertCount(1, Storage::disk('public')->files('screenshots/references'));

        $source = imagecreatetruecolor(360, 640);
        $yellow = imagecolorallocate($source, 250, 204, 21);
        imagefill($source, 0, 0, $yellow);
        ob_start();
        imagepng($source);
        $png = ob_get_clean();
        imagedestroy($source);
        Http::fake(['openrouter.ai/*' => Http::response([
            'data' => [[
                'media_type' => 'image/png',
                'b64_json' => base64_encode($png),
            ]],
        ])]);

        $response = $this->withServerVariables($this->cmsServer())->post('/cms/screenshot-maker/generate', [
            'frame' => 1,
            'headline' => 'RANK THE WORST',
            'supporting_text' => 'Every bad day has a score.',
            'text_position' => 'top',
            'phone_angle' => 'tilted-right',
            'background' => 'Black depth with bright yellow arcs.',
            'people_count' => 2,
            'people_description' => 'Two diverse friends laughing together.',
            'app_screen' => UploadedFile::fake()->image('misery-screen.png', 390, 844),
        ])->assertOk()
            ->assertJsonPath('width', 1290)
            ->assertJsonPath('height', 2796)
            ->assertJsonPath('provider', 'Gemini via OpenRouter')
            ->assertJsonPath('model', 'google/gemini-image-test');

        $files = Storage::disk('public')->files('screenshots/generated');
        $this->assertCount(1, $files);
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($files[0]));
        $this->assertSame([1290, 2796], [$width, $height]);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://openrouter.ai/api/v1/images'
                && $request['model'] === 'google/gemini-image-test'
                && $request['resolution'] === '2K'
                && $request['aspect_ratio'] === '9:16'
                && str_contains($request['prompt'], 'Misery-yellow (#FACC15) rainbow')
                && collect($request['input_references'])->contains(fn ($reference) => str_starts_with($reference['image_url']['url'], 'data:image/jpeg;base64,'))
                && collect($request['input_references'])->contains(fn ($reference) => str_starts_with($reference['image_url']['url'], 'data:image/png;base64,'));
        });
        $this->assertStringContainsString('generated/', $response->json('url'));
    }
}
