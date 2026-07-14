<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Stack;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class CardGeneratorController extends Controller
{
    private const GENERATION_COUNT = 10;

    private const THEMES = [
        'mixed' => 'Mixed situations',
        'travel' => 'Travel disasters',
        'home' => 'Home and property',
        'work' => 'Work and technology',
        'money' => 'Money and legal',
        'social' => 'Social embarrassment and events',
        'health' => 'Health and public misery',
        'outdoors' => 'Nature and outdoors',
        'digital' => 'Modern digital disasters',
        'spectacular' => 'Spectacular near-catastrophes',
    ];

    private const SEVERITIES = [
        'mixed' => ['Mixed across the full scale', 0.01, 99.99],
        'low' => ['Low misery', 0.01, 24.99],
        'medium' => ['Moderate misery', 25.00, 49.99],
        'high' => ['High misery', 50.00, 74.99],
        'extreme' => ['Extreme misery', 75.00, 99.99],
    ];

    public function index()
    {
        return view('cms.generator', [
            'themes' => self::THEMES,
            'severities' => self::SEVERITIES,
        ]);
    }

    public function generate(Request $request)
    {
        $selection = $request->validate([
            'theme' => ['required', Rule::in(array_keys(self::THEMES))],
            'severity' => ['required', Rule::in(array_keys(self::SEVERITIES))],
        ]);

        if (! config('services.gemini.key') && ! config('services.openrouter.key')) {
            return back()->withInput()->withErrors([
                'generation' => 'GEMINI_API_KEY and OPENROUTER_API_KEY are not configured on the backend.',
            ]);
        }

        $existingCards = Card::query()->orderBy('score')->get(['title', 'subtitle', 'score']);
        $prompt = $this->generationPrompt($selection, $existingCards->toArray());
        $providerUsed = 'Gemini';
        $content = null;

        if (config('services.gemini.key')) {
            try {
                $content = $this->generateWithGemini($prompt);
            } catch (Throwable $error) {
                Log::warning('CMS card-content generation Gemini primary failed', ['exception' => $error]);
                if (! config('services.openrouter.key')) {
                    return back()->withInput()->withErrors(['generation' => 'Gemini generation failed: '.$this->providerError($error)]);
                }
            }
        }

        if (! is_string($content)) {
            $providerUsed = 'OpenRouter fallback';
            try {
                $content = $this->generateWithOpenRouter($prompt);
            } catch (Throwable $error) {
                Log::error('CMS card-content generation OpenRouter fallback failed', ['exception' => $error]);

                return back()->withInput()->withErrors(['generation' => 'Card generation failed: '.$this->providerError($error)]);
            }
        }

        try {
            $decoded = json_decode($this->stripCodeFence($content), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return back()->withInput()->withErrors(['generation' => 'The AI provider returned invalid JSON. Please try again.']);
        }

        $accepted = $this->uniqueCandidates(data_get($decoded, 'cards', []), $existingCards->pluck('title')->all(), $selection['severity']);
        if (count($accepted) < self::GENERATION_COUNT) {
            return back()->withInput()->withErrors([
                'generation' => 'The AI did not produce 10 valid, unique card situations. Nothing was saved; please try again.',
            ]);
        }

        $stack = Stack::where('slug', 'normal')->firstOrFail();
        DB::transaction(function () use ($accepted, $stack) {
            foreach (array_slice($accepted, 0, self::GENERATION_COUNT) as $candidate) {
                Card::create([
                    'title' => $candidate['title'],
                    'subtitle' => $candidate['description'],
                    'score' => $candidate['score'],
                    'status' => false,
                    'image' => '0',
                    'deck' => $stack->slug,
                    'stack_id' => $stack->id,
                ]);
            }
        });

        return redirect()->route('cms.cards.index')->with(
            'success',
            '10 unique card situations with suggested misery scores generated via '.$providerUsed.'.'
        );
    }

    private function generateWithGemini(string $prompt): string
    {
        $model = (string) config('services.gemini.text_model');
        $response = Http::withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->acceptJson()->asJson()->timeout(120)
            ->post(rtrim((string) config('services.gemini.base_url'), '/').'/models/'.rawurlencode($model).':generateContent', [
                'contents' => [['role' => 'user', 'parts' => [['text' => implode("\n\n", [
                    $this->systemPrompt(),
                    $prompt,
                    'Return only JSON matching this exact schema, with no Markdown or explanation:',
                    json_encode($this->cardSchema(), JSON_UNESCAPED_SLASHES),
                ])]]]],
            ])->throw()->json();

        $content = implode('', array_map(
            fn ($part) => is_string(data_get($part, 'text')) ? data_get($part, 'text') : '',
            (array) data_get($response, 'candidates.0.content.parts', [])
        ));
        throw_if($content === '', RuntimeException::class, 'Gemini returned no card data.');

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
                'temperature' => 0.95,
                'response_format' => ['type' => 'json_schema', 'json_schema' => [
                    'name' => 'card_content_batch', 'strict' => true, 'schema' => $this->cardSchema(),
                ]],
            ])->throw()->json();

        $content = data_get($response, 'choices.0.message.content');
        throw_unless(is_string($content) && $content !== '', RuntimeException::class, 'OpenRouter returned no card data.');

        return $content;
    }

    private function systemPrompt(): string
    {
        return implode(' ', [
            'You are the senior card editor for Misery Index, a party game about ranking unfortunate situations.',
            'Create vivid, specific, instantly understandable situations that are funny to discuss but plausible enough to score.',
            'Every card needs a concise title, one sentence of concrete context, and a carefully judged misery score with exactly two decimal places.',
            'Score relative to the supplied existing deck: trivial inconvenience is near 0, life-altering catastrophe is near 100.',
            'Avoid trivia questions, answers, fantasy, death, graphic injury, duplicate concepts, vague wording, and alternate wording of an existing card.',
        ]);
    }

    private function generationPrompt(array $selection, array $existing): string
    {
        [$severityLabel, $minimum, $maximum] = self::SEVERITIES[$selection['severity']];
        $excluded = implode("\n", array_map(
            fn (array $card) => '- '.$card['title'].' | '.$card['subtitle'].' | score '.$card['score'],
            array_slice($existing, -300)
        ));

        return implode("\n", [
            'Generate 18 candidate Misery Index cards so the application can retain the best 10.',
            'Theme: '.self::THEMES[$selection['theme']],
            "Requested severity: {$severityLabel}; every score must be between {$minimum} and {$maximum}.",
            'Use natural English. Title: 4–12 words. Description: one vivid sentence under 180 characters.',
            'Give every score exactly two decimal places and judge it against the existing deck examples below.',
            'Make candidates meaningfully different from each other and from all existing cards.',
            '',
            'Existing cards to exclude and use as scoring anchors:',
            $excluded === '' ? '(No existing cards.)' : $excluded,
        ]);
    }

    private function cardSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['cards' => [
                'type' => 'array', 'minItems' => self::GENERATION_COUNT, 'maxItems' => 20,
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'score' => ['type' => 'number'],
                    ],
                    'required' => ['title', 'description', 'score'],
                    'additionalProperties' => false,
                ],
            ]],
            'required' => ['cards'], 'additionalProperties' => false,
        ];
    }

    private function uniqueCandidates(mixed $candidates, array $existingTitles, string $severity): array
    {
        if (! is_array($candidates)) {
            return [];
        }
        [, $minimum, $maximum] = self::SEVERITIES[$severity];
        $pool = array_map([$this, 'normalize'], $existingTitles);
        $accepted = [];

        foreach ($candidates as $candidate) {
            $title = trim((string) data_get($candidate, 'title', ''));
            $description = trim((string) data_get($candidate, 'description', ''));
            $score = round((float) data_get($candidate, 'score', -1), 2);
            $normalized = $this->normalize($title);
            if ($title === '' || $description === '' || mb_strlen($title) > 255 || mb_strlen($description) > 1000 || $score < $minimum || $score > $maximum) {
                continue;
            }
            if (array_any($pool, function (string $existing) use ($normalized): bool {
                similar_text($normalized, $existing, $similarity);

                return $normalized === $existing || $similarity >= 78;
            })) {
                continue;
            }
            $accepted[] = compact('title', 'description', 'score');
            $pool[] = $normalized;
        }

        return $accepted;
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower(preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value)) ?? '');
    }

    private function providerError(Throwable $error): string
    {
        return $error instanceof RequestException
            ? (string) data_get($error->response?->json(), 'error.message', $error->getMessage())
            : ($error->getMessage() ?: 'Unknown provider error.');
    }

    private function stripCodeFence(string $content): string
    {
        return preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content)) ?? trim($content);
    }
}
