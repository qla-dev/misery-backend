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

        return view('cms.screenshots.index', compact('references'));
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
            'people_count' => ['required', 'integer', 'between:0,4'],
            'people_description' => ['nullable', 'string', 'max:500'],
            'app_screen' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:12288'],
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
            : 'Include exactly '.$data['people_count'].' people: '.trim((string) $data['people_description']).'. They support the phone and never cover important UI or headline text.';
        $prompt = implode("\n", [
            'Create a premium Apple App Store portrait marketing screenshot for the Misery Meter party game.',
            'Output is one complete 1290 x 2796 composition, safe for an iPhone App Store screenshot.',
            'Headline (spell exactly): '.$data['headline'],
            'Supporting text (spell exactly): '.trim((string) $data['supporting_text']),
            'Place all marketing text at the '.$data['text_position'].' of the canvas, outside and clearly separated from the phone frame.',
            'Phone presentation angle: '.$data['phone_angle'].'. Show a realistic premium black iPhone frame with the supplied app screen fitted naturally inside it.',
            'Background direction: '.$data['background'].'.',
            $people,
            'MANDATORY BRAND BACKGROUND: every screenshot has a bold Misery-yellow (#FACC15) rainbow made from broad concentric arcs/rays behind the phone. Use yellow, warm gold, black, and white as the dominant palette. The yellow rainbow must be unmistakable in every result.',
            'Use the supplied App Store examples only for hierarchy, commercial polish, phone perspective, spacing, and the relationship between headline, device, and people. Never copy their brands, wording, colors, faces, logos, or app screens.',
            'High-end campaign art, crisp typography, generous safe margins, no watermark, no extra logos, no mockup labels, no text besides the exact headline/supporting text and text already present inside the supplied app UI.',
        ]);

        try {
            $result = $generator->generate($prompt, $references, '9:16');
            $jpeg = $this->appleScreenshotJpeg($result['data']);
            $filename = 'frame-'.str_pad((string) $data['frame'], 2, '0', STR_PAD_LEFT).'-'.Str::uuid().'.jpg';
            Storage::disk('public')->put('screenshots/generated/'.$filename, $jpeg);

            return response()->json([
                'url' => route('cms.screenshots.assets', ['path' => 'generated/'.$filename]),
                'filename' => $filename,
                'width' => 1290,
                'height' => 2796,
                'provider' => 'Gemini',
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

    private function appleScreenshotJpeg(string $image): string
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
        imagecopyresampled($canvas, $source, 0, 0, $cropX, $cropY, 1290, 2796, $cropWidth, $cropHeight);
        ob_start();
        imageinterlace($canvas, true);
        imagejpeg($canvas, null, 92);
        $jpeg = ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);
        throw_unless(is_string($jpeg) && $jpeg !== '', \RuntimeException::class, 'Apple screenshot encoding failed.');

        return $jpeg;
    }
}
