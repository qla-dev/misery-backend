<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Services\GeminiImageGenerator;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class ContentGeneratorController extends Controller
{
    private const EXPLAINERS = [
        'The hidden Misery Rate and why players place a card by position instead of guessing its exact number.',
        'How a player builds their Misery Lane from least miserable to most miserable.',
        'How stealing works after a player places a card incorrectly.',
        'How the Game Master guides turns, reveals scores, and shows result overlays.',
        'How a room code lets a group join one live game and play together.',
    ];

    private const HISTORICAL_EVENTS = [
        'The Great Molasses Flood, Boston, 15 January 1919: a storage tank burst and released a destructive wave of molasses through the North End.',
        'The London Beer Flood, 17 October 1814: a huge brewery vat failed and a wave of porter flooded nearby streets and homes.',
        'The Vasa disaster, Stockholm, 10 August 1628: the heavily armed warship sank shortly into its maiden voyage after encountering wind.',
        'The Tacoma Narrows Bridge collapse, Washington, 7 November 1940: wind-driven aeroelastic flutter destroyed the bridge; no people were killed.',
        'The Mars Climate Orbiter loss, 23 September 1999: incompatible metric and imperial units contributed to the spacecraft entering Mars too low.',
        'The Sultana steamboat disaster, Mississippi River, 27 April 1865: overloaded conditions and boiler explosions caused one of the deadliest maritime disasters in United States history.',
        'The Great Smog of London, December 1952: severe air pollution settled over the city for days and caused a major public-health disaster.',
        'The Tay Bridge disaster, Scotland, 28 December 1879: the railway bridge collapsed during a violent storm while a passenger train was crossing.',
    ];

    public function index()
    {
        return view('cms.content.index');
    }

    public function generate(Request $request): JsonResponse
    {
        $selection = $request->validate([
            'format' => ['required', Rule::in(['post', 'story'])],
            'language' => ['required', Rule::in(['en', 'bs'])],
            'mode' => ['required', Rule::in(['custom', 'explainer', 'history'])],
            'brief' => ['nullable', 'string', 'max:1500', Rule::requiredIf($request->input('mode') === 'custom')],
        ]);

        if (! config('services.gemini.key') && ! config('services.openrouter.key')) {
            return response()->json([
                'message' => 'GEMINI_API_KEY and OPENROUTER_API_KEY are not configured on the backend.',
            ], 422);
        }

        $subject = match ($selection['mode']) {
            'custom' => trim((string) $selection['brief']),
            'explainer' => self::EXPLAINERS[array_rand(self::EXPLAINERS)],
            'history' => self::HISTORICAL_EVENTS[array_rand(self::HISTORICAL_EVENTS)],
        };
        $prompt = $this->prompt($selection, $subject);
        $content = null;
        $provider = 'Gemini';

        if (config('services.gemini.key')) {
            try {
                $content = $this->generateWithGemini($prompt);
            } catch (Throwable $error) {
                Log::warning('CMS social content Gemini generation failed', ['exception' => $error]);
                if (! config('services.openrouter.key')) {
                    return $this->generationError($error);
                }
            }
        }

        if (! is_string($content)) {
            $provider = 'OpenRouter';
            try {
                $content = $this->generateWithOpenRouter($prompt);
            } catch (Throwable $error) {
                Log::error('CMS social content OpenRouter generation failed', ['exception' => $error]);

                return $this->generationError($error);
            }
        }

        try {
            $decoded = json_decode($this->stripCodeFence($content), true, 512, JSON_THROW_ON_ERROR);
            $result = $this->normalizeResult($decoded);
        } catch (Throwable) {
            return response()->json(['message' => 'The AI provider returned invalid content. Please try again.'], 502);
        }

        return response()->json([
            'content' => $result,
            'provider' => $provider,
            'source' => $subject,
        ]);
    }

    public function generateSilhouette(Request $request, GeminiImageGenerator $generator): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:1200'],
            'context' => ['nullable', 'string', 'max:1800'],
            'slot' => ['required', Rule::in(['primary', 'secondary'])],
        ]);

        $prompt = implode("\n", [
            'Create one standalone editorial silhouette illustration for Misery Meter.',
            'Scene: '.trim($data['description']),
            'Copy context: '.trim((string) ($data['context'] ?? '')),
            'Draw a crisp, anonymous, featureless human silhouette acting out the scene. Use only pure white and Misery yellow (#FACC15) on a pure black background.',
            'The person must remain fully visible and uncropped. Props may be yellow or white. No gradients, shadows, texture, facial features, letters, words, numbers, logos, borders, cards, or UI.',
            'Keep generous black space around the complete figure so the CMS can position and scale it freely. Strong safety-sign pictogram readability, clean vector-like edges, square canvas.',
            $data['slot'] === 'secondary'
                ? 'This is the smaller supporting silhouette. Use a distinct pose that complements a larger primary figure.'
                : 'This is the primary silhouette. Make the pose immediately readable and visually dominant.',
        ]);

        try {
            $reference = file_get_contents(resource_path('ai/main-silhouette.png'));
            $result = $generator->generate($prompt, [[
                'mime_type' => 'image/png',
                'data' => $reference === false ? '' : $reference,
                'label' => 'Required silhouette style reference. Preserve the anonymous white person and yellow accent language, but create the requested new pose.',
            ]]);
            $extension = str_contains($result['mime_type'], 'jpeg') ? 'jpg' : 'png';
            $filename = $data['slot'].'-'.Str::uuid().'.'.$extension;
            Storage::disk('public')->put('content/silhouettes/'.$filename, $result['data']);

            return response()->json([
                'url' => route('cms.content.assets', ['filename' => $filename]),
                'provider' => 'Gemini',
            ]);
        } catch (Throwable $error) {
            Log::error('CMS silhouette generation failed', ['exception' => $error, 'slot' => $data['slot']]);

            return $this->generationError($error);
        }
    }

    private function generateWithGemini(string $prompt): string
    {
        $response = Http::withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->acceptJson()->asJson()->timeout(120)
            ->post(rtrim((string) config('services.gemini.base_url'), '/').'/models/'.rawurlencode((string) config('services.gemini.text_model')).':generateContent', [
                'contents' => [['role' => 'user', 'parts' => [['text' => $this->systemPrompt()."\n\n".$prompt."\n\nReturn only JSON matching this schema:\n".json_encode($this->schema(), JSON_UNESCAPED_SLASHES)]]]],
                'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.9],
            ])->throw()->json();

        $content = implode('', array_map(
            fn ($part) => is_string(data_get($part, 'text')) ? data_get($part, 'text') : '',
            (array) data_get($response, 'candidates.0.content.parts', [])
        ));
        throw_if($content === '', RuntimeException::class, 'Gemini returned no social content.');

        return $content;
    }

    private function generateWithOpenRouter(string $prompt): string
    {
        $response = Http::withToken(config('services.openrouter.key'))
            ->withHeaders(array_filter([
                'HTTP-Referer' => config('services.openrouter.http_referer'),
                'X-Title' => config('services.openrouter.title'),
            ]))->acceptJson()->asJson()->timeout(120)
            ->post(rtrim((string) config('services.openrouter.base_url'), '/').'/chat/completions', [
                'model' => config('services.openrouter.text_model'),
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.9,
                'response_format' => ['type' => 'json_schema', 'json_schema' => [
                    'name' => 'misery_social_content', 'strict' => true, 'schema' => $this->schema(),
                ]],
            ])->throw()->json();

        $content = data_get($response, 'choices.0.message.content');
        throw_unless(is_string($content) && $content !== '', RuntimeException::class, 'OpenRouter returned no social content.');

        return $content;
    }

    private function systemPrompt(): string
    {
        return implode(' ', [
            'You are the social content editor for Misery Meter, a witty party game about ranking unfortunate events.',
            'Write sharp editorial social copy, never card copy. The composition must feel like a designed magazine poster, not a game card.',
            'Keep historical content factually careful, respectful toward victims, non-graphic, and witty only about absurd circumstances or failed decisions.',
            'For Bosnian, use natural Bosnian rather than Croatian or Serbian, with correct č, ć, dž, đ, š, and ž and careful grammar.',
            'Do not include hashtags in visual fields. Put optional hashtags only in the caption.',
        ]);
    }

    private function prompt(array $selection, string $subject): string
    {
        $language = $selection['language'] === 'bs' ? 'Bosnian' : 'English';
        $format = $selection['format'] === 'story' ? 'Instagram Story 1080x1920' : 'Instagram portrait post 1080x1350';

        return implode("\n", [
            "Create one {$format} concept in {$language}.",
            'Content mode: '.$selection['mode'].'.',
            'Subject or creative brief: '.$subject,
            'The visual system is fixed: black background, yellow accent, white type, mandatory real Misery Meter wordmark with its i-letter mascot, Bebas Neue title, Outfit subtitle/body, and one or two white/yellow human silhouettes.',
            'Write an eyebrow of at most 28 characters, a striking title of at most 62 characters, a subtitle of at most 170 characters, one detail line of at most 72 characters, a CTA of at most 34 characters, and an Instagram caption of at most 500 characters.',
            'Choose the main silhouette_position from top-left, top-center, top-right, center-left, center-center, center-right, bottom-left, bottom-center, bottom-right. Choose silhouette_scale from small, medium, large. Choose accent_style from bolt, timeline, spotlight.',
            'Do not mention the design instructions in the copy. Do not invent historical dates, places, casualty counts, or quotations.',
        ]);
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'eyebrow' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'subtitle' => ['type' => 'string'],
                'detail' => ['type' => 'string'],
                'cta' => ['type' => 'string'],
                'caption' => ['type' => 'string'],
                'silhouette_position' => ['type' => 'string', 'enum' => ['top-left', 'top-center', 'top-right', 'center-left', 'center-center', 'center-right', 'bottom-left', 'bottom-center', 'bottom-right']],
                'silhouette_scale' => ['type' => 'string', 'enum' => ['small', 'medium', 'large']],
                'accent_style' => ['type' => 'string', 'enum' => ['bolt', 'timeline', 'spotlight']],
            ],
            'required' => ['eyebrow', 'title', 'subtitle', 'detail', 'cta', 'caption', 'silhouette_position', 'silhouette_scale', 'accent_style'],
            'additionalProperties' => false,
        ];
    }

    private function normalizeResult(array $decoded): array
    {
        $limits = ['eyebrow' => 28, 'title' => 62, 'subtitle' => 170, 'detail' => 72, 'cta' => 34, 'caption' => 500];
        $result = [];
        foreach ($limits as $field => $limit) {
            $value = trim(preg_replace('/\s+/u', ' ', (string) data_get($decoded, $field, '')) ?? '');
            throw_if($value === '', RuntimeException::class, "Missing {$field}.");
            $result[$field] = mb_substr($value, 0, $limit);
        }
        $positions = ['top-left', 'top-center', 'top-right', 'center-left', 'center-center', 'center-right', 'bottom-left', 'bottom-center', 'bottom-right', 'left', 'center', 'right'];
        $result['silhouette_position'] = in_array(data_get($decoded, 'silhouette_position'), $positions, true) ? data_get($decoded, 'silhouette_position') : 'bottom-right';
        $result['silhouette_scale'] = in_array(data_get($decoded, 'silhouette_scale'), ['small', 'medium', 'large'], true) ? data_get($decoded, 'silhouette_scale') : 'medium';
        $result['accent_style'] = in_array(data_get($decoded, 'accent_style'), ['bolt', 'timeline', 'spotlight'], true) ? data_get($decoded, 'accent_style') : 'bolt';

        return $result;
    }

    private function generationError(Throwable $error): JsonResponse
    {
        $message = $error instanceof RequestException
            ? (string) data_get($error->response?->json(), 'error.message', $error->getMessage())
            : ($error->getMessage() ?: 'Unknown provider error.');

        return response()->json(['message' => 'Content generation failed: '.$message], 502);
    }

    private function stripCodeFence(string $content): string
    {
        return preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content)) ?? trim($content);
    }
}
