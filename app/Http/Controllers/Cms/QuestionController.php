<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Throwable;

class QuestionController extends Controller
{
    private const GENERATION_COUNT = 10;

    public function index(Request $request)
    {
        $questions = Question::query()
            ->when($request->string('q')->toString(), function ($query, $search) {
                $query->where(fn ($inner) => $inner
                    ->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%"));
            })
            ->when($request->string('category')->toString(), fn ($query, $category) => $query->where('category', $category))
            ->when($request->filled('difficulty'), fn ($query) => $query->where('difficulty', $request->integer('difficulty')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->boolean('status')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('cms.questions.index', $this->viewData(compact('questions')));
    }

    public function create()
    {
        return view('cms.questions.form', $this->viewData(['question' => new Question]));
    }

    public function store(Request $request)
    {
        $question = Question::create($this->validated($request));

        return redirect()->route('cms.questions.edit', $question)->with('success', 'Question created.');
    }

    public function edit(Question $question)
    {
        return view('cms.questions.form', $this->viewData(compact('question')));
    }

    public function update(Request $request, Question $question)
    {
        $question->update($this->validated($request));

        return back()->with('success', 'Question saved.');
    }

    public function destroy(Question $question)
    {
        $question->delete();

        return redirect()->route('cms.questions.index')->with('success', 'Question deleted.');
    }

    public function generateForm()
    {
        return view('cms.questions.generate', $this->viewData());
    }

    public function generate(Request $request)
    {
        $selection = $request->validate([
            'category' => ['required', Rule::in(array_keys(Question::CATEGORIES))],
            'difficulty' => ['required', 'integer', Rule::in(array_keys(Question::DIFFICULTIES))],
        ]);

        if (! config('services.openrouter.key')) {
            return back()->withInput()->withErrors(['generation' => 'OPENROUTER_API_KEY is not configured on the backend.']);
        }

        $allExisting = Question::query()->pluck('question')->all();
        $promptExamples = array_slice(array_reverse($allExisting), 0, 300);

        try {
            $response = Http::withToken(config('services.openrouter.key'))
                ->withHeaders(array_filter([
                    'HTTP-Referer' => config('services.openrouter.http_referer'),
                    'X-Title' => config('services.openrouter.title'),
                ]))
                ->acceptJson()
                ->asJson()
                ->timeout(120)
                ->post(rtrim(config('services.openrouter.base_url'), '/').'/chat/completions', [
                    'model' => config('services.openrouter.text_model'),
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $this->generationPrompt($selection, $promptExamples)],
                    ],
                    'temperature' => 0.9,
                    'response_format' => $this->responseFormat(),
                ])->throw()->json();
        } catch (RequestException $error) {
            return back()->withInput()->withErrors([
                'generation' => data_get($error->response?->json(), 'error.message', $error->getMessage()),
            ]);
        }

        $content = data_get($response, 'choices.0.message.content');
        if (! is_string($content)) {
            return back()->withInput()->withErrors(['generation' => 'The AI provider returned no question data.']);
        }

        try {
            $decoded = json_decode($this->stripCodeFence($content), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return back()->withInput()->withErrors(['generation' => 'The AI provider returned invalid JSON. Please try again.']);
        }

        $accepted = $this->uniqueCandidates(data_get($decoded, 'questions', []), $allExisting);
        if (count($accepted) < self::GENERATION_COUNT) {
            return back()->withInput()->withErrors([
                'generation' => 'The AI did not produce 10 sufficiently unique questions. Nothing was saved; please try again.',
            ]);
        }

        try {
            DB::transaction(function () use ($accepted, $selection) {
                foreach (array_slice($accepted, 0, self::GENERATION_COUNT) as $candidate) {
                    Question::create([
                        'question' => $candidate['question'],
                        'answer' => $candidate['answer'],
                        'category' => $selection['category'],
                        'difficulty' => $selection['difficulty'],
                        'status' => false,
                        'generated_by_ai' => true,
                    ]);
                }
            });
        } catch (Throwable) {
            return back()->withInput()->withErrors([
                'generation' => 'A duplicate was detected while saving. Nothing was saved; please generate again.',
            ]);
        }

        return redirect()->route('cms.questions.index')->with('success', '10 unique AI questions generated and saved as drafts.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'answer' => ['required', 'string', 'max:1000'],
            'category' => ['required', Rule::in(array_keys(Question::CATEGORIES))],
            'difficulty' => ['required', 'integer', Rule::in(array_keys(Question::DIFFICULTIES))],
            'status' => ['nullable', 'boolean'],
        ]);
        $data['status'] = $request->boolean('status');

        return $data;
    }

    private function viewData(array $data = []): array
    {
        return $data + ['categories' => Question::CATEGORIES, 'difficulties' => Question::DIFFICULTIES];
    }

    private function systemPrompt(): string
    {
        return implode(' ', [
            'You are an expert trivia editor for a multiplayer party game.',
            'Create concise, evergreen, factually accurate questions with one unambiguous short answer.',
            'Never create opinion questions, trick questions, time-sensitive questions, or alternate wording of an excluded question.',
            'Return only data matching the supplied JSON schema.',
        ]);
    }

    private function generationPrompt(array $selection, array $existing): string
    {
        $difficultyGuidance = [
            1 => 'widely known facts suitable for casual players',
            2 => 'recognizable facts requiring some subject knowledge',
            3 => 'challenging facts for knowledgeable players',
            4 => 'expert-level but fair and verifiable facts',
        ];
        $excluded = $existing === []
            ? '(There are no existing questions yet.)'
            : implode("\n", array_map(fn ($question) => '- '.$question, $existing));

        return implode("\n", [
            'Generate 18 candidate trivia questions so the application can retain the best 10.',
            'Category: '.Question::CATEGORIES[$selection['category']],
            'Difficulty range: '.Question::DIFFICULTIES[$selection['difficulty']].' — '.$difficultyGuidance[$selection['difficulty']],
            'Each question must test a different fact. Candidates must not duplicate, paraphrase, or ask the same underlying fact as another candidate or any excluded question.',
            'Use natural English. Keep questions and answers concise. Do not include category or difficulty in the question text.',
            '',
            'Existing questions to exclude:',
            $excluded,
        ]);
    }

    private function responseFormat(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'question_batch',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'questions' => [
                            'type' => 'array',
                            'minItems' => self::GENERATION_COUNT,
                            'maxItems' => 20,
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'question' => ['type' => 'string'],
                                    'answer' => ['type' => 'string'],
                                ],
                                'required' => ['question', 'answer'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'required' => ['questions'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function uniqueCandidates(mixed $candidates, array $existing): array
    {
        if (! is_array($candidates)) {
            return [];
        }

        $comparisonPool = array_values(array_filter(array_map(
            fn ($question) => Question::normalize((string) $question),
            $existing
        )));
        $accepted = [];

        foreach ($candidates as $candidate) {
            $question = trim((string) data_get($candidate, 'question', ''));
            $answer = trim((string) data_get($candidate, 'answer', ''));
            $normalized = Question::normalize($question);

            if ($question === '' || $answer === '' || mb_strlen($question) > 1000 || mb_strlen($answer) > 1000) {
                continue;
            }
            if ($this->isSimilarToAny($normalized, $comparisonPool)) {
                continue;
            }

            $accepted[] = compact('question', 'answer');
            $comparisonPool[] = $normalized;
        }

        return $accepted;
    }

    private function isSimilarToAny(string $candidate, array $questions): bool
    {
        foreach ($questions as $existing) {
            if ($candidate === $existing) {
                return true;
            }

            similar_text($candidate, $existing, $similarity);
            if ($similarity >= 78 || $this->tokenSimilarity($candidate, $existing) >= 0.68) {
                return true;
            }
        }

        return false;
    }

    private function tokenSimilarity(string $left, string $right): float
    {
        $leftTokens = array_unique(array_filter(explode(' ', $left), fn ($word) => mb_strlen($word) > 2));
        $rightTokens = array_unique(array_filter(explode(' ', $right), fn ($word) => mb_strlen($word) > 2));
        $union = array_unique(array_merge($leftTokens, $rightTokens));

        return $union === [] ? 0 : count(array_intersect($leftTokens, $rightTokens)) / count($union);
    }

    private function stripCodeFence(string $content): string
    {
        return preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content)) ?? trim($content);
    }
}
