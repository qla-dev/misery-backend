<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiImageGenerator
{
    /**
     * @param  array<int, array{mime_type:string,data:string,label?:string}>  $references
     * @return array{data:string,mime_type:string}
     */
    public function generate(string $prompt, array $references = [], string $aspectRatio = '1:1'): array
    {
        $key = (string) config('services.gemini_fallback.key');
        throw_if($key === '', RuntimeException::class, 'FALLBACK_GEMINI_API_KEY or GEMINI_API_KEY is not configured.');

        $parts = [['text' => $prompt]];
        foreach ($references as $index => $reference) {
            $parts[] = ['text' => $reference['label'] ?? 'Visual reference '.($index + 1).'. Use its design principles without copying its text, logo, or app identity.'];
            $parts[] = ['inlineData' => [
                'mimeType' => $reference['mime_type'],
                'data' => base64_encode($reference['data']),
            ]];
        }
        $parts[] = ['text' => 'Return only the finished image. Do not return explanatory text.'];

        $model = (string) config('services.gemini_fallback.image_model');
        $response = Http::withHeaders(['x-goog-api-key' => $key])
            ->acceptJson()
            ->asJson()
            ->timeout(180)
            ->post(rtrim((string) config('services.gemini_fallback.base_url'), '/').'/models/'.rawurlencode($model).':generateContent', [
                'contents' => [['role' => 'user', 'parts' => $parts]],
                'generationConfig' => [
                    'responseModalities' => ['IMAGE'],
                    'imageConfig' => ['aspectRatio' => $aspectRatio, 'imageSize' => '2K'],
                ],
            ])->throw()->json();

        foreach ((array) data_get($response, 'candidates.0.content.parts', []) as $part) {
            $encoded = data_get($part, 'inlineData.data') ?? data_get($part, 'inline_data.data');
            if (! is_string($encoded) || $encoded === '') {
                continue;
            }

            $data = base64_decode($encoded, true);
            throw_if($data === false || $data === '', RuntimeException::class, 'Gemini returned invalid image data.');

            return [
                'data' => $data,
                'mime_type' => (string) (data_get($part, 'inlineData.mimeType') ?? data_get($part, 'inline_data.mime_type') ?? 'image/png'),
            ];
        }

        throw new RuntimeException('Gemini returned no image.');
    }
}
