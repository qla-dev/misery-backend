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
            ->assertSee('Generate artwork for frame 1')
            ->assertSee('Generate artwork for frame 10')
            ->assertSee('Sharp yellow ribbons')
            ->assertSee('Yellow sunrise gradient')
            ->assertSee('data-draggable-text="headline"', false)
            ->assertSee('Text position')
            ->assertSee('Phone angle')
            ->assertSee('Amatic SC')
            ->assertSee('Save composed screenshot')
            ->assertSee('Saved Apple screenshots');
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
            'static_background' => 'yellow-ribbons',
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
        $this->assertCount(1, Storage::disk('public')->files('screenshots/drafts'));
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($files[0]));
        $this->assertSame([1290, 2796], [$width, $height]);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://openrouter.ai/api/v1/images'
                && $request['model'] === 'google/gemini-image-test'
                && $request['resolution'] === '2K'
                && $request['aspect_ratio'] === '9:16'
                && str_contains($request['prompt'], 'perfectly flat solid #FF00FF magenta background')
                && str_contains($request['prompt'], 'Create only the foreground overlay')
                && str_contains($request['prompt'], 'ABSOLUTELY NO TEXT')
                && str_contains($request['prompt'], 'solid #00FF00 green chroma screen')
                && ! str_contains($request['prompt'], 'RANK THE WORST')
                && collect($request['input_references'])->contains(fn ($reference) => str_starts_with($reference['image_url']['url'], 'data:image/jpeg;base64,'))
                && collect($request['input_references'])->contains(fn ($reference) => str_starts_with($reference['image_url']['url'], 'data:image/png;base64,'));
        });
        $this->assertStringContainsString('generated/', $response->json('url'));
    }

    public function test_composed_screenshot_and_editable_metadata_are_saved_permanently(): void
    {
        Storage::fake('public');
        $image = imagecreatetruecolor(1290, 2796);
        imagefill($image, 0, 0, imagecolorallocate($image, 250, 204, 21));
        ob_start();
        imagejpeg($image, null, 88);
        $jpeg = ob_get_clean();
        imagedestroy($image);

        $response = $this->withServerVariables($this->cmsServer())->postJson('/cms/screenshot-maker/save', [
            'frame' => 1,
            'image_data' => 'data:image/jpeg;base64,'.base64_encode($jpeg),
            'headline' => 'RANK THE WORST',
            'supporting_text' => 'Every bad day has a score.',
            'text_position' => 'top',
            'headline_font' => 'amatic',
            'headline_color' => 'black',
            'supporting_font' => 'outfit',
            'supporting_color' => 'yellow',
            'static_background' => 'sunrise',
            'headline_x' => 48.5,
            'headline_y' => 12.25,
            'supporting_x' => 51.5,
            'supporting_y' => 24.75,
            'artwork_x' => 44.5,
            'artwork_y' => 57.25,
        ])->assertOk();

        $savedFiles = collect(Storage::disk('public')->files('screenshots/saved'));
        $this->assertCount(1, $savedFiles->filter(fn ($path) => str_ends_with($path, '.jpg')));
        $this->assertCount(1, $savedFiles->filter(fn ($path) => str_ends_with($path, '.json')));
        $this->assertStringContainsString('saved/', $response->json('url'));
        $this->withServerVariables($this->cmsServer())->get('/cms/screenshot-maker')
            ->assertOk()
            ->assertSee($response->json('filename'));
    }
}
