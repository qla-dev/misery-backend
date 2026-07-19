<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Stack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cms_requires_basic_auth(): void
    {
        $this->get('/cms')->assertUnauthorized()->assertHeader('WWW-Authenticate');
    }

    public function test_authenticated_cms_lists_and_updates_cards(): void
    {
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Bad day', 'subtitle' => 'Very bad', 'score' => 10, 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)->get('/cms/cards')
            ->assertOk()
            ->assertSee('Bad day')
            ->assertSee('All packs')
            ->assertSee('All enhancement')
            ->assertSee('Unenhanced')
            ->assertSee('Export')
            ->assertSee('Generate')
            ->assertSee('>Enhance</button>', false)
            ->assertSee('Size')
            ->assertSee('Format')
            ->assertSee('All formats')
            ->assertSee('Translate to Bosnian')
            ->assertSee('Change status')
            ->assertSee('selectVisibleCards', false)
            ->assertSee('tabTop=footerTop+115', false)
            ->assertSee('labelY=(footerTop+tabTop)/2', false)
            ->assertSee('textPaddingY=textGap', false)
            ->assertSee("rgba(250,204,21,.35)", false)
            ->assertSee('CARD_EXPORT_WIDTH=1200', false);
        $this->withServerVariables($server)->get('/cms/native-card-artwork')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->withServerVariables($server)->put('/cms/cards/'.$card->id, [
            'title' => 'Worse day', 'subtitle' => 'Much worse', 'score' => 20.5,
            'stack_id' => $stack->id, 'image' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('cards', ['id' => $card->id, 'title' => 'Worse day', 'deck' => 'normal']);
    }

    public function test_cms_displays_and_sorts_artwork_weight(): void
    {
        Storage::fake('public');
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        Storage::disk('public')->put('cards/small.jpg', str_repeat('a', 50 * 1024));
        Storage::disk('public')->put('cards/large.jpg', str_repeat('b', 120 * 1024));
        Card::create(['title' => 'Small artwork', 'score' => 10, 'image' => 'cards/small.jpg', 'deck' => 'normal', 'stack_id' => $stack->id]);
        Card::create(['title' => 'Large artwork', 'score' => 20, 'image' => 'cards/large.jpg', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)->get('/cms/cards?sort=artwork_weight&direction=desc')
            ->assertOk()
            ->assertSee('120 KB')
            ->assertSee('50 KB')
            ->assertSeeInOrder(['Large artwork', 'Small artwork']);

        $this->withServerVariables($server)->get('/cms/cards?sort=artwork_weight&direction=asc')
            ->assertOk()
            ->assertSeeInOrder(['Small artwork', 'Large artwork']);
    }

    public function test_cards_open_with_normal_pack_and_id_sort_by_default(): void
    {
        $normal = Stack::where('slug', 'normal')->firstOrFail();
        $spicy = Stack::where('slug', 'spicy')->firstOrFail();
        $first = Card::create(['title' => 'First normal card', 'score' => 90, 'deck' => 'normal', 'stack_id' => $normal->id]);
        $second = Card::create(['title' => 'Second normal card', 'score' => 10, 'deck' => 'normal', 'stack_id' => $normal->id]);
        Card::create(['title' => 'Hidden spicy card', 'score' => 5, 'deck' => 'spicy', 'stack_id' => $spicy->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)->get('/cms/cards')
            ->assertOk()
            ->assertSee('value="'.$normal->id.'" selected', false)
            ->assertSee('>ID ↑</a>', false)
            ->assertSeeInOrder(['data-card-id="'.$first->id.'"', 'data-card-id="'.$second->id.'"'], false)
            ->assertDontSee('Hidden spicy card');

        $this->withServerVariables($server)->get('/cms/cards?stack=')
            ->assertOk()
            ->assertSee('Hidden spicy card');
    }

    public function test_deleting_a_card_preserves_the_filtered_list_url(): void
    {
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Delete filtered', 'score' => 10, 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];
        $filteredUrl = route('cms.cards.index', ['format' => 'webp', 'status' => '1', 'sort' => 'card_id', 'direction' => 'desc', 'page' => '2']);

        $this->withServerVariables($server)->withHeader('referer', $filteredUrl)
            ->delete('/cms/cards/'.$card->id)
            ->assertRedirect($filteredUrl);

        $this->assertDatabaseMissing('cards', ['id' => $card->id]);
    }

    public function test_cms_displays_card_id_before_artwork_and_sorts_by_id(): void
    {
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $first = Card::create(['title' => 'Earlier ID', 'score' => 80, 'image' => 'cards/earlier.jpg', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $second = Card::create(['title' => 'Later ID', 'score' => 10, 'image' => 'cards/later.jpg', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)->get('/cms/cards?sort=card_id&direction=desc')
            ->assertOk()
            ->assertSeeInOrder(['>ID ↓</a>', '>Art</th>'], false)
            ->assertSeeInOrder(['data-card-id="'.$second->id.'"', 'data-card-id="'.$first->id.'"'], false);

        $this->withServerVariables($server)->get('/cms/cards?sort=score&direction=asc')
            ->assertOk()
            ->assertSeeInOrder(['data-card-id="'.$second->id.'"', 'data-card-id="'.$first->id.'"'], false)
            ->assertSee('>Score ↑</a>', false);
    }

    public function test_cms_displays_filters_and_sorts_artwork_format_with_translation_status(): void
    {
        Storage::fake('public');
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        Storage::disk('public')->put('cards/example.jpg', 'jpg');
        Storage::disk('public')->put('cards/example.webp', 'webp');
        Card::create(['title' => 'JPEG card', 'subtitle' => 'Copy', 'score' => 10, 'image' => 'cards/example.jpg', 'deck' => 'normal', 'stack_id' => $stack->id]);
        Card::create(['title' => 'WebP card', 'subtitle' => 'Copy', 'title_bs' => 'WebP kartica', 'subtitle_bs' => 'Tekst', 'score' => 20, 'image' => 'cards/example.webp', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)->get('/cms/cards?sort=artwork_format&direction=asc')
            ->assertOk()
            ->assertSee('JPG')
            ->assertSee('WEBP')
            ->assertSee('TRANSLATED')
            ->assertSee('NOT TRANSLATED')
            ->assertSeeInOrder(['JPEG card', 'WebP card']);

        $this->withServerVariables($server)->get('/cms/cards?format=webp')
            ->assertOk()
            ->assertSee('WebP card')
            ->assertDontSee('data-title="JPEG card"', false);
    }

    public function test_cms_filters_cards_by_artwork_enhancement_state(): void
    {
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        Card::create(['title' => 'Enhanced card', 'score' => 10, 'artwork_enhanced' => true, 'deck' => 'normal', 'stack_id' => $stack->id]);
        Card::create(['title' => 'Unenhanced card', 'score' => 20, 'artwork_enhanced' => false, 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)->get('/cms/cards?enhanced=1')
            ->assertOk()
            ->assertSee('Enhanced card')
            ->assertDontSee('Unenhanced card');

        $this->withServerVariables($server)->get('/cms/cards?enhanced=0')
            ->assertOk()
            ->assertSee('Unenhanced card')
            ->assertDontSee('Enhanced card');
    }

    public function test_cms_can_approve_and_return_a_card_to_draft(): void
    {
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Review me', 'score' => 22.22, 'status' => false, 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)->post('/cms/cards/'.$card->id.'/status', ['status' => 1])
            ->assertRedirect()->assertSessionHas('success', 'Card approved.');
        $this->assertTrue($card->fresh()->status);

        $this->withServerVariables($server)->post('/cms/cards/'.$card->id.'/status', ['status' => 0])
            ->assertRedirect()->assertSessionHas('success', 'Card returned to draft.');
        $this->assertFalse($card->fresh()->status);
    }

    public function test_card_editor_back_link_preserves_the_filtered_cards_url(): void
    {
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Filtered card', 'score' => 22.22, 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];
        $filteredUrl = url('/cms/cards').'?stack='.$stack->id.'&status=1&q=Filtered&page=3';
        $editorUrl = route('cms.cards.edit', ['card' => $card, 'return' => $filteredUrl]);

        $this->withServerVariables($server)->get($editorUrl)
            ->assertOk()
            ->assertSee('href="'.e($filteredUrl).'"', false);

        $this->withServerVariables($server)->get(route('cms.cards.edit', ['card' => $card, 'return' => 'https://example.com/cms/cards']))
            ->assertOk()
            ->assertSee('href="'.route('cms.cards.index').'"', false)
            ->assertDontSee('href="https://example.com/cms/cards"', false);
    }

    public function test_cms_can_update_only_a_card_score_inline(): void
    {
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Inline edit', 'subtitle' => 'Keep me', 'score' => 22.22, 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)->patchJson('/cms/cards/'.$card->id.'/score', ['score' => 37.45])
            ->assertOk()
            ->assertJson(['score' => 37.45, 'formatted_score' => '37.45']);

        $card->refresh();
        $this->assertSame(37.45, (float) $card->score);
        $this->assertSame('Inline edit', $card->title);
        $this->assertSame('Keep me', $card->subtitle);

        $this->withServerVariables($server)->patchJson('/cms/cards/'.$card->id.'/score', ['score' => 101])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('score');
    }

    public function test_cms_card_list_exposes_bulk_and_quick_artwork_controls(): void
    {
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        Card::create(['title' => 'Artwork controls', 'score' => 20, 'image' => 'cards/uploads/control.jpg', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)->get('/cms/cards')->assertOk()
            ->assertSee('>Delete</button>', false)
            ->assertSee('Swap artwork')
            ->assertSee('Convert to WebP')
            ->assertSee('artwork-format-download', false)
            ->assertSee('data-change-artwork', false)
            ->assertSee('artworkPicker', false)
            ->assertSee('data-asset-filter="jpg"', false)
            ->assertSee('data-asset-filter="webp"', false)
            ->assertSee('data-asset-filter="other"', false)
            ->assertSee('art-thumb__preview', false);
    }

    public function test_cms_can_assign_swap_and_bulk_delete_cards_without_deleting_artwork(): void
    {
        Storage::fake('public');
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        Storage::disk('public')->put('cards/uploads/one.jpg', 'one');
        Storage::disk('public')->put('cards/uploads/two.jpg', 'two');
        Storage::disk('public')->put('generated/card-89-enhanced-example.webp', 'root-generated');
        $first = Card::create(['title' => 'First art', 'score' => 10, 'image' => 'cards/uploads/one.jpg', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $second = Card::create(['title' => 'Second art', 'score' => 20, 'image' => 'cards/uploads/two.jpg', 'artwork_enhanced' => true, 'deck' => 'normal', 'stack_id' => $stack->id]);
        $third = Card::create(['title' => 'Third art', 'score' => 30, 'image' => 'cards/uploads/one.jpg', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)->get('/cms/cards')->assertOk()
            ->assertSee('data-asset-path="cards/uploads/one.jpg"', false)
            ->assertSee('data-asset-path="cards/uploads/two.jpg"', false)
            ->assertSee('data-asset-path="generated/card-89-enhanced-example.webp"', false)
            ->assertSee('data-asset-format="webp"', false);

        $this->withServerVariables($server)->postJson('/cms/cards/'.$third->id.'/assign-artwork', ['asset_path' => 'cards/uploads/two.jpg'])
            ->assertOk()->assertJsonPath('artwork_enhanced', false);
        $this->assertSame('cards/uploads/two.jpg', $third->fresh()->image);
        Storage::disk('public')->assertExists('cards/uploads/one.jpg');

        $this->withServerVariables($server)->postJson('/cms/cards/swap-artwork', ['ids' => [$first->id, $second->id]])
            ->assertOk()->assertJsonCount(2, 'cards');
        $this->assertSame('cards/uploads/two.jpg', $first->fresh()->image);
        $this->assertSame('cards/uploads/one.jpg', $second->fresh()->image);

        $this->withServerVariables($server)->postJson('/cms/cards/bulk-destroy', ['ids' => [$first->id, $third->id]])
            ->assertOk()->assertJsonPath('deleted', 2);
        $this->assertDatabaseMissing('cards', ['id' => $first->id]);
        $this->assertDatabaseMissing('cards', ['id' => $third->id]);
        Storage::disk('public')->assertExists('cards/uploads/one.jpg');
        Storage::disk('public')->assertExists('cards/uploads/two.jpg');
    }

    public function test_cms_updates_stack_presentation_exposed_to_native_clients(): void
    {
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)->patch('/cms/stacks/'.$stack->id, [
            'name' => 'Normal Plus',
            'color' => '#12ABEF',
            'icon_key' => 'thermometer-sun',
            'description' => 'Updated English description',
            'description_bs' => 'Ažurirani bosanski opis',
        ])->assertRedirect()->assertSessionHas('success', 'Stack updated.');

        $this->getJson('/api/stacks')->assertOk()
            ->assertJsonFragment([
                'slug' => 'normal',
                'name' => 'Normal Plus',
                'color' => '#12ABEF',
                'icon_key' => 'thermometer-sun',
                'description_bs' => 'Ažurirani bosanski opis',
                'is_premium' => false,
            ]);
    }

    public function test_stack_api_counts_only_active_cards(): void
    {
        $stack = Stack::where('slug', 'normal')->firstOrFail();

        Card::create(['title' => 'Active one', 'score' => 10, 'status' => true, 'deck' => 'normal', 'stack_id' => $stack->id]);
        Card::create(['title' => 'Active two', 'score' => 20, 'status' => true, 'deck' => 'normal', 'stack_id' => $stack->id]);
        Card::create(['title' => 'Inactive', 'score' => 30, 'status' => false, 'deck' => 'normal', 'stack_id' => $stack->id]);

        $normalStack = collect($this->getJson('/api/stacks')->assertOk()->json('data'))
            ->firstWhere('slug', 'normal');

        $this->assertSame(2, $normalStack['active_cards_count']);
    }

    public function test_cms_ai_translation_explicitly_requests_standard_bosnian_and_returns_editable_copy(): void
    {
        config([
            'services.gemini.key' => 'gemini-translation-key',
            'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1',
            'services.gemini.text_model' => 'gemini-test-model',
            'services.openrouter.key' => null,
        ]);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'title_bs' => 'Probušena guma usred oluje',
                    'subtitle_bs' => 'Čekaš pomoć dok kiša pojačava.',
                ], JSON_UNESCAPED_UNICODE)]]]]],
            ]),
        ]);
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create([
            'title' => 'Flat tire in a storm',
            'subtitle' => 'You wait for help while the rain gets heavier.',
            'score' => 22.22,
            'deck' => 'normal',
            'stack_id' => $stack->id,
        ]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)->postJson('/cms/cards/'.$card->id.'/translate-bs', [
            'title' => $card->title,
            'subtitle' => $card->subtitle,
        ])->assertOk()
            ->assertJsonPath('title_bs', 'Probušena guma usred oluje')
            ->assertJsonPath('subtitle_bs', 'Čekaš pomoć dok kiša pojačava.')
            ->assertJsonPath('provider', 'Gemini');

        $this->assertNull($card->fresh()->title_bs);

        $this->withServerVariables($server)->postJson('/cms/cards/'.$card->id.'/translate-bs', [
            'title' => $card->title,
            'subtitle' => $card->subtitle,
            'save' => true,
        ])->assertOk()
            ->assertJsonPath('saved', true);

        $this->assertSame('Probušena guma usred oluje', $card->fresh()->title_bs);
        Http::assertSent(function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'];

            return str_contains($prompt, 'specifically Bosnian, not Croatian and not Serbian')
                && str_contains($prompt, 'ijekavian standard')
                && str_contains($prompt, 'č, ć, dž and đ')
                && str_contains($prompt, 'grammar, cases, gender, number, agreement')
                && str_contains($prompt, 'natural, complete event sentence with a finite verb')
                && str_contains($prompt, 'Lavina je blokirala jedinu cestu')
                && str_contains($prompt, 'Vaš pas je uništio svadbenu tortu')
                && str_contains($prompt, 'Protupožarne prskalice su uništile izložbu umjetnina')
                && str_contains($prompt, 'never use the description translation as title_bs')
                && str_contains($prompt, 'affricates č, ć, dž and đ must be written correctly')
                && str_contains($prompt, 'never use passive "od strane" wording');
        });
    }

    public function test_card_model_removes_terminal_periods_from_english_subtitles(): void
    {
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create([
            'title' => 'Terminal punctuation',
            'subtitle' => 'This subtitle ends with periods...',
            'score' => 10,
            'deck' => 'normal',
            'stack_id' => $stack->id,
        ]);

        $this->assertSame('This subtitle ends with periods', $card->subtitle);
        $this->assertDatabaseHas('cards', [
            'id' => $card->id,
            'subtitle' => 'This subtitle ends with periods',
        ]);
    }

    public function test_generate_action_saves_black_background_webp_path_on_card(): void
    {
        Storage::fake('public');
        config([
            'services.openrouter.key' => 'test-key',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.image_model' => 'openai/gpt-image-1',
        ]);
        Http::fake(['openrouter.ai/*' => Http::response([
            'data' => [['b64_json' => $this->testPngBase64()]],
            'usage' => ['cost' => 0.012345],
        ])]);
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Flat tire', 'subtitle' => 'In heavy rain', 'score' => 12, 'image' => '0', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $response = $this->withServerVariables($server)->post('/cms/cards/'.$card->id.'/generate');
        $response
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'Cost: $0.012345.'))
            ->assertSessionHas('crop_generated_artwork', fn (array $crop) => (int) $crop['card_id'] === $card->id && filled($crop['path']));
        $path = $card->fresh()->image;
        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith('.webp', $path);
        $this->assertLessThanOrEqual(100 * 1024, strlen(Storage::disk('public')->get($path)));
        $this->withServerVariables($server)->get('/cms/cards/'.$card->id.'/edit')
            ->assertOk()
            ->assertSee('generatedCropForm');

        Storage::disk('public')->put('cards/uploads/original-before-generation.jpg', 'original');
        $inlineCard = Card::create(['title' => 'Inline generation', 'score' => 14, 'image' => 'cards/uploads/original-before-generation.jpg', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $this->withServerVariables($server)->postJson('/cms/cards/'.$inlineCard->id.'/generate')
            ->assertOk()
            ->assertJsonPath('image', fn (string $image) => str_contains($image, '/card-images/cards/generated/webp/card-'.$inlineCard->id.'-'));
        $this->assertNotSame('0', $inlineCard->fresh()->image);
        Storage::disk('public')->assertExists('cards/uploads/original-before-generation.jpg');
        Http::assertSent(function ($request) {
            $reference = $request['input_references'][0]['image_url']['url'];
            $referencePng = base64_decode(str_replace('data:image/png;base64,', '', $reference), true);

            return $request->url() === 'https://openrouter.ai/api/v1/images'
            && $request['model'] === 'openai/gpt-image-1'
            && $request['quality'] === 'high'
            && $request['background'] === 'opaque'
            && $request['output_format'] === 'webp'
            && count($request['input_references']) === 5
            && str_starts_with($request['input_references'][1]['image_url']['url'], 'data:image/jpeg;base64,')
            && str_starts_with($request['input_references'][2]['image_url']['url'], 'data:image/jpeg;base64,')
            && str_starts_with($request['input_references'][3]['image_url']['url'], 'data:image/jpeg;base64,')
            && str_starts_with($request['input_references'][4]['image_url']['url'], 'data:image/jpeg;base64,')
            && str_starts_with($reference, 'data:image/png;base64,')
            && is_string($referencePng)
            && str_starts_with($referencePng, "\x89PNG\r\n\x1a\n")
            && str_contains($request['prompt'], 'Never return an amber-only image')
            && str_contains($request['prompt'], 'No eyes')
            && str_contains($request['prompt'], 'Main-character mouth exception')
            && str_contains($request['prompt'], 'exactly one tiny, simple black mouth shape')
            && str_contains($request['prompt'], 'every visible person, object, and detail must be a solid filled')
            && str_contains($request['prompt'], 'main human silhouette must ALWAYS be solid pure white #FFFFFF')
            && str_contains($request['prompt'], 'event-specific element that causes or represents the misery must be solid primary amber #FACC15')
            && str_contains($request['prompt'], 'main-silhouette PNG')
            && str_contains($request['prompt'], 'highly creative')
            && str_contains($request['prompt'], 'always depict at least two clearly visible people')
            && str_contains($request['prompt'], 'Never generate a one-person scene')
            && str_contains($request['prompt'], 'obvious main character and primary victim')
            && str_contains($request['prompt'], 'give the second person the most natural role required by the situation')
            && str_contains($request['prompt'], 'do not make the secondary person laugh at, point at, film, tease, celebrate, or mock')
            && str_contains($request['prompt'], 'Ordinary home, travel, health, work, weather, mechanical, and accident situations')
            && str_contains($request['prompt'], 'the second person must contribute to the event rather than merely stand nearby and react')
            && str_contains($request['prompt'], 'when the situation naturally affects a pair or group')
            && str_contains($request['prompt'], 'do not default to the main character holding their head')
            && str_contains($request['prompt'], 'situation-specific full-body acting')
            && str_contains($request['prompt'], 'Grounding rule: nothing may float')
            && str_contains($request['prompt'], 'smooth antialiased curves')
            && str_contains($request['prompt'], 'all four corners and every pixel along all four outer edges')
            && str_contains($request['prompt'], 'Never place the scene inside a rounded rectangle')
            && str_contains($request['prompt'], 'no white corner wedges')
            && str_contains($request['prompt'], 'Absolute frame ban')
            && str_contains($request['prompt'], 'inner border, outer border, white border, amber border')
            && str_contains($request['prompt'], 'White is reserved exclusively for human silhouettes')
            && str_contains($request['prompt'], 'Never create a large white rectangle, block, slab')
            && str_contains($request['prompt'], 'not a collage, infographic, diagram, collection of icons')
            && str_contains($request['prompt'], 'no object of any kind may appear as a free-floating icon')
            && str_contains($request['prompt'], 'Platforms and environmental planes are allowed')
            && str_contains($request['prompt'], 'wide enough view to show every person and object completely')
            && str_contains($request['prompt'], 'Mandatory four-edge coverage')
            && str_contains($request['prompt'], 'within 10 pixels of the top edge, bottom edge, left edge, and right edge')
            && str_contains($request['prompt'], 'especially the bottom edge')
            && str_contains($request['prompt'], 'Coverage without cropping')
            && str_contains($request['prompt'], 'Complete-object construction')
            && str_contains($request['prompt'], 'Show both ends and all structurally necessary parts')
            && str_contains($request['prompt'], 'dioramas, floor islands, platforms, trays, cutaway rooms')
            && str_contains($request['prompt'], 'show their complete intentional shape and all visible boundaries')
            && str_contains($request['prompt'], 'Absolute crop ban')
            && str_contains($request['prompt'], 'scale the entire composition down until everything is fully visible')
            && str_contains($request['prompt'], 'Only the uniform black background may occupy the corners and the full outer boundary')
            && str_contains($request['prompt'], 'Three-part composition recipe')
            && str_contains($request['prompt'], 'use the attached good-example image only for staging, visual hierarchy')
            && str_contains($request['prompt'], 'do not reuse its exact camera angle or object orientation')
            && str_contains($request['prompt'], 'left and right three-quarter views')
            && str_contains($request['prompt'], 'ignore and omit its timer display, digits')
            && str_contains($request['prompt'], 'no words, letters, numbers, digits, decimal points, scores')
            && str_contains($request['prompt'], 'never depict a finished game card, card shell')
            && str_contains($request['prompt'], 'exactly these three flat colors')
            && str_contains($request['prompt'], 'Absolute gradient ban')
            && str_contains($request['prompt'], 'Flat solid fills only')
            && str_contains($request['prompt'], 'pure black #000000')
            && str_contains($request['prompt'], 'no pixelation')
            && str_contains($request['prompt'], 'Production sharpness pass')
            && str_contains($request['prompt'], 'below 100 KB');
        });
    }

    public function test_generate_action_falls_back_to_direct_gemini_when_openrouter_has_insufficient_credit(): void
    {
        Storage::fake('public');
        config([
            'services.openrouter.key' => 'openrouter-test-key',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.gemini_fallback.key' => 'gemini-test-key',
            'services.gemini_fallback.base_url' => 'https://generativelanguage.googleapis.com/v1',
            'services.gemini_fallback.image_model' => 'gemini-3.1-flash-image',
        ]);
        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => ['message' => 'Insufficient credits']], 402),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'inlineData' => [
                                'mimeType' => 'image/png',
                                'data' => $this->testPngBase64(),
                            ],
                        ]],
                    ],
                ]],
            ]),
        ]);
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Flat tire', 'subtitle' => 'In heavy rain', 'score' => 12, 'image' => '0', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)
            ->post('/cms/cards/'.$card->id.'/generate')
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'direct Gemini fallback'));

        Storage::disk('public')->assertExists($card->fresh()->image);
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'generativelanguage.googleapis.com')) {
                return false;
            }

            return $request->url() === 'https://generativelanguage.googleapis.com/v1/models/gemini-3.1-flash-image:generateContent'
                && $request->hasHeader('x-goog-api-key', 'gemini-test-key')
                && $request['generationConfig']['responseModalities'] === ['IMAGE']
                && $request['generationConfig']['imageConfig']['aspectRatio'] === '1:1'
                && $request['generationConfig']['imageConfig']['imageSize'] === '2K'
                && $request['contents'][0]['parts'][2]['inlineData']['mimeType'] === 'image/png'
                && $request['contents'][0]['parts'][4]['inlineData']['mimeType'] === 'image/jpeg'
                && $request['contents'][0]['parts'][6]['inlineData']['mimeType'] === 'image/jpeg'
                && $request['contents'][0]['parts'][7]['inlineData']['mimeType'] === 'image/jpeg'
                && $request['contents'][0]['parts'][8]['inlineData']['mimeType'] === 'image/jpeg';
        });
    }

    public function test_svg_generation_saves_sanitized_cms_only_artwork(): void
    {
        Storage::fake('public');
        config([
            'services.gemini.key' => 'gemini-text-key',
            'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1',
            'services.gemini.text_model' => 'gemini-3.1-flash-lite',
        ]);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024"><circle cx="300" cy="300" r="120" fill="white"/><path fill="#facc15" d="M500 100h180l-80 250h100L420 700l80-260H390z"/><rect x="120" y="720" width="780" height="60" fill="#525252"/></svg>';
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => "```svg\n{$svg}\n```"]]]]],
            ]),
        ]);
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Lose your keys', 'subtitle' => 'Locked outside', 'score' => 18, 'image' => '0', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)
            ->post('/cms/cards/'.$card->id.'/generate-svg')
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'SVG illustration generated'));

        $path = $card->fresh()->svg_img;
        $this->assertStringStartsWith('cards/generated-svg/card-'.$card->id.'-', $path);
        Storage::disk('public')->assertExists($path);
        $storedSvg = Storage::disk('public')->get($path);
        $this->assertStringContainsString('fill="#FFFFFF"', $storedSvg);
        $this->assertStringContainsString('fill="#FACC15"', $storedSvg);
        $this->assertStringNotContainsString('<script', $storedSvg);
        $this->get('/card-images/'.$path)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox")
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        Http::assertSent(fn ($request) => $request->url() === 'https://generativelanguage.googleapis.com/v1/models/gemini-3.1-flash-lite:generateContent'
            && $request->hasHeader('x-goog-api-key', 'gemini-text-key')
            && ! isset($request['systemInstruction'])
            && ! isset($request['generationConfig'])
            && str_contains(data_get($request, 'contents.0.parts.0.text'), 'Lose your keys')
            && str_contains(data_get($request, 'contents.0.parts.0.text'), '<svg'));
    }

    public function test_svg_generation_rejects_executable_svg(): void
    {
        Storage::fake('public');
        config([
            'services.gemini.key' => 'gemini-text-key',
            'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1',
            'services.gemini.text_model' => 'gemini-3.1-flash-lite',
        ]);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><circle cx="50" cy="50" r="20" fill="#FFFFFF"/><path fill="#FACC15" d="M0 0h10v10z"/></svg>']]]]],
            ]),
        ]);
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Unsafe SVG test', 'score' => 18, 'image' => '0', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)
            ->from('/cms/cards/'.$card->id.'/edit')
            ->post('/cms/cards/'.$card->id.'/generate-svg')
            ->assertRedirect('/cms/cards/'.$card->id.'/edit')
            ->assertSessionHasErrors('generation');

        $this->assertNull($card->fresh()->svg_img);
        $this->assertSame([], Storage::disk('public')->allFiles('cards/generated-svg'));
    }

    public function test_generate_action_returns_to_cms_with_a_configuration_error(): void
    {
        config(['services.openrouter.key' => null]);
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Flat tire', 'score' => 12, 'image' => '0', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)
            ->from('/cms/cards/'.$card->id.'/edit')
            ->post('/cms/cards/'.$card->id.'/generate')
            ->assertRedirect('/cms/cards/'.$card->id.'/edit')
            ->assertSessionHasErrors('generation');
    }

    public function test_generated_artwork_crop_replaces_the_original_with_a_768_webp(): void
    {
        Storage::fake('public');
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $originalPath = 'cards/generated/card-original.jpg';
        Storage::disk('public')->put($originalPath, 'old-image');
        $card = Card::create([
            'title' => 'Flat tire',
            'score' => 12,
            'image' => $originalPath,
            'deck' => 'normal',
            'stack_id' => $stack->id,
        ]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $image = imagecreatetruecolor(256, 256);
        imagefill($image, 0, 0, imagecolorallocate($image, 250, 204, 21));
        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = ob_get_clean();
        imagedestroy($image);

        $this->withServerVariables($server)
            ->post('/cms/cards/'.$card->id.'/crop-generated', [
                'crop_data' => 'data:image/jpeg;base64,'.base64_encode($jpeg),
                'generation_id' => '550e8400-e29b-41d4-a716-446655440000',
            ])
            ->assertRedirect(route('cms.cards.edit', $card))
            ->assertSessionHas('success', '768 × 768 WebP artwork crop saved.');

        $croppedPath = $card->fresh()->image;
        $this->assertNotSame($originalPath, $croppedPath);
        Storage::disk('public')->assertMissing($originalPath);
        Storage::disk('public')->assertExists($croppedPath);
        $this->assertLessThanOrEqual(100 * 1024, strlen(Storage::disk('public')->get($croppedPath)));
        $saved = imagecreatefromstring(Storage::disk('public')->get($croppedPath));
        $this->assertNotFalse($saved);
        $this->assertSame(768, imagesx($saved));
        $this->assertSame(768, imagesy($saved));
        $this->assertStringEndsWith('.webp', $croppedPath);
        imagedestroy($saved);

        $this->withServerVariables($server)->postJson('/cms/cards/'.$card->id.'/crop-generated', [
            'crop_data' => 'data:image/jpeg;base64,'.base64_encode($jpeg),
        ])->assertOk()
            ->assertJsonPath('message', '768 × 768 WebP artwork crop saved.')
            ->assertJsonPath('image', fn (string $image) => str_contains($image, '/card-images/cards/generated/webp/card-'.$card->id.'-cropped-'));
    }

    public function test_cms_can_convert_existing_artwork_to_768_webp(): void
    {
        Storage::fake('public');
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $originalPath = 'cards/uploads/large.jpg';
        $image = imagecreatetruecolor(1024, 1024);
        imagefill($image, 0, 0, imagecolorallocate($image, 250, 204, 21));
        ob_start();
        imagejpeg($image, null, 95);
        Storage::disk('public')->put($originalPath, ob_get_clean());
        imagedestroy($image);
        $card = Card::create(['title' => 'Convert me', 'score' => 25, 'image' => $originalPath, 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)->postJson('/cms/cards/'.$card->id.'/convert-webp')
            ->assertOk()
            ->assertJsonPath('dimensions', '768x768');

        $path = $card->fresh()->image;
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($originalPath);
        Storage::disk('public')->assertExists($path);
        $bytes = Storage::disk('public')->get($path);
        $this->assertLessThanOrEqual(100 * 1024, strlen($bytes));
        $saved = imagecreatefromstring($bytes);
        $this->assertSame(768, imagesx($saved));
        $this->assertSame(768, imagesy($saved));
        imagedestroy($saved);
        $this->get('/card-images/'.$path)->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_current_artwork_can_be_enhanced_and_kept_below_one_hundred_kilobytes(): void
    {
        Storage::fake('public');
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $originalPath = 'cards/generated/card-before-enhancement.jpg';
        $image = imagecreatetruecolor(256, 256);
        $black = imagecolorallocate($image, 0, 0, 0);
        $yellow = imagecolorallocate($image, 250, 204, 21);
        imagefill($image, 0, 0, $black);
        imagesetthickness($image, 16);
        imageline($image, 20, 236, 236, 20, $yellow);
        ob_start();
        imagejpeg($image, null, 60);
        $original = ob_get_clean();
        imagedestroy($image);
        Storage::disk('public')->put($originalPath, $original);
        config([
            'services.openrouter.key' => 'enhancement-test-key',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.image_model' => 'google/gemini-image-test',
            'services.gemini_fallback.key' => null,
        ]);
        Http::fake(['openrouter.ai/*' => Http::response([
            'data' => [[
                'media_type' => 'image/jpeg',
                'b64_json' => base64_encode($original),
            ]],
        ])]);

        $card = Card::create([
            'title' => 'Flat tire',
            'score' => 12,
            'image' => $originalPath,
            'deck' => 'normal',
            'stack_id' => $stack->id,
        ]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)
            ->postJson('/cms/cards/'.$card->id.'/enhance-artwork')
            ->assertOk()
            ->assertJsonPath('message', 'Artwork enhanced with Gemini via OpenRouter using google/gemini-image-test, then saved as 1024 × 1024 WebP below 100 KB.')
            ->assertJsonPath('extension', 'WEBP')
            ->assertJsonPath('image', fn (string $image) => str_contains($image, '/card-images/cards/generated/webp/card-'.$card->id.'-enhanced-'));

        $enhancedPath = $card->fresh()->image;
        $this->assertTrue($card->fresh()->artwork_enhanced);
        $this->assertStringContainsString('-enhanced-', $enhancedPath);
        $this->assertStringEndsWith('.webp', $enhancedPath);
        Storage::disk('public')->assertMissing($originalPath);
        Storage::disk('public')->assertExists($enhancedPath);
        $enhancedBytes = Storage::disk('public')->get($enhancedPath);
        $this->assertLessThanOrEqual(100 * 1024, strlen($enhancedBytes));
        $enhanced = imagecreatefromstring($enhancedBytes);
        $this->assertNotFalse($enhanced);
        $this->assertSame(1024, imagesx($enhanced));
        $this->assertSame(1024, imagesy($enhanced));
        $center = imagecolorat($enhanced, 512, 512);
        $this->assertEqualsWithDelta(250, ($center >> 16) & 0xFF, 15);
        $this->assertEqualsWithDelta(204, ($center >> 8) & 0xFF, 15);
        $this->assertEqualsWithDelta(21, $center & 0xFF, 15);
        imagedestroy($enhanced);
        $this->get('/card-images/'.$enhancedPath)->assertOk()->assertHeader('Content-Type', 'image/webp');
        Http::assertSent(fn ($request) => $request->url() === 'https://openrouter.ai/api/v1/images'
            && $request['model'] === 'google/gemini-image-test'
            && $request['aspect_ratio'] === '1:1'
            && str_contains($request['prompt'], 'image restoration, not a redesign')
            && str_contains($request['prompt'], 'DO NOT preserve the source crop or framing')
            && str_contains($request['prompt'], 'AUTO-ZOOM AND REFRAME')
            && str_contains($request['prompt'], 'identify the tight bounding box of every real non-black pixel')
            && str_contains($request['prompt'], 'EDGE ACCEPTANCE CHECK')
            && str_contains($request['prompt'], 'topmost real colored or white pixel must begin at the top content boundary')
            && str_contains($request['prompt'], 'Sharpness improvement alone is not a successful enhancement')
            && str_contains($request['prompt'], 'canonical Misery yellow #FACC15')
            && str_contains($request['prompt'], 'correct every yellow area back to the uniform solid color #FACC15')
            && str_starts_with($request['input_references'][0]['image_url']['url'], 'data:image/jpeg;base64,'));

        $this->withServerVariables($server)->get('/cms/cards')
            ->assertOk()
            ->assertSee('ENHANCED')
            ->assertSee('Enhance');
    }

    public function test_generate_action_returns_to_cms_when_provider_returns_no_image(): void
    {
        config(['services.openrouter.key' => 'test-key']);
        Http::fake(['openrouter.ai/*' => Http::response(['data' => []])]);
        $stack = Stack::where('slug', 'normal')->firstOrFail();
        $card = Card::create(['title' => 'Flat tire', 'score' => 12, 'image' => '0', 'deck' => 'normal', 'stack_id' => $stack->id]);
        $server = ['PHP_AUTH_USER' => config('cms.username'), 'PHP_AUTH_PW' => config('cms.password')];

        $this->withServerVariables($server)
            ->from('/cms/cards/'.$card->id.'/edit')
            ->post('/cms/cards/'.$card->id.'/generate')
            ->assertRedirect('/cms/cards/'.$card->id.'/edit')
            ->assertSessionHasErrors('generation');
    }

    public function test_generated_card_images_are_served_without_public_storage_symlink(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cards/generated/example.png', 'png-bytes');

        $this->get('/card-images/cards/generated/example.png')
            ->assertOk()
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    }

    private function testPngBase64(): string
    {
        $image = imagecreatetruecolor(2, 2);
        imagefill($image, 0, 0, imagecolorallocate($image, 250, 204, 21));
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return base64_encode($png);
    }
}
