<?php

namespace Tests\Feature;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuestionCmsTest extends TestCase
{
    use RefreshDatabase;

    private array $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];
    }

    public function test_cms_can_create_and_activate_a_question(): void
    {
        $response = $this->withServerVariables($this->server)->post('/cms/questions', [
            'question' => 'Which planet is known as the Red Planet?',
            'answer' => 'Mars',
            'category' => 'science',
            'difficulty' => 1,
            'status' => 1,
        ]);

        $question = Question::firstOrFail();
        $response->assertRedirect(route('cms.questions.edit', $question));
        $this->assertTrue($question->status);
        $this->assertSame(hash('sha256', 'which planet is known as the red planet'), $question->normalized_hash);
    }

    public function test_questions_api_returns_only_active_matching_questions(): void
    {
        $this->question('Visible sports question?', 'Visible', 'sports', 2, true);
        $this->question('Draft sports question?', 'Draft', 'sports', 2, false);
        $this->question('Visible movie question?', 'Movie', 'movies', 2, true);

        $this->getJson('/api/questions?category=sports&difficulty=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.question', 'Visible sports question?')
            ->assertJsonMissing(['status' => false])
            ->assertJsonMissing(['status' => true]);
    }

    public function test_ai_generation_rejects_existing_question_and_saves_exactly_ten_drafts(): void
    {
        $this->question('Who directed Jaws?', 'Steven Spielberg', 'movies', 2, true);
        config([
            'services.gemini.key' => 'gemini-test-key',
            'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1',
            'services.gemini.text_model' => 'gemini-3.1-flash-lite',
        ]);

        $candidates = [
            ['question' => 'Who directed Jaws?', 'answer' => 'Steven Spielberg'],
            ['question' => 'Which film won the first Academy Award for Best Picture?', 'answer' => 'Wings'],
            ['question' => 'What is the fictional African nation in Black Panther?', 'answer' => 'Wakanda'],
            ['question' => 'Who played the title role in the 1976 film Rocky?', 'answer' => 'Sylvester Stallone'],
            ['question' => 'Which 1999 film features the character Neo?', 'answer' => 'The Matrix'],
            ['question' => 'What animated film features a clownfish named Nemo?', 'answer' => 'Finding Nemo'],
            ['question' => 'Which director created Spirited Away?', 'answer' => 'Hayao Miyazaki'],
            ['question' => 'What is the name of Han Solo ship?', 'answer' => 'Millennium Falcon'],
            ['question' => 'Which film series features Katniss Everdeen?', 'answer' => 'The Hunger Games'],
            ['question' => 'What 1993 dinosaur film was directed by Steven Spielberg?', 'answer' => 'Jurassic Park'],
            ['question' => 'Which actor portrayed Jack in Titanic?', 'answer' => 'Leonardo DiCaprio'],
            ['question' => 'What is the subtitle of the second Lord of the Rings film?', 'answer' => 'The Two Towers'],
        ];
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [[
                    'text' => json_encode(['questions' => $candidates]),
                ]]]]],
            ]),
        ]);

        $this->withServerVariables($this->server)->post('/cms/questions/generate-ai', [
            'category' => 'movies',
            'difficulty' => 2,
        ])->assertRedirect(route('cms.questions.index'))
            ->assertSessionHas('success');

        $this->assertSame(11, Question::count());
        $this->assertSame(10, Question::where('generated_by_ai', true)->where('status', false)->count());
        $this->assertSame(10, Question::where('generated_by_ai', true)->where('category', 'movies')->where('difficulty', 2)->count());
        $this->assertSame(1, Question::where('question', 'Who directed Jaws?')->count());

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://generativelanguage.googleapis.com/v1/models/gemini-3.1-flash-lite:generateContent'
            && $request->hasHeader('x-goog-api-key', 'gemini-test-key')
            && ! isset($request['systemInstruction'])
            && ! isset($request['generationConfig'])
            && str_contains(data_get($request, 'contents.0.parts.0.text'), 'Who directed Jaws?')
            && str_contains(data_get($request, 'contents.0.parts.0.text'), 'Movies')
            && str_contains(data_get($request, 'contents.0.parts.0.text'), 'Medium')
            && str_contains(data_get($request, 'contents.0.parts.0.text'), 'JSON Schema'));
    }

    public function test_failed_unique_batch_saves_nothing(): void
    {
        $this->question('What is Earth satellite?', 'The Moon', 'science', 1, true);
        config([
            'services.openrouter.key' => 'test-key',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.text_model' => 'test-model',
        ]);
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['questions' => array_fill(0, 10, [
                    'question' => 'What is Earth satellite?',
                    'answer' => 'The Moon',
                ])])]]],
            ]),
        ]);

        $this->withServerVariables($this->server)->from('/cms/questions/generate-ai')->post('/cms/questions/generate-ai', [
            'category' => 'science',
            'difficulty' => 1,
        ])->assertRedirect('/cms/questions/generate-ai')->assertSessionHasErrors('generation');

        $this->assertSame(1, Question::count());
    }

    private function question(string $question, string $answer, string $category, int $difficulty, bool $status): Question
    {
        return Question::create(compact('question', 'answer', 'category', 'difficulty', 'status'));
    }
}
