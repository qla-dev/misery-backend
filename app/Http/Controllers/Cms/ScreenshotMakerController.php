<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Services\GeminiImageGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ScreenshotMakerController extends Controller
{
    public function index()
    {
        $references = collect(Storage::disk('public')->files('screenshots/references'))
            ->filter(fn (string $path) => preg_match('/\.(png|jpe?g|webp)$/i', $path))
            ->map(fn (string $path) => [
                'name' => basename($path),
                'url' => route('cms.screenshots.assets', ['path' => Str::after($path, 'screenshots/')]),
            ])->values();

        $savedScreenshots = collect(Storage::disk('public')->files('screenshots/saved'))
            ->filter(fn (string $path) => preg_match('/\.(png|jpe?g)$/i', $path))
            ->sortByDesc(fn (string $path) => Storage::disk('public')->lastModified($path))
            ->map(fn (string $path) => [
                'name' => basename($path),
                'url' => route('cms.screenshots.assets', ['path' => Str::after($path, 'screenshots/')]),
            ])->values();

        return view('cms.screenshots.index', compact('references', 'savedScreenshots'));
    }

    public function storeReferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'references' => ['required', 'array', 'min:1', 'max:8'],
            'references.*' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:10240'],
        ]);

        $stored = [];
        foreach ($data['references'] as $file) {
            $name = now()->format('YmdHis').'-'.Str::lower(Str::random(8)).'.'.$file->extension();
            $path = $file->storeAs('screenshots/references', $name, 'public');
            $stored[] = [
                'name' => $name,
                'url' => route('cms.screenshots.assets', ['path' => Str::after($path, 'screenshots/')]),
            ];
        }

        return response()->json(['references' => $stored]);
    }

    public function destroyReference(string $filename): JsonResponse
    {
        abort_if($filename !== basename($filename), 404);
        Storage::disk('public')->delete('screenshots/references/'.$filename);

        return response()->json(['deleted' => true]);
    }

    public function generate(Request $request, GeminiImageGenerator $generator): JsonResponse
    {
        $data = $request->validate([
            'frame' => ['required', 'integer', 'between:1,10'],
            'headline' => ['required', 'string', 'max:90'],
            'supporting_text' => ['nullable', 'string', 'max:180'],
            'text_position' => ['required', Rule::in(['top', 'bottom'])],
            'phone_angle' => ['required', Rule::in(['front', 'left', 'right', 'tilted-left', 'tilted-right', 'close-up'])],
            'background' => ['required', 'string', 'max:300'],
            'static_background' => ['required', Rule::in(['yellow-ribbons', 'sunrise', 'gold-depth', 'dark-radial'])],
            'people_count' => ['required', 'integer', 'between:0,4'],
            'people_description' => ['nullable', 'string', 'max:500'],
            'app_screen' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:12288'],
            'headline_font' => ['nullable', Rule::in(['bebas', 'outfit', 'amatic'])],
            'headline_color' => ['nullable', Rule::in(['white', 'yellow', 'black'])],
            'supporting_font' => ['nullable', Rule::in(['bebas', 'outfit', 'amatic'])],
            'supporting_color' => ['nullable', Rule::in(['white', 'yellow', 'black'])],
        ]);

        $references = $this->savedReferences();
        if ($request->hasFile('app_screen')) {
            $file = $request->file('app_screen');
            $references[] = [
                'mime_type' => (string) $file->getMimeType(),
                'data' => (string) file_get_contents($file->getRealPath()),
                'label' => 'This is the exact Misery Meter app screen. Reproduce its visible UI faithfully inside the phone display. Do not rewrite its interface text or invent a different app.',
            ];
        }

        $people = (int) $data['people_count'] === 0
            ? 'No people. Make the phone and app UI the sole hero.'
            : 'Include exactly '.$data['people_count'].' people: '.trim((string) $data['people_description']).'. They support the phone and never cover the app UI or the reserved copy area.';
        $prompt = implode("\n", [
            'Create only the foreground overlay for a premium Apple App Store portrait screenshot for the Misery Meter party game.',
            'Output is one complete 1290 x 2796 portrait composition. The CMS adds the static background and editable typography later.',
            'ABSOLUTELY NO MARKETING TEXT: do not draw any headline, subtitle, caption, slogan, placeholder letters, mockup labels, or decorative typography anywhere outside the supplied app screen.',
            'Phone presentation angle: '.$data['phone_angle'].'. Show a realistic premium black iPhone frame with the supplied app screen fitted naturally inside it.',
            $people,
            'TRANSPARENCY KEY: place the phone and people over a perfectly flat solid #FF00FF magenta background. The magenta must be completely uniform with no gradients, shadows, texture, floor, reflections, scenery, rainbow, or objects. Never use magenta on the foreground subjects.',
            'Keep the complete phone and every person fully inside the canvas with clean separated edges and generous space around them.',
            'Use the supplied App Store examples only for hierarchy, commercial polish, phone perspective, spacing, and the relationship between headline, device, and people. Never copy their brands, wording, colors, faces, logos, or app screens.',
            'High-end campaign art, generous safe margins, no watermark, no extra logos. Text already visible inside the supplied app UI may remain; generate no other text.',
        ]);

        try {
            $result = $generator->generate($prompt, $references, '9:16');
            $png = $this->appleScreenshotOverlayPng($result['data']);
            $filename = 'frame-'.str_pad((string) $data['frame'], 2, '0', STR_PAD_LEFT).'-'.Str::uuid().'.png';
            Storage::disk('public')->put('screenshots/generated/'.$filename, $png);
            $draft = [
                'frame' => (int) $data['frame'],
                'artwork' => $filename,
                'headline' => $data['headline'],
                'supporting_text' => trim((string) $data['supporting_text']),
                'text_position' => $data['text_position'],
                'static_background' => $data['static_background'],
                'headline_font' => $data['headline_font'] ?? 'bebas',
                'headline_color' => $data['headline_color'] ?? 'black',
                'supporting_font' => $data['supporting_font'] ?? 'outfit',
                'supporting_color' => $data['supporting_color'] ?? 'black',
            ];
            Storage::disk('public')->put('screenshots/drafts/'.pathinfo($filename, PATHINFO_FILENAME).'.json', json_encode($draft, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return response()->json([
                'url' => route('cms.screenshots.assets', ['path' => 'generated/'.$filename]),
                'filename' => $filename,
                'width' => 1290,
                'height' => 2796,
                'provider' => $result['provider'],
                'model' => $result['model'],
                'draft' => $draft,
            ]);
        } catch (Throwable $error) {
            Log::error('CMS App Store screenshot generation failed', [
                'exception' => $error,
                'frame' => $data['frame'],
                'reference_count' => count($references),
            ]);

            return response()->json(['message' => 'Screenshot generation failed: '.($error->getMessage() ?: 'Unknown Gemini error.')], 502);
        }
    }

    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'frame' => ['required', 'integer', 'between:1,10'],
            'image_data' => ['required', 'string', 'max:30000000'],
            'headline' => ['required', 'string', 'max:90'],
            'supporting_text' => ['nullable', 'string', 'max:180'],
            'text_position' => ['required', Rule::in(['top', 'bottom'])],
            'headline_font' => ['required', Rule::in(['bebas', 'outfit', 'amatic'])],
            'headline_color' => ['required', Rule::in(['white', 'yellow', 'black'])],
            'supporting_font' => ['required', Rule::in(['bebas', 'outfit', 'amatic'])],
            'supporting_color' => ['required', Rule::in(['white', 'yellow', 'black'])],
            'static_background' => ['required', Rule::in(['yellow-ribbons', 'sunrise', 'gold-depth', 'dark-radial'])],
            'headline_x' => ['required', 'numeric', 'between:0,100'],
            'headline_y' => ['required', 'numeric', 'between:0,100'],
            'supporting_x' => ['required', 'numeric', 'between:0,100'],
            'supporting_y' => ['required', 'numeric', 'between:0,100'],
        ]);

        abort_unless(
            preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/=\r\n]+)$#', $data['image_data'], $matches) === 1,
            422,
            'The composed screenshot must be a PNG or JPEG data URL.'
        );
        $image = base64_decode(str_replace(["\r", "\n"], '', $matches[2]), true);
        abort_unless(is_string($image) && $image !== '', 422, 'The composed screenshot could not be decoded.');
        $dimensions = getimagesizefromstring($image);
        abort_unless(is_array($dimensions) && $dimensions[0] === 1290 && $dimensions[1] === 2796, 422, 'Saved screenshots must be exactly 1290 × 2796.');

        $extension = $matches[1] === 'jpeg' ? 'jpg' : 'png';
        $base = 'frame-'.str_pad((string) $data['frame'], 2, '0', STR_PAD_LEFT).'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6));
        $filename = $base.'.'.$extension;
        Storage::disk('public')->put('screenshots/saved/'.$filename, $image);
        Storage::disk('public')->put('screenshots/saved/'.$base.'.json', json_encode(collect($data)->except('image_data')->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return response()->json([
            'filename' => $filename,
            'url' => route('cms.screenshots.assets', ['path' => 'saved/'.$filename]),
        ]);
    }

    public function asset(string $path): BinaryFileResponse
    {
        abort_if(str_contains($path, '..') || str_starts_with($path, '/'), 404);
        $storagePath = 'screenshots/'.$path;
        abort_unless(Storage::disk('public')->exists($storagePath), 404);

        return response()->file(Storage::disk('public')->path($storagePath), [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /** @return array<int, array{mime_type:string,data:string,label:string}> */
    private function savedReferences(): array
    {
        return collect(Storage::disk('public')->files('screenshots/references'))
            ->filter(fn (string $path) => preg_match('/\.(png|jpe?g|webp)$/i', $path))
            ->take(8)
            ->map(function (string $path) {
                $absolute = Storage::disk('public')->path($path);

                return [
                    'mime_type' => mime_content_type($absolute) ?: 'image/jpeg',
                    'data' => (string) file_get_contents($absolute),
                    'label' => 'Saved App Store style reference. Learn its hierarchy, device staging, readable headline placement, depth, and commercial finish without copying its brand or text.',
                ];
            })->values()->all();
    }

    private function appleScreenshotOverlayPng(string $image): string
    {
        throw_unless(function_exists('imagecreatefromstring'), \RuntimeException::class, 'GD is required to prepare Apple screenshots.');
        $source = @imagecreatefromstring($image);
        throw_if($source === false, \RuntimeException::class, 'Gemini returned an unreadable image.');

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $targetRatio = 1290 / 2796;
        $sourceRatio = $sourceWidth / $sourceHeight;
        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $cropX = (int) floor(($sourceWidth - $cropWidth) / 2);
            $cropY = 0;
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
            $cropX = 0;
            $cropY = (int) floor(($sourceHeight - $cropHeight) / 2);
        }

        $canvas = imagecreatetruecolor(1290, 2796);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, $cropX, $cropY, 1290, 2796, $cropWidth, $cropHeight);
        for ($y = 0; $y < 2796; $y++) {
            for ($x = 0; $x < 1290; $x++) {
                $color = imagecolorat($canvas, $x, $y);
                $red = ($color >> 16) & 0xff;
                $green = ($color >> 8) & 0xff;
                $blue = $color & 0xff;
                if ($red > 180 && $blue > 180 && $green < 120 && ($red + $blue - 2 * $green) > 240) {
                    imagesetpixel($canvas, $x, $y, imagecolorallocatealpha($canvas, $red, $green, $blue, 127));
                }
            }
        }
        ob_start();
        imagepng($canvas, null, 8);
        $png = ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);
        throw_unless(is_string($png) && $png !== '', \RuntimeException::class, 'Apple screenshot overlay encoding failed.');

        return $png;
    }
}
