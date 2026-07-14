<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Question;
use App\Models\Stack;
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

    public function test_cms_navigation_replaces_questions_with_card_generator(): void
    {
        $this->withServerVariables($this->server)->get('/cms/generator')
            ->assertOk()
            ->assertSee('Generate card content')
            ->assertSee('suggested two-decimal misery scores')
            ->assertDontSee('Generate questions');

        $this->withServerVariables($this->server)->get('/cms')
            ->assertOk()
            ->assertSee('Generator')
            ->assertDontSee('>Questions<', false);
    }

    public function test_questions_api_remains_available_for_legacy_clients(): void
    {
        Question::create([
            'question' => 'Visible sports question?', 'answer' => 'Visible',
            'category' => 'sports', 'difficulty' => 2, 'status' => true,
        ]);

        $this->getJson('/api/questions?category=sports&difficulty=2')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.question', 'Visible sports question?');
    }

    public function test_ai_generator_saves_exactly_ten_unique_cards_with_scores(): void
    {
        $this->existingCard('Existing disaster');
        config([
            'services.gemini.key' => 'gemini-test-key',
            'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1',
            'services.gemini.text_model' => 'gemini-3.1-flash-lite',
        ]);

        $cards = [['title' => 'Existing disaster', 'description' => 'Duplicate.', 'score' => 44.44]];
        $titles = [
            'Airport Shuttle Leaves With Your Luggage',
            'Sprinkler Activates During the Wedding Toast',
            'Office Projector Shows Your Private Photos',
            'Balcony Door Locks You Outside Overnight',
            'Moving Van Delivers Everything to Another City',
            'Restaurant Chair Collapses During the Proposal',
            'Hotel Shower Floods the Room Below',
            'Drone Drops the Anniversary Gift Into Traffic',
            'Elevator Opens Directly Into a Costume Party',
            'Dog Buries Your Rental Car Keys',
            'Power Cut Traps the Birthday Cake Upstairs',
        ];
        foreach ($titles as $index => $title) {
            $cards[] = [
                'title' => $title,
                'description' => "A specific unfortunate event number {$index} unfolds in public.",
                'score' => 26 + $index + 0.37,
            ];
        }
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['cards' => $cards])]]]]],
        ])]);

        $before = Card::count();
        $this->withServerVariables($this->server)->post('/cms/generator', [
            'theme' => 'mixed', 'severity' => 'mixed',
        ])->assertRedirect(route('cms.cards.index'))->assertSessionHas('success');

        $this->assertSame($before + 10, Card::count());
        $this->assertSame(10, Card::whereIn('title', $titles)->count());
        $this->assertSame(10, Card::whereIn('title', $titles)->where('image', '0')->count());
        $this->assertSame(10, Card::whereIn('title', $titles)->where('status', false)->count());

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://generativelanguage.googleapis.com/v1/models/gemini-3.1-flash-lite:generateContent'
            && $request->hasHeader('x-goog-api-key', 'gemini-test-key')
            && str_contains(data_get($request, 'contents.0.parts.0.text'), 'Existing disaster')
            && str_contains(data_get($request, 'contents.0.parts.0.text'), 'misery score')
            && str_contains(data_get($request, 'contents.0.parts.0.text'), 'description'));
    }

    public function test_invalid_or_duplicate_generation_batch_saves_nothing(): void
    {
        config(['services.openrouter.key' => 'test-key', 'services.gemini.key' => null]);
        $existing = $this->existingCard('Existing duplicate disaster');
        Http::fake(['openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['cards' => array_fill(0, 10, [
                'title' => $existing->title, 'description' => $existing->subtitle, 'score' => 50.55,
            ])])]]],
        ])]);

        $before = Card::count();
        $this->withServerVariables($this->server)->from('/cms/generator')->post('/cms/generator', [
            'theme' => 'mixed', 'severity' => 'mixed',
        ])->assertRedirect('/cms/generator')->assertSessionHasErrors('generation');
        $this->assertSame($before, Card::count());
    }

    private function existingCard(string $title): Card
    {
        $stack = Stack::where('slug', 'normal')->firstOrFail();

        return Card::create([
            'title' => $title,
            'subtitle' => 'An existing card description.',
            'score' => 44.44,
            'image' => '0',
            'deck' => $stack->slug,
            'stack_id' => $stack->id,
        ]);
    }
}
