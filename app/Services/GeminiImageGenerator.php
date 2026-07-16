<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiImageGenerator
{
    private const SUPPORTED_IMAGE_TYPES = [
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/heic',
        'image/heif',
        'image/gif',
        'image/bmp',
        'image/tiff',
    ];

    /**
     * @param  array<int, array{mime_type:string,data:string,label?:string}>  $references
     * @return array{data:string,mime_type:string}
     */
    public function generate(string $prompt, array $references = [], string $aspectRatio = '1:1'): array
    {
        $key = (string) config('services.gemini_fallback.key');
        throw_if($key === '', RuntimeException::class, 'FALLBACK_GEMINI_API_KEY or GEMINI_API_KEY is not configured.');

        $promptSections = [$prompt];
        foreach ($references as $index => $reference) {
            $promptSections[] = 'Reference image '.($index + 1).': '.($reference['label'] ?? 'Use its design principles without copying its text, logo, or app identity.');
        }
        $promptSections[] = 'The reference images follow in the same numbered order. Return only the finished image. Do not return explanatory text.';

        $content = [['type' => 'text', 'text' => implode("\n\n", $promptSections)]];
        foreach ($references as $reference) {
            throw_unless(
                in_array($reference['mime_type'], self::SUPPORTED_IMAGE_TYPES, true),
                RuntimeException::class,
                'Unsupported Gemini reference image type: '.$reference['mime_type'].'. Convert it to PNG, JPEG, or WebP first.'
            );
            throw_if($reference['data'] === '', RuntimeException::class, 'Gemini reference image is empty.');

            $content[] = [
                'type' => 'image',
                'mime_type' => $reference['mime_type'],
                'data' => base64_encode($reference['data']),
            ];
        }

        $model = (string) config('services.gemini_fallback.image_model');
        $response = Http::withHeaders(['x-goog-api-key' => $key])
            ->acceptJson()
            ->asJson()
            ->timeout(180)
            ->post(rtrim((string) config('services.gemini_fallback.base_url'), '/').'/interactions', [
                'model' => $model,
                'input' => [[
                    'type' => 'user_input',
                    'content' => $content,
                ]],
                'response_format' => [
                    'type' => 'image',
                    'mime_type' => 'image/jpeg',
                    'aspect_ratio' => $aspectRatio,
                    'image_size' => '2K',
                ],
            ])->throw()->json();

        $images = [];
        $outputImage = data_get($response, 'output_image');
        if (is_array($outputImage)) {
            $images[] = $outputImage;
        }

        foreach ((array) data_get($response, 'steps', []) as $step) {
            if (data_get($step, 'type') !== 'model_output') {
                continue;
            }

            foreach ((array) data_get($step, 'content', []) as $content) {
                if (data_get($content, 'type') === 'image') {
                    $images[] = $content;
                }
            }
        }

        foreach (array_reverse($images) as $image) {
            $encoded = data_get($image, 'data');
            if (! is_string($encoded) || $encoded === '') {
                continue;
            }

            $data = base64_decode($encoded, true);
            throw_if($data === false || $data === '', RuntimeException::class, 'Gemini returned invalid image data.');

            return [
                'data' => $data,
                'mime_type' => (string) (data_get($image, 'mime_type') ?? 'image/jpeg'),
            ];
        }

        throw new RuntimeException('Gemini returned no image.');
    }
}
