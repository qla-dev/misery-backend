<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

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
     * @return array{data:string,mime_type:string,provider:string,model:string}
     */
    public function generate(string $prompt, array $references = [], string $aspectRatio = '1:1'): array
    {
        $promptSections = [$prompt];
        foreach ($references as $index => $reference) {
            $promptSections[] = 'Reference image '.($index + 1).': '.($reference['label'] ?? 'Use its design principles without copying its text, logo, or app identity.');
        }
        $promptSections[] = 'The reference images follow in the same numbered order. Return only the finished image. Do not return explanatory text.';

        foreach ($references as $reference) {
            throw_unless(
                in_array($reference['mime_type'], self::SUPPORTED_IMAGE_TYPES, true),
                RuntimeException::class,
                'Unsupported Gemini reference image type: '.$reference['mime_type'].'. Convert it to PNG, JPEG, or WebP first.'
            );
            throw_if($reference['data'] === '', RuntimeException::class, 'Gemini reference image is empty.');
        }

        $completePrompt = implode("\n\n", $promptSections);
        $openRouterKey = (string) config('services.openrouter.key');
        $openRouterError = null;
        if ($openRouterKey !== '') {
            try {
                return $this->generateWithOpenRouter($completePrompt, $references, $aspectRatio, $openRouterKey);
            } catch (Throwable $error) {
                $openRouterError = $error;
                Log::warning('Shared image generation OpenRouter primary failed; trying direct Gemini fallback', [
                    'model' => config('services.openrouter.image_model'),
                    'error' => $error->getMessage(),
                ]);
            }
        }

        $geminiKey = (string) config('services.gemini_fallback.key');
        if ($geminiKey !== '') {
            try {
                return $this->generateWithDirectGemini($completePrompt, $references, $aspectRatio, $geminiKey);
            } catch (Throwable $error) {
                if ($openRouterError) {
                    throw new RuntimeException(
                        'OpenRouter image generation failed: '.$openRouterError->getMessage().' Direct Gemini fallback failed: '.$error->getMessage(),
                        previous: $error
                    );
                }

                throw $error;
            }
        }

        if ($openRouterError) {
            throw $openRouterError;
        }

        throw new RuntimeException('OPENROUTER_API_KEY is not configured and no direct Gemini fallback key is available.');
    }

    /**
     * @param  array<int, array{mime_type:string,data:string,label?:string}>  $references
     * @return array{data:string,mime_type:string,provider:string,model:string}
     */
    private function generateWithOpenRouter(string $prompt, array $references, string $aspectRatio, string $key): array
    {
        $model = (string) config('services.openrouter.image_model');
        $inputReferences = array_map(fn (array $reference) => [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'data:'.$reference['mime_type'].';base64,'.base64_encode($reference['data']),
            ],
        ], $references);

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'resolution' => '2K',
            'aspect_ratio' => $aspectRatio,
            'quality' => 'high',
            'background' => 'opaque',
            'output_format' => 'jpeg',
            'output_compression' => 92,
        ];
        if ($inputReferences !== []) {
            $payload['input_references'] = $inputReferences;
        }

        $headers = array_filter([
            'HTTP-Referer' => config('services.openrouter.http_referer'),
            'X-Title' => config('services.openrouter.title'),
        ]);
        $response = Http::withToken($key)
            ->withHeaders($headers)
            ->acceptJson()
            ->asJson()
            ->timeout(180)
            ->post(rtrim((string) config('services.openrouter.base_url'), '/').'/images', $payload)
            ->throw()
            ->json();

        $encoded = data_get($response, 'data.0.b64_json');
        throw_unless(is_string($encoded) && $encoded !== '', RuntimeException::class, 'OpenRouter returned no image data.');
        $data = base64_decode($encoded, true);
        throw_if($data === false || $data === '', RuntimeException::class, 'OpenRouter returned invalid image data.');

        return [
            'data' => $data,
            'mime_type' => (string) data_get($response, 'data.0.media_type', 'image/jpeg'),
            'provider' => 'Gemini via OpenRouter',
            'model' => $model,
        ];
    }

    /**
     * @param  array<int, array{mime_type:string,data:string,label?:string}>  $references
     * @return array{data:string,mime_type:string,provider:string,model:string}
     */
    private function generateWithDirectGemini(string $prompt, array $references, string $aspectRatio, string $key): array
    {
        $content = [['type' => 'text', 'text' => $prompt]];
        foreach ($references as $reference) {
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
                'provider' => 'Direct Gemini fallback',
                'model' => $model,
            ];
        }

        throw new RuntimeException('Gemini returned no image.');
    }
}
