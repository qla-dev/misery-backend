<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Stack;
use App\Services\GeminiImageGenerator;
use DOMDocument;
use DOMElement;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CardController extends Controller
{
    private const MAX_GENERATED_JPEG_BYTES = 102400;

    public function index(Request $request)
    {
        $cardQuery = Card::with('stack')->when($request->string('q')->toString(), function ($query, $search) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('subtitle', 'like', "%{$search}%"));
        })->when($request->filled('status'), fn ($query) => $query->where('status', $request->boolean('status')))
            ->when($request->filled('enhanced'), fn ($query) => $query->where('artwork_enhanced', $request->boolean('enhanced')))
            ->when($request->filled('stack'), fn ($query) => $query->where('stack_id', $request->integer('stack')))
            ->when($request->string('format')->toString() === 'webp', fn ($query) => $query->whereRaw('LOWER(image) LIKE ?', ['%.webp']))
            ->when($request->string('format')->toString() === 'jpg', fn ($query) => $query->where(function ($query) {
                $query->whereRaw('LOWER(image) LIKE ?', ['%.jpg'])->orWhereRaw('LOWER(image) LIKE ?', ['%.jpeg']);
            }));

        $sort = $request->string('sort')->toString();
        if (in_array($sort, ['artwork_weight', 'artwork_format'], true)) {
            $direction = $request->string('direction')->lower()->toString() === 'desc' ? 'desc' : 'asc';
            $allCards = $cardQuery->get()->each(function (Card $card) {
                $this->addArtworkMetadata($card);
            })->sortBy(
                fn (Card $card) => $sort === 'artwork_format' ? $card->artwork_extension : ($card->artwork_bytes ?? -1),
                $sort === 'artwork_format' ? SORT_NATURAL : SORT_NUMERIC,
                $direction === 'desc'
            )->values();
            $perPage = 25;
            $page = max(1, LengthAwarePaginator::resolveCurrentPage());
            $cards = new LengthAwarePaginator(
                $allCards->forPage($page, $perPage)->values(),
                $allCards->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } elseif (in_array($sort, ['card_id', 'score'], true)) {
            $direction = $request->string('direction')->lower()->toString() === 'desc' ? 'desc' : 'asc';
            $cards = $cardQuery->orderBy($sort === 'card_id' ? 'id' : 'score', $direction)->paginate(25)->withQueryString();
            $cards->getCollection()->each(function (Card $card) {
                $this->addArtworkMetadata($card);
            });
        } else {
            $cards = $cardQuery->orderBy('score')->paginate(25)->withQueryString();
            $cards->getCollection()->each(function (Card $card) {
                $this->addArtworkMetadata($card);
            });
        }
        $stacks = Stack::query()->orderBy('name')->get();
        $assets = collect([
            ...Storage::disk('public')->allFiles('cards'),
            ...Storage::disk('public')->allFiles('generated'),
        ])
            ->unique()
            ->filter(fn (string $path) => in_array(Str::lower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'webp', 'png', 'gif', 'avif'], true))
            ->map(function (string $path): array {
                $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

                return [
                    'path' => $path,
                    'url' => url('/card-images/'.$path),
                    'folder' => dirname($path),
                    'filename' => basename($path),
                    'format' => in_array($extension, ['jpg', 'jpeg'], true) ? 'jpg' : ($extension === 'webp' ? 'webp' : 'other'),
                ];
            })
            ->sortBy(fn (array $asset) => $asset['folder'].'/'.$asset['filename'], SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view('cms.cards.index', compact('assets', 'cards', 'stacks'));
    }

    private function addArtworkMetadata(Card $card): void
    {
        $card->setAttribute('artwork_bytes', $this->artworkBytes($card->image));
        $card->setAttribute('artwork_extension', $this->artworkExtension($card->image));
    }

    private function artworkExtension(?string $path): string
    {
        if (! $path || $path === '0') return '—';
        $extension = strtolower((string) pathinfo((string) (parse_url($path, PHP_URL_PATH) ?: $path), PATHINFO_EXTENSION));
        return match ($extension) {
            'jpeg', 'jpg' => 'JPG',
            'webp' => 'WEBP',
            'png' => 'PNG',
            default => $extension !== '' ? strtoupper($extension) : '—',
        };
    }

    private function artworkBytes(?string $path): ?int
    {
        if (! $path || $path === '0' || Str::startsWith($path, ['http://', 'https://'])) {
            return null;
        }

        $storagePath = preg_replace('#^storage/#', '', ltrim($path, '/'));
        try {
            return Storage::disk('public')->exists($storagePath)
                ? Storage::disk('public')->size($storagePath)
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function create()
    {
        return view('cms.cards.form', ['card' => new Card, 'stacks' => Stack::orderBy('name')->get()]);
    }

    public function edit(Request $request, Card $card)
    {
        return view('cms.cards.form', compact('card') + [
            'stacks' => Stack::orderBy('name')->get(),
            'cardsReturnUrl' => $this->cardsReturnUrl($request),
        ]);
    }

    public function store(Request $request)
    {
        $card = Card::create($this->validated($request));
        $this->storeUpload($request, $card);

        return redirect()->route('cms.cards.edit', $card)->with('success', 'Card created.');
    }

    public function update(Request $request, Card $card)
    {
        $previousImage = $card->image;
        $card->update($this->validated($request));
        if (! $request->hasFile('image_upload') && $previousImage !== $card->image) {
            $card->update(['artwork_enhanced' => false]);
            $this->deleteManagedImage($previousImage);
        }
        $this->storeUpload($request, $card);

        return back()->with('success', 'Card saved.');
    }

    public function updateScore(Request $request, Card $card)
    {
        $data = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ]);

        $card->update(['score' => $data['score']]);

        return response()->json([
            'score' => (float) $card->score,
            'formatted_score' => number_format((float) $card->score, 2, '.', ''),
        ]);
    }

    public function destroy(Request $request, Card $card)
    {
        $image = $card->image;
        $this->deleteManagedSvg($card->svg_img);
        $card->delete();
        $this->deleteManagedImageIfUnused($image);

        if (! $request->filled('return')) {
            $request->merge(['return' => (string) $request->headers->get('referer', '')]);
        }

        return redirect($this->cardsReturnUrl($request))->with('success', 'Card deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', 'distinct', 'exists:cards,id']]);
        $cards = Card::query()->whereKey($data['ids'])->get();
        $images = $cards->pluck('image')->filter()->unique()->values();
        $svgs = $cards->pluck('svg_img')->filter()->unique()->values();

        DB::transaction(fn () => Card::query()->whereKey($cards->modelKeys())->delete());
        $images->each(fn ($path) => $this->deleteManagedImageIfUnused($path));
        $svgs->each(fn ($path) => $this->deleteManagedSvg($path));

        return response()->json(['deleted' => $cards->count(), 'ids' => $cards->modelKeys()]);
    }

    public function assignArtwork(Request $request, Card $card)
    {
        $data = $request->validate(['asset_path' => ['required', 'string', 'max:2048']]);
        $path = str_replace('\\', '/', ltrim($data['asset_path'], '/'));
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));
        abort_if(str_contains($path, '../') || ! Str::startsWith($path, ['cards/', 'generated/']), 422, 'The selected asset path is invalid.');
        abort_unless(in_array($extension, ['jpg', 'jpeg', 'webp', 'png', 'gif', 'avif'], true), 422, 'The selected asset is not a supported image.');
        abort_unless(Storage::disk('public')->exists($path), 422, 'The selected asset is unavailable.');

        $card->update(['image' => $path, 'artwork_enhanced' => str_contains(Str::lower($path), '-enhanced-')]);

        return response()->json([
            'card_id' => $card->id,
            'image' => $this->cardImageUrl($card->image),
            'artwork_enhanced' => $card->artwork_enhanced,
            'bytes' => $this->artworkBytes($card->image),
            'extension' => $this->artworkExtension($card->image),
        ]);
    }

    public function swapArtwork(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'size:2'], 'ids.*' => ['integer', 'distinct', 'exists:cards,id']]);
        $cards = Card::query()->whereKey($data['ids'])->get()->keyBy('id');
        $first = $cards->get((int) $data['ids'][0]);
        $second = $cards->get((int) $data['ids'][1]);
        abort_if(blank($first->image) || $first->image === '0' || blank($second->image) || $second->image === '0', 422, 'Both selected cards must have artwork to swap.');

        DB::transaction(function () use ($first, $second) {
            [$firstImage, $firstEnhanced] = [$first->image, $first->artwork_enhanced];
            $first->update(['image' => $second->image, 'artwork_enhanced' => $second->artwork_enhanced]);
            $second->update(['image' => $firstImage, 'artwork_enhanced' => $firstEnhanced]);
        });

        return response()->json(['cards' => collect([$first->fresh(), $second->fresh()])->map(fn (Card $item) => [
            'id' => $item->id,
            'image' => $this->cardImageUrl($item->image),
            'artwork_enhanced' => $item->artwork_enhanced,
            'bytes' => $this->artworkBytes($item->image),
            'extension' => $this->artworkExtension($item->image),
        ])->values()]);
    }

    public function setStatus(Request $request, Card $card)
    {
        $data = $request->validate(['status' => ['required', 'boolean']]);
        $card->update(['status' => (bool) $data['status']]);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $card->id,
                'status' => $card->status,
                'label' => $card->status ? 'APPROVED' : 'DRAFT',
            ]);
        }

        return back()->with('success', $card->status ? 'Card approved.' : 'Card returned to draft.');
    }

    public function translateToBosnian(Request $request, Card $card)
    {
        $source = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:1000'],
            'save' => ['sometimes', 'boolean'],
        ]);
        $source['title'] = trim($source['title']);
        $source['subtitle'] = trim((string) ($source['subtitle'] ?? ''));

        abort_if(
            ! config('services.gemini.key') && ! config('services.openrouter.key'),
            422,
            'GEMINI_API_KEY and OPENROUTER_API_KEY are not configured on the backend.'
        );

        $prompt = $this->bosnianTranslationPrompt($source['title'], $source['subtitle']);
        $providers = [];
        if (config('services.gemini.key')) {
            $providers['Gemini'] = fn () => $this->translateWithGemini($prompt);
        }
        if (config('services.openrouter.key')) {
            $providers['OpenRouter'] = fn () => $this->translateWithOpenRouter($prompt);
        }

        $lastError = null;
        foreach ($providers as $provider => $translate) {
            try {
                $translation = $this->decodeBosnianTranslation($translate(), $source['subtitle'] !== '');
                if ($request->boolean('save')) {
                    $card->update($translation);
                }

                Log::info('CMS card translated to Bosnian', [
                    'card_id' => $card->id,
                    'provider' => $provider,
                ]);

                return response()->json($translation + [
                    'provider' => $provider,
                    'saved' => $request->boolean('save'),
                ]);
            } catch (Throwable $error) {
                $lastError = $error;
                Log::warning('CMS Bosnian card translation provider failed', [
                    'card_id' => $card->id,
                    'provider' => $provider,
                    'exception' => $error,
                ]);
            }
        }

        return response()->json([
            'message' => 'AI translation failed: '.($lastError?->getMessage() ?: 'No provider returned a valid Bosnian translation.'),
        ], 502);
    }

    private function bosnianTranslationPrompt(string $title, string $subtitle): string
    {
        return implode("\n", [
            'Translate this Misery Meter game-card copy from English into standard BOSNIAN (language code bs).',
            'The target language is specifically Bosnian, not Croatian and not Serbian.',
            'Use natural contemporary Bosnian in the ijekavian standard. Avoid Croatian-only wording, Serbian ekavian forms, and unnatural literal calques.',
            'Pay exceptional attention to Bosnian grammar, cases, gender, number, agreement, word order, idiom, and punctuation.',
            'CARD-TITLE GRAMMAR IS MANDATORY: express the title as a concise nominal phrase whose grammatical head is in the nominative case, normally using a Bosnian verbal noun (glagolska imenica). Never translate an English imperative title as a command, and do not use an infinitive as the title.',
            'Example: "Send a Private Photo to the Family Group" must become "Slanje privatne fotografije u porodičnu grupu", never "Pošalji privatnu fotografiju porodičnoj grupi".',
            'Use the Bosnian Latin alphabet correctly. Preserve every diacritic and especially distinguish the affricates č, ć, dž and đ. Never replace them with c, dj, dz, or approximate spellings.',
            'Keep the same meaning, severity, humor, point of view, names, numbers, and factual details. Do not add, remove, soften, censor, or explain content.',
            'The title must remain concise and instantly readable on a game card. The description must remain one natural sentence when the English source has a description.',
            'Silently proofread the final Bosnian for grammar and correct affricates before returning it.',
            'Return only valid UTF-8 JSON with exactly these keys: title_bs and subtitle_bs. Use null for subtitle_bs only when the English subtitle is empty.',
            '',
            'English title: '.$title,
            'English description: '.($subtitle !== '' ? $subtitle : '(empty)'),
        ]);
    }

    private function translateWithGemini(string $prompt): string
    {
        $model = (string) config('services.gemini.text_model');
        $response = Http::withHeaders(['x-goog-api-key' => config('services.gemini.key')])
            ->acceptJson()->asJson()->timeout(120)
            ->post(rtrim((string) config('services.gemini.base_url'), '/').'/models/'.rawurlencode($model).':generateContent', [
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.2],
            ])->throw()->json();

        $content = implode('', array_map(
            fn ($part) => is_string(data_get($part, 'text')) ? data_get($part, 'text') : '',
            (array) data_get($response, 'candidates.0.content.parts', [])
        ));
        throw_if($content === '', RuntimeException::class, 'Gemini returned no translation.');

        return $content;
    }

    private function translateWithOpenRouter(string $prompt): string
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'title_bs' => ['type' => 'string'],
                'subtitle_bs' => ['type' => ['string', 'null']],
            ],
            'required' => ['title_bs', 'subtitle_bs'],
            'additionalProperties' => false,
        ];
        $response = Http::withToken(config('services.openrouter.key'))
            ->withHeaders(array_filter([
                'HTTP-Referer' => config('services.openrouter.http_referer'),
                'X-Title' => config('services.openrouter.title'),
            ]))->acceptJson()->asJson()->timeout(120)
            ->post(rtrim((string) config('services.openrouter.base_url'), '/').'/chat/completions', [
                'model' => config('services.openrouter.text_model'),
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a meticulous professional translator into standard Bosnian. Return only the requested JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_schema', 'json_schema' => [
                    'name' => 'bosnian_card_translation', 'strict' => true, 'schema' => $schema,
                ]],
            ])->throw()->json();

        $content = data_get($response, 'choices.0.message.content');
        throw_unless(is_string($content) && $content !== '', RuntimeException::class, 'OpenRouter returned no translation.');

        return $content;
    }

    private function decodeBosnianTranslation(string $content, bool $subtitleRequired): array
    {
        $json = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content)) ?? trim($content));
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $title = trim((string) data_get($decoded, 'title_bs', ''));
        $subtitleValue = data_get($decoded, 'subtitle_bs');
        $subtitle = is_string($subtitleValue) ? trim($subtitleValue) : null;

        throw_if($title === '' || mb_strlen($title) > 255, RuntimeException::class, 'AI returned an invalid Bosnian title.');
        throw_if($subtitleRequired && ($subtitle === null || $subtitle === ''), RuntimeException::class, 'AI returned no Bosnian description.');
        throw_if($subtitle !== null && mb_strlen($subtitle) > 1000, RuntimeException::class, 'AI returned a Bosnian description that is too long.');

        return ['title_bs' => $title, 'subtitle_bs' => $subtitleRequired ? $subtitle : null];
    }

    public function generate(Request $request, Card $card)
    {
        $generationId = (string) Str::uuid();
        $startedAt = microtime(true);
        $logContext = [
            'generation_id' => $generationId,
            'card_id' => $card->id,
            'card_title' => $card->title,
            'ip' => $request->ip(),
            'model' => config('services.openrouter.image_model'),
        ];

        Log::info('CMS artwork generation clicked', $logContext + [
            'referer' => $request->headers->get('referer'),
            'openrouter_configured' => filled(config('services.openrouter.key')),
            'gemini_fallback_configured' => filled(config('services.gemini_fallback.key')),
            'provider_url' => rtrim((string) config('services.openrouter.base_url'), '/').'/images',
        ]);

        if (! config('services.openrouter.key')) {
            Log::warning('CMS artwork generation stopped: API key missing', $logContext);

            return $this->artworkGenerationError($request, "OPENROUTER_API_KEY is not configured on the backend. [Generation: {$generationId}]");
        }
        $prompt = implode("\n", [
            'Use case: stylized-concept',
            'Asset type: finished square WebP illustration for a Misery Index game card',
            "Situation: {$card->title}",
            $card->subtitle ? "Context: {$card->subtitle}" : '',
            'Create one bold, instantly readable pictogram scene that clearly explains the unfortunate situation.',
            'Be highly creative: invent a clever, surprising visual metaphor for this specific situation while keeping the scene instantly understandable at card size. Avoid generic or repetitive compositions.',
            'Cast requirement: always depict at least two clearly visible people. Use two people by default and add a third only when it materially improves the situation. Never generate a one-person scene.',
            'Multi-person staging: organize the composition around the two primary people. Give each person a distinct pose, clear role, and direct physical or visual relationship to the same event. They must interact with each other, the same prop, or the same cause-and-effect action rather than appearing as unrelated figures.',
            'Character hierarchy: one person must be the obvious main character and primary victim of the misery. Make this person larger, more central, and most visually important. Their pose must clearly show the unfortunate action or consequence.',
            'Secondary character role: when the misery primarily affects one person, give the second person the most natural role required by the situation: helping, warning, rescuing, serving, operating equipment, being affected by the same problem, unintentionally causing it, or reacting with concern, surprise, fear, or confusion. Show this through clear full-body action; the secondary face must remain completely blank.',
            'Mocking restriction: do not make the secondary person laugh at, point at, film, tease, celebrate, or mock the main victim by default. Use ridicule or amused audience behavior only when the card title or context explicitly and inherently involves public embarrassment, humiliation, a performance, an audience, or someone recording the incident. Ordinary home, travel, health, work, weather, mechanical, and accident situations should use a practical or empathetic secondary role instead.',
            'Secondary-character necessity: the second person must contribute to the event rather than merely stand nearby and react. Give them a concrete action, physical connection, shared consequence, or useful story function that helps explain what is happening.',
            'Shared-event exception: when the situation naturally affects a pair or group, both primary people may directly experience or perform the event. Even then, give them distinct roles and poses so the action reads as one connected interaction rather than duplicated figures.',
            'Pose variety rule: do not default to the main character holding their head, putting both hands on their head, covering their face, or standing with symmetrical raised arms. Avoid these cliché poses unless touching the head is literally required by the situation.',
            'Express emotion through situation-specific full-body acting: stumbling backward, freezing mid-action, leaning away, recoiling, dropping an object, reaching toward the problem, bracing against something, pointing in disbelief, kneeling, slipping, twisting, shielding the body, or interacting directly with the hazard. Choose a natural pose that explains this exact event.',
            'Style: polished, modern editorial safety-sign illustration made from clean, bold, solid-filled geometric people and objects. It should look professionally vector-designed, not like crude clip art.',
            'Mandatory fill style: every visible person, object, and detail must be a solid filled silhouette or filled shape. Do not use outline-only, stroke-only, line-art, wireframe, hollow, contour, border-style, or unfilled elements. Use smooth antialiased curves and crisp high-resolution edges.',
            'Strict three-color palette: use exactly these three flat colors and no others: pure black #000000, pure white #FFFFFF, and primary amber #FACC15. Every filled area must be one uniform solid color. Do not use gray, off-white, beige, orange, extra shades, gradients, color transitions, shading, highlights, lighting, glow, transparency, or checkerboard patterns.',
            'Absolute gradient ban: never use a linear, radial, spotlight, shadow, fade, vignette, tonal variation, darker amber, lighter amber, gray antialias fill, or simulated depth. Do not shade furniture, floors, walls, people, props, or backgrounds. Flat solid fills only.',
            'Color roles: the entire square background must be solid black #000000; all people must be solid white #FFFFFF; the event, hazard, action, props, and misery-causing elements must be solid amber #FACC15. Black may define negative space and minimal environmental shapes.',
            'White is reserved exclusively for human silhouettes. Never use white for floors, stages, podiums, platforms, furniture, walls, borders, props, devices, spotlights, or background shapes. Never create a large white rectangle, block, slab, base, band, or mass anywhere in the scene.',
            'Mandatory color balance: white and amber must both be clearly visible and important to the scene. Include at least one large, distinct, solid-white person occupying a meaningful part of the illustration; tiny white accents, outlines, or highlights do not count. Use amber for at least one substantial event or hazard element. Never return an amber-only image.',
            'People: depict humans as anonymous safety-sign silhouettes with simple circular heads. No eyes, pupils, eyebrows, eyelashes, nose, nostrils, ears, hair, or facial hair.',
            'Main-character mouth exception: the primary victim may have exactly one tiny, simple black mouth shape to communicate the appropriate emotion. Use only a minimal open oval, small curved line, or short flat line. No lips, teeth, tongue, outline, shading, or other facial features. All secondary characters must keep completely blank faces.',
            'Mandatory subject color: the main human silhouette must ALWAYS be solid pure white #FFFFFF. Never make the main silhouette amber, gray, black, transparent, outlined, or any other color.',
            'Mandatory event color: the event-specific element that causes or represents the misery must be solid primary amber #FACC15. Supporting hazard or action elements should also use amber when useful.',
            'Reference images: use the attached main-silhouette PNG as the required character reference, and use the three attached illustration examples only as visual-style references for bold pictogram storytelling, clean shape language, and readable staging.',
            'Reference limits: create an original composition for this exact situation. Do not copy any example composition, pose, object arrangement, text, lettering, logo, border, background treatment, rounded corners, checkerboard, white floor or platform, floating prop, icon collage, or colors outside the required three-color palette.',
            'Physical scene rule: create one coherent, believable cause-and-effect composition, not a collage, infographic, diagram, collection of icons, or scattered symbols. Every element must have an obvious spatial relationship to the main action.',
            'Grounding rule: nothing may float. Every person must visibly stand, sit, fall, lean, or make contact with another object. Every prop must be visibly held, attached, mounted on a stand or wall, or resting on a clearly connected surface.',
            'Universal object rule: no object of any kind may appear as a free-floating icon or disconnected symbol. Every device, tool, hazard, piece of furniture, vehicle, sign, light, prop, and environmental element must be visibly held, attached, mounted, supported, resting on a connected surface, or naturally positioned within the scene.',
            'Environmental grounding: use black or amber environmental geometry when it genuinely helps establish a real floor, stage, curb, wall, room, tray, platform, or support. Keep it physically plausible and subordinate to the main action. Platforms and environmental planes are allowed, but they must be intentionally designed and completely resolved.',
            'Complete environment rule: dioramas, floor islands, platforms, trays, cutaway rooms, and enclosed perspective planes are allowed when appropriate to the situation. If used, show their complete intentional shape and all visible boundaries; never leave them abruptly unfinished, accidentally open, severed, or cut off by the canvas.',
            'Camera distance is mandatory: use a wide enough view to show every person and object completely from end to end, but scale and arrange the overall scene to use the full square. Never use a close-up, tight crop, tiny centered composition, or oversized foreground.',
            'Mandatory four-edge coverage: the composed artwork must visibly reach within 10 pixels of the top edge, bottom edge, left edge, and right edge. Place at least one deliberate, fully finished person, object, effect, or environmental element near each of the four edges. Check every edge separately, especially the bottom edge. Do not leave a large unused black band on any side.',
            'Coverage without cropping: elements near the edges must remain complete and must end visibly inside the canvas. Nothing may touch or cross the outermost boundary, and no element may be clipped merely to satisfy the four-edge coverage rule.',
            'Composition priority: complete visibility and balanced full-canvas coverage are equally mandatory. Reduce unnecessary supporting details if needed, then distribute the finished scene across the entire square while keeping both people clearly readable.',
            'Complete-object construction: every person and physical object must have a complete, intentional, closed silhouette. Show both ends and all structurally necessary parts. Never leave an object unfinished, severed, open-ended, abruptly stopped, hidden by the canvas edge, or implied to continue off-screen. Lines belonging to a person or object may not terminate at an image edge.',
            'Environmental treatment: establish location with connected ground-contact lines, background silhouettes, perspective planes, or a complete base when useful. Every environmental shape must terminate deliberately and read as finished construction, never as an accidental fragment or an object that continues off-screen.',
            'Absolute crop ban: never crop, clip, truncate, or cut off any subject or object at the top, bottom, left, or right edge. No body part, furniture leg, vehicle, device, effect, or action element may continue beyond the frame. If the scene is too large, scale the entire composition down until everything is fully visible.',
            'Only the uniform black background may occupy the corners and the full outer boundary. Do not use edge-to-edge foreground shapes or partial off-screen objects as a compositional device.',
            'Three-part composition recipe: structure every image around (1) one dominant main victim, (2) one clearly readable secondary character reacting or participating, and (3) one grounded event object or environmental cluster that explains the misery. These parts must connect naturally and distribute visual weight across the entire square.',
            'Primary composition reference: use the attached good-example image only for staging, visual hierarchy, grounded props, diagonal balance, and the relationship between a person and an event object. Adapt its scale so the new complete composition reaches within 10 pixels of every edge without cropping. Any perspective floor, platform, or environmental base must be completely constructed and fully visible.',
            'Perspective variety: preserve the reference imageâ€™s dimensional, solid, three-dimensional scene construction, but do not reuse its exact camera angle or object orientation. Vary the viewpoint between left and right three-quarter views, high three-quarter views, low three-quarter views, diagonal stage views, and shallow isometric views. Never render every scene from the same perspective.',
            'Do not copy from the good example: ignore and omit its timer display, digits, warning symbol, appliance, laundry, exact camera angle, exact pose, exact object shapes, and one-person cast. Replace its content and viewpoint with the current situation and the required two-character hierarchy.',
            'Background geometry is mandatory: fill the entire square canvas with opaque pure black #000000, including all four corners and every pixel along all four outer edges.',
            'Never place the scene inside a rounded rectangle, rounded card, inset panel, container, vignette, mask, frame, or tile. Never round, clip, soften, curve, bevel, or cut off the canvas corners.',
            'The four canvas corners must be square and pure black. There must be no white corner wedges, margin, padding outside the black background, visible outer canvas, transparency, alpha, checkerboard pattern, border, or contrasting area around the black scene.',
            'Absolute frame ban: do not draw any enclosing rectangle, square, outline, keyline, stroke, picture frame, card frame, poster frame, inner border, outer border, white border, amber border, or decorative line around the scene. No line may run parallel to and connect around the four canvas edges. The artwork must remain an open edge-to-edge scene on black.',
            'Quality: render at 1024×1024 or higher with smooth antialiasing, clean curves, crisp shape boundaries, and no pixelation, jagged edges, compression artifacts, grain, noise, halftone dots, texture, or blur.',
            'Production sharpness pass: render every silhouette and geometric shape as if it were precision vector artwork, with decisive high-contrast boundaries, smooth curves, clean joins, and legible small details. Inspect the result at 200% and eliminate soft focus, smeared edges, stair-stepping, fuzzy halos, color fringing, and malformed micro-details before returning it.',
            'Lightweight delivery design: keep the scene visually bold and economical enough to encode as a 1024×1024 WebP below 100 KB without visible degradation. Prefer large clean filled shapes over noisy micro-detail, dense texture, or needless tiny fragments; file-size optimization must never reduce edge sharpness or introduce pixelation.',
            'Zero typography rule: the generated image must contain absolutely no text-like marks: no words, letters, numbers, digits, decimal points, scores, ratings, captions, labels, signs, UI, symbols that resemble writing, logos, or watermark. Do not render the card title, description, or misery score inside the artwork.',
            'Output is illustration only: never depict a finished game card, card shell, poster, badge, score panel, caption area, title area, UI panel, or rounded rectangular container. The WebP itself is the scene, not a picture of a card.',
            'Constraints: WebP; no card frame, border, gradients, lighting effects, or shadows. Use only black #000000, white #FFFFFF, and amber #FACC15.',
        ]);

        $providerUsed = 'openrouter';
        $generationCost = null;
        try {
            $headers = array_filter([
                'HTTP-Referer' => config('services.openrouter.http_referer'),
                'X-Title' => config('services.openrouter.title'),
            ]);
            $references = $this->silhouetteReferences();
            Log::info('CMS artwork generation sending provider request', $logContext + [
                'prompt_bytes' => strlen($prompt),
                'reference_count' => count($references),
            ]);

            $providerResponse = Http::withToken(config('services.openrouter.key'))
                ->withHeaders($headers)
                ->acceptJson()
                ->asJson()
                ->timeout(180)
                ->post(rtrim(config('services.openrouter.base_url'), '/').'/images', [
                    'model' => config('services.openrouter.image_model'),
                    'prompt' => $prompt,
                    'size' => '1024x1024',
                    'quality' => 'high',
                    'background' => 'opaque',
                    'output_format' => 'webp',
                    'input_references' => $references,
                ]);

            Log::info('CMS artwork generation provider responded', $logContext + [
                'provider_status' => $providerResponse->status(),
                'provider_content_type' => $providerResponse->header('Content-Type'),
                'provider_response_bytes' => strlen($providerResponse->body()),
                'provider_error' => $providerResponse->successful()
                    ? null
                    : Str::limit((string) data_get($providerResponse->json(), 'error.message', $providerResponse->body()), 2000),
            ]);

            $response = $providerResponse->throw()->json();
            $generationCost = $this->responseCost($response);
        } catch (RequestException $error) {
            $message = (string) data_get($error->response?->json(), 'error.message', $error->getMessage());
            Log::error('CMS artwork generation provider request failed', $logContext + [
                'exception' => $error,
                'provider_status' => $error->response?->status(),
                'provider_error' => Str::limit($message, 2000),
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            if (! $this->isInsufficientCreditError($error, $message)) {
                return $this->artworkGenerationError($request, $message." [Generation: {$generationId}]");
            }

            if (! config('services.gemini_fallback.key')) {
                Log::warning('CMS artwork Gemini fallback unavailable: API key missing', $logContext);

                return $this->artworkGenerationError($request, $message." Gemini fallback is not configured; add FALLBACK_GEMINI_API_KEY. [Generation: {$generationId}]");
            }

            Log::warning('CMS artwork switching to direct Gemini fallback', $logContext + [
                'openrouter_status' => $error->response?->status(),
                'gemini_model' => config('services.gemini_fallback.image_model'),
            ]);

            try {
                $response = $this->generateWithGemini($prompt, $logContext);
                $providerUsed = 'gemini-fallback';
            } catch (Throwable $fallbackError) {
                $fallbackMessage = $fallbackError instanceof RequestException
                    ? (string) data_get($fallbackError->response?->json(), 'error.message', $fallbackError->getMessage())
                    : $fallbackError->getMessage();
                Log::error('CMS artwork direct Gemini fallback failed', $logContext + [
                    'exception' => $fallbackError,
                    'gemini_status' => $fallbackError instanceof RequestException ? $fallbackError->response?->status() : null,
                    'gemini_error' => Str::limit($fallbackMessage ?: 'Unknown Gemini error', 2000),
                    'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return $this->artworkGenerationError($request, 'OpenRouter has insufficient credit and Gemini fallback failed: '.($fallbackMessage ?: 'Unknown Gemini error')." [Generation: {$generationId}]");
            }
        } catch (Throwable $error) {
            Log::error('CMS artwork generation could not start', $logContext + [
                'exception' => $error,
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            $message = $error->getMessage() ?: 'Artwork generation could not be started.';

            return $this->artworkGenerationError($request, $message." [Generation: {$generationId}]");
        }

        try {
            $encoded = data_get($response, 'data.0.b64_json');
            Log::info('CMS artwork generation parsing image data', $logContext + [
                'response_keys' => is_array($response) ? array_keys($response) : [],
                'encoded_image_present' => filled($encoded),
                'encoded_image_bytes' => is_string($encoded) ? strlen($encoded) : 0,
            ]);
            abort_unless($encoded, 502, 'Image provider returned no image data.');
            $path = 'cards/generated/webp/card-'.$card->id.'-'.now()->format('YmdHis').'.webp';
            $image = base64_decode($encoded, true);
            abort_unless($image !== false, 502, 'Image provider returned invalid image data.');
            $originalBytes = strlen($image);
            $webp = $this->convertGeneratedImageToWebp($image);
            Log::info('CMS artwork generation optimized image', $logContext + [
                'original_image_bytes' => $originalBytes,
                'webp_bytes' => strlen($webp),
                'storage_path' => $path,
            ]);
            abort_if(strlen($webp) > self::MAX_GENERATED_JPEG_BYTES, 502, 'Generated WebP could not be optimized below 100 KB without unacceptable quality loss. Please generate it again.');
            Storage::disk('public')->put($path, $webp);
            abort_unless(Storage::disk('public')->exists($path), 500, 'Generated WebP was not found after writing it to storage.');
            $this->deleteManagedImage($card->image);
            $card->update(['image' => $path, 'artwork_enhanced' => false]);
        } catch (Throwable $error) {
            Log::error('CMS artwork generation image processing failed', $logContext + [
                'exception' => $error,
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            $message = $error->getMessage() ?: 'Generated artwork could not be saved.';

            return $this->artworkGenerationError($request, $message." [Generation: {$generationId}]");
        }

        Log::info('CMS artwork generation completed', $logContext + [
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'provider' => $providerUsed,
            'storage_path' => $path,
            'generation_cost' => $generationCost,
        ]);

        $costMessage = $generationCost !== null
            ? ' Cost: $'.number_format($generationCost, 6, '.', '').'.'
            : '';

        $message = 'Black-background WebP artwork generated via '.($providerUsed === 'gemini-fallback' ? 'direct Gemini fallback' : 'OpenRouter').'. Adjust the square crop if needed.'.$costMessage." [Generation: {$generationId}]";
        if ($request->expectsJson()) {
            return response()->json([
                'image' => url('/card-images/'.$path),
                'bytes' => strlen($webp),
                'extension' => 'WEBP',
                'message' => $message,
                'generation_id' => $generationId,
            ]);
        }

        return back()
            ->with('success', $message)
            ->with('crop_generated_artwork', [
                'card_id' => $card->id,
                'path' => $path,
                'generation_id' => $generationId,
            ])
            ->with('generated_prompt', $prompt);
    }

    public function saveGeneratedCrop(Request $request, Card $card)
    {
        $data = $request->validate([
            'crop_data' => ['required', 'string', 'max:12000000'],
            'generation_id' => ['nullable', 'uuid'],
        ]);

        abort_unless(
            preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=\r\n]+)$#', $data['crop_data'], $matches) === 1,
            422,
            'The cropped artwork must be a JPEG image.'
        );

        $image = base64_decode(str_replace(["\r", "\n"], '', $matches[1]), true);
        abort_unless($image !== false && $image !== '', 422, 'The cropped artwork could not be decoded.');
        abort_if(strlen($image) > 8 * 1024 * 1024, 422, 'The cropped artwork is too large.');

        $webp = $this->convertImageToWebp($image, 768, 422);
        abort_if(strlen($webp) > self::MAX_GENERATED_JPEG_BYTES, 422, 'The cropped WebP could not be optimized below 100 KB. Please zoom or crop it again.');

        $path = 'cards/generated/webp/card-'.$card->id.'-cropped-'.Str::uuid().'.webp';
        Storage::disk('public')->put($path, $webp);
        abort_unless(Storage::disk('public')->exists($path), 500, 'The cropped artwork was not found after writing it to storage.');

        $previousImage = $card->image;
        $card->update(['image' => $path, 'artwork_enhanced' => false]);
        $this->deleteManagedImage($previousImage);

        Log::info('CMS generated artwork crop saved', [
            'card_id' => $card->id,
            'generation_id' => $data['generation_id'] ?? null,
            'storage_path' => $path,
            'webp_bytes' => strlen($webp),
            'dimensions' => '768x768',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'image' => url('/card-images/'.$path),
                'bytes' => strlen($webp),
                'extension' => 'WEBP',
                'message' => '768 × 768 WebP artwork crop saved.',
            ]);
        }

        return redirect($this->cardEditUrl($request, $card))->with('success', '768 × 768 WebP artwork crop saved.');
    }

    public function convertArtworkToWebp(Request $request, Card $card)
    {
        $previousPath = (string) $card->image;
        abort_if($previousPath === '' || $previousPath === '0' || Str::startsWith($previousPath, ['http://', 'https://']), 422, 'Only locally stored card artwork can be converted.');
        $storagePath = preg_replace('#^storage/#', '', ltrim($previousPath, '/'));
        abort_unless(is_string($storagePath) && Storage::disk('public')->exists($storagePath), 404, 'The current artwork could not be found.');

        $webp = $this->convertImageToWebp(Storage::disk('public')->get($storagePath), 768, 422);
        abort_if(strlen($webp) > self::MAX_GENERATED_JPEG_BYTES, 422, 'The WebP artwork could not be optimized below 100 KB.');
        $path = 'cards/generated/webp/card-'.$card->id.'-'.Str::uuid().'.webp';
        Storage::disk('public')->put($path, $webp);
        abort_unless(Storage::disk('public')->exists($path), 500, 'The converted WebP artwork was not found after writing it to storage.');

        $card->update(['image' => $path]);
        $this->deleteManagedImage($previousPath);

        return response()->json([
            'bytes' => strlen($webp),
            'extension' => 'WEBP',
            'dimensions' => '768x768',
            'image' => url('/card-images/'.$path),
            'message' => 'Artwork converted to 768 × 768 WebP.',
        ]);
    }

    public function enhanceArtwork(Request $request, Card $card, GeminiImageGenerator $generator)
    {
        $previousPath = (string) $card->image;
        abort_if($previousPath === '' || $previousPath === '0' || Str::startsWith($previousPath, ['http://', 'https://']), 422, 'Only locally stored card artwork can be enhanced.');

        $storagePath = preg_replace('#^storage/#', '', ltrim($previousPath, '/'));
        abort_unless(is_string($storagePath) && Storage::disk('public')->exists($storagePath), 404, 'The current artwork could not be found.');

        $original = Storage::disk('public')->get($storagePath);
        abort_unless(function_exists('imagecreatefromstring'), 422, 'GD is unavailable for artwork enhancement.');
        $source = @imagecreatefromstring($original);
        abort_unless($source !== false, 422, 'The current artwork is not a valid image.');

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width < 128 || $height < 128 || $width !== $height) {
            imagedestroy($source);
            abort(422, 'Artwork must be a square image of at least 128 pixels before enhancement.');
        }

        imagedestroy($source);

        $prompt = implode("\n", [
            'Enhance and restore the supplied Misery Meter card artwork at high resolution.',
            'This is image restoration, not a redesign: preserve the exact composition, number of people, poses, objects, proportions, relative placements, and visual meaning. DO NOT preserve the source crop or framing when it contains empty black margins. The only permitted composition-level change is a uniform zoom plus translation of the complete artwork to create the required tighter framing.',
            'Keep the solid black background and use exactly pure black #000000, pure white #FFFFFF, and canonical Misery yellow #FACC15. If the source yellow has drifted toward orange, gold, amber, or another shade, correct every yellow area back to the uniform solid color #FACC15. Do not introduce text, logos, borders, gradients, shadows, textures, new props, or new subjects.',
            'AUTO-ZOOM AND REFRAME — REQUIRED, NOT OPTIONAL: before restoring detail, identify the tight bounding box of every real non-black pixel in the supplied artwork. Uniformly enlarge and translate the complete composition so that this bounding box uses the maximum possible area of the square canvas. Empty black margin from the source must not be copied into the result. Keep every person, object, limb, and event element completely visible; never stretch, distort, cut off, rotate, or rearrange anything.',
            'EDGE ACCEPTANCE CHECK: inspect the finished image itself, not the source. The topmost real colored or white pixel must begin at the top content boundary, including when the source has a large empty black band above the scene. Apply the same tight-framing check independently to bottom, left, and right. If any side still contains avoidable empty black padding, the result is unfinished: zoom and reframe again before returning it. Sharpness improvement alone is not a successful enhancement.',
            'Remove JPEG artifacts, blockiness, pixelation, jagged diagonals, blurry contours, color fringing, and smeared edges. Reconstruct every silhouette and object with crisp, smooth, precision-vector-like boundaries.',
            'Return one square image only. It must look like the same artwork rendered cleanly at professional high resolution.',
        ]);

        try {
            $generated = $generator->generate($prompt, [[
                'mime_type' => (string) (getimagesizefromstring($original)['mime'] ?? 'image/jpeg'),
                'data' => $original,
                'label' => 'This is the exact artwork to restore. Preserve it faithfully and only improve rendering quality.',
            ]], '1:1');
            $generatedSource = @imagecreatefromstring($generated['data']);
            throw_unless($generatedSource !== false, RuntimeException::class, 'The enhancement model returned an invalid image.');
            throw_unless(imagesx($generatedSource) === imagesy($generatedSource), RuntimeException::class, 'The enhancement model did not return a square image.');
            $enhanced = $this->enhanceCardArtwork($generatedSource);
            imagedestroy($generatedSource);
            $enhanced = $this->normalizeEnhancedArtworkColors($enhanced);
            $webp = $this->encodeCardWebp($enhanced);
            imagedestroy($enhanced);
            throw_if(strlen($webp) > self::MAX_GENERATED_JPEG_BYTES, RuntimeException::class, 'Enhanced WebP artwork could not be optimized below 100 KB.');
        } catch (Throwable $error) {
            Log::error('CMS Gemini card artwork enhancement failed', [
                'card_id' => $card->id,
                'exception' => $error,
            ]);

            $message = 'Artwork enhancement failed: '.($error->getMessage() ?: 'Unknown image provider error.');

            return $request->expectsJson()
                ? response()->json(['message' => $message], 502)
                : redirect($this->cardEditUrl($request, $card))->withErrors(['generation' => $message]);
        }

        $path = 'cards/generated/webp/card-'.$card->id.'-enhanced-'.Str::uuid().'.webp';
        Storage::disk('public')->put($path, $webp);
        abort_unless(Storage::disk('public')->exists($path), 500, 'Enhanced artwork was not found after writing it to storage.');

        $card->update(['image' => $path, 'artwork_enhanced' => true]);
        $this->deleteManagedImage($previousPath);

        Log::info('CMS card artwork enhanced', [
            'card_id' => $card->id,
            'source_dimensions' => $width.'x'.$height,
            'source_bytes' => strlen($original),
            'webp_bytes' => strlen($webp),
            'storage_path' => $path,
            'provider' => $generated['provider'],
            'model' => $generated['model'],
        ]);

        $message = 'Artwork enhanced with '.$generated['provider'].' using '.$generated['model'].', then saved as 1024 × 1024 WebP below 100 KB.';

        return $request->expectsJson()
            ? response()->json([
                'image' => url('/card-images/'.$path),
                'bytes' => strlen($webp),
                'extension' => 'WEBP',
                'message' => $message,
            ])
            : redirect($this->cardEditUrl($request, $card))->with('success', $message);
    }

    private function responseCost(array $response): ?float
    {
        foreach (['usage.cost', 'data.0.usage.cost', 'cost'] as $path) {
            $cost = data_get($response, $path);
            if (is_numeric($cost)) {
                return (float) $cost;
            }
        }

        return null;
    }

    private function artworkGenerationError(Request $request, string $message)
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], 502)
            : back()->withErrors(['generation' => $message]);
    }

    public function generateSvg(Request $request, Card $card)
    {
        $generationId = (string) Str::uuid();
        $model = (string) config('services.gemini.text_model');
        $logContext = [
            'generation_id' => $generationId,
            'card_id' => $card->id,
            'card_title' => $card->title,
            'ip' => $request->ip(),
            'model' => $model,
        ];

        if (! config('services.gemini.key')) {
            Log::warning('CMS SVG generation stopped: Gemini key missing', $logContext);

            return back()->withErrors([
                'generation' => "GEMINI_API_KEY or FALLBACK_GEMINI_API_KEY is not configured. [Generation: {$generationId}]",
            ]);
        }

        $prompt = $this->svgGenerationPrompt($card);
        $url = rtrim((string) config('services.gemini.base_url'), '/')
            .'/models/'.rawurlencode($model).':generateContent';

        Log::info('CMS SVG generation sending Gemini request', $logContext + [
            'endpoint' => $url,
            'prompt_bytes' => strlen($prompt),
        ]);

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => config('services.gemini.key'),
            ])
                ->acceptJson()
                ->asJson()
                ->timeout(120)
                ->post($url, [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => "You create safe, minimal SVG pictograms. Return only complete SVG markup with no Markdown or explanation.\n\n{$prompt}"]],
                    ]],
                ])
                ->throw()
                ->json();

            $parts = (array) data_get($response, 'candidates.0.content.parts', []);
            $content = implode('', array_map(
                fn ($part) => is_string(data_get($part, 'text')) ? data_get($part, 'text') : '',
                $parts
            ));
            throw_if($content === '', RuntimeException::class, 'Gemini returned no SVG code.');

            $svg = $this->sanitizeGeneratedSvg($content);
            $path = 'cards/generated-svg/card-'.$card->id.'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6)).'.svg';
            Storage::disk('public')->put($path, $svg);
            abort_unless(Storage::disk('public')->exists($path), 500, 'Generated SVG was not found after writing it to storage.');
            $this->deleteManagedSvg($card->svg_img);
            $card->update(['svg_img' => $path]);
        } catch (Throwable $error) {
            $message = $error instanceof RequestException
                ? (string) data_get($error->response?->json(), 'error.message', $error->getMessage())
                : ($error->getMessage() ?: 'SVG generation failed.');
            Log::error('CMS SVG generation failed', $logContext + [
                'exception' => $error,
                'provider_status' => $error instanceof RequestException ? $error->response?->status() : null,
            ]);

            return back()->withErrors(['generation' => $message." [Generation: {$generationId}]"]);
        }

        Log::info('CMS SVG generation completed', $logContext + [
            'storage_path' => $path,
            'svg_bytes' => strlen($svg),
        ]);

        return back()
            ->with('success', "SVG illustration generated with Gemini and saved. [Generation: {$generationId}]")
            ->with('generated_svg_prompt', $prompt);
    }

    private function svgGenerationPrompt(Card $card): string
    {
        return implode("\n", [
            'Create a custom SVG pictogram for this Misery Meter game card.',
            "Situation: {$card->title}",
            $card->subtitle ? "Context: {$card->subtitle}" : '',
            'First reason silently whether the scene needs one, two, or three people, then depict the smallest cast that clearly communicates the event.',
            'Be creative and situation-specific. The result must remain instantly readable at small mobile-card size.',
            'Use viewBox="0 0 1024 1024", width="1024", height="1024", and a fully transparent background.',
            'Use only solid filled, simple vector shapes. Main people must be pure white #FFFFFF. The misery event or hazard must be amber #FACC15. Minimal environmental grounding may use only #262626 or #525252.',
            'Include both a substantial white person and a substantial amber event element. Never make people amber or the main hazard white.',
            'Keep the environment simple and connected to the action so nothing appears to float.',
            'Allowed SVG elements only: svg, g, path, circle, rect, ellipse, polygon, polyline.',
            'Allowed styling only through fill, fill-rule, clip-rule, transform, and opacity attributes. No style tags or style attributes.',
            'Do not use text, fonts, stroke, line art, gradients, filters, masks, clip paths, patterns, images, data URLs, external links, CSS, scripts, animation, IDs, classes, event handlers, metadata, comments, or embedded resources.',
            'Keep the SVG under 100 KB and use a small number of clean geometric shapes.',
            'Return only the complete <svg>...</svg> markup without a code fence or explanation.',
            '',
            'Use this existing mascot SVG markup only as a visual-language and proportion reference. Adapt the pose; do not copy it as the final scene:',
            $this->silhouetteSvg(),
        ]);
    }

    private function sanitizeGeneratedSvg(string $content): string
    {
        $svg = trim(preg_replace('/^```(?:svg|xml)?\s*|\s*```$/i', '', trim($content)) ?? trim($content));
        throw_if($svg === '' || strlen($svg) > 102400, RuntimeException::class, 'Generated SVG is empty or larger than 100 KB.');
        throw_if(str_contains($svg, '<!DOCTYPE'), RuntimeException::class, 'Generated SVG contains a forbidden document type.');

        $previousLibxmlErrors = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlErrors);

        throw_unless($loaded && $document->documentElement instanceof DOMElement, RuntimeException::class, 'Gemini returned invalid SVG XML.');
        $root = $document->documentElement;
        throw_unless($root->localName === 'svg' && $root->namespaceURI === 'http://www.w3.org/2000/svg', RuntimeException::class, 'Generated file is not a valid SVG document.');

        $allowedElements = ['svg', 'g', 'path', 'circle', 'rect', 'ellipse', 'polygon', 'polyline'];
        $allowedAttributes = [
            'xmlns', 'viewBox', 'width', 'height', 'preserveAspectRatio',
            'x', 'y', 'cx', 'cy', 'r', 'rx', 'ry', 'points', 'd',
            'fill', 'fill-rule', 'clip-rule', 'transform', 'opacity',
        ];
        $fillMap = [
            '#fff' => '#FFFFFF', '#ffffff' => '#FFFFFF', 'white' => '#FFFFFF',
            '#facc15' => '#FACC15', '#262626' => '#262626', '#525252' => '#525252',
            'none' => 'none', 'transparent' => 'none',
        ];
        $shapeCount = 0;
        $usedFills = [];

        foreach (iterator_to_array($document->getElementsByTagName('*')) as $element) {
            throw_unless(in_array($element->localName, $allowedElements, true), RuntimeException::class, "Generated SVG contains forbidden <{$element->localName}> element.");
            if ($element->localName !== 'svg' && $element->localName !== 'g') {
                $shapeCount++;
            }

            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                $name = $attribute->nodeName;
                $value = trim($attribute->nodeValue ?? '');
                throw_unless(in_array($name, $allowedAttributes, true), RuntimeException::class, "Generated SVG contains forbidden {$name} attribute.");
                throw_if(str_starts_with(strtolower($name), 'on') || preg_match('/javascript:|data:|url\s*\(/i', $value), RuntimeException::class, 'Generated SVG contains an unsafe attribute value.');

                if ($name === 'fill') {
                    $normalizedFill = $fillMap[strtolower($value)] ?? null;
                    throw_unless($normalizedFill !== null, RuntimeException::class, "Generated SVG uses forbidden fill color {$value}.");
                    $element->setAttribute('fill', $normalizedFill);
                    $usedFills[$normalizedFill] = true;
                }
            }

            if ($element->localName !== 'svg' && $element->localName !== 'g') {
                $fillSource = $element;
                while ($fillSource instanceof DOMElement && ! $fillSource->hasAttribute('fill')) {
                    $fillSource = $fillSource->parentNode;
                }
                $resolvedFill = $fillSource instanceof DOMElement ? $fillSource->getAttribute('fill') : '';
                throw_if($resolvedFill === '' || $resolvedFill === 'none', RuntimeException::class, 'Every generated SVG shape must have an allowed solid fill.');
            }
        }

        foreach ($document->getElementsByTagName('*') as $element) {
            foreach ($element->childNodes as $child) {
                throw_if($child->nodeType === XML_TEXT_NODE && trim($child->nodeValue ?? '') !== '', RuntimeException::class, 'Generated SVG contains forbidden text content.');
                throw_if(! in_array($child->nodeType, [XML_ELEMENT_NODE, XML_TEXT_NODE], true), RuntimeException::class, 'Generated SVG contains forbidden comments or processing instructions.');
            }
        }

        throw_if($shapeCount === 0, RuntimeException::class, 'Generated SVG contains no visible shapes.');
        throw_unless(isset($usedFills['#FFFFFF'], $usedFills['#FACC15']), RuntimeException::class, 'Generated SVG must contain both white people and an amber event element.');

        $root->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        $root->setAttribute('viewBox', '0 0 1024 1024');
        $root->setAttribute('width', '1024');
        $root->setAttribute('height', '1024');

        $safeSvg = $document->saveXML($root);
        throw_unless(is_string($safeSvg) && $safeSvg !== '', RuntimeException::class, 'Generated SVG could not be serialized.');

        return $safeSvg;
    }

    private function isInsufficientCreditError(RequestException $error, string $message): bool
    {
        if ($error->response?->status() === 402) {
            return true;
        }

        $haystack = Str::lower($message.' '.json_encode($error->response?->json()));

        return Str::contains($haystack, [
            'insufficient credit',
            'insufficient funds',
            'not enough credit',
            'credit balance',
        ]);
    }

    private function generateWithGemini(string $prompt, array $logContext): array
    {
        $model = (string) config('services.gemini_fallback.image_model');
        $url = rtrim((string) config('services.gemini_fallback.base_url'), '/')
            .'/models/'.rawurlencode($model).':generateContent';
        $reference = $this->silhouettePng();
        $compositionReference = $this->compositionReferenceImage();
        $styleReferences = $this->styleReferenceImages();
        $parts = [
            ['text' => $prompt],
            ['text' => 'The next PNG is the required character reference. Preserve its anonymous, featureless safety-sign silhouette proportions, recolor every person pure white, and adapt the pose to the situation. Do not copy its source background into the result.'],
            ['inlineData' => [
                'mimeType' => 'image/png',
                'data' => base64_encode($reference),
            ]],
            ['text' => 'The next JPEG is a composition reference only. Borrow its visual hierarchy, grounded interaction, dimensional construction, and spatial relationship between characters and the event object. Adapt the scale and distribution so at least one fully finished visual element reaches within 10 pixels of each of the four edges, including the bottom, while every person and object remains complete and uncropped. Do not copy its digits, timer, warning icon, appliance, laundry, exact viewpoint, literal content, hands-on-head pose, or one-person cast.'],
            ['inlineData' => [
                'mimeType' => 'image/jpeg',
                'data' => base64_encode($compositionReference),
            ]],
            ['text' => 'The next three images are style references only. Learn their bold pictogram readability and visual storytelling, but do not copy their compositions, text, frames, corners, backgrounds, white platforms, floating props, disconnected icons, or extra colors. Follow the prompt’s physical grounding and color-role rules instead.'],
        ];
        foreach ($styleReferences as $styleReference) {
            $parts[] = ['inlineData' => [
                'mimeType' => $styleReference['mime_type'],
                'data' => base64_encode($styleReference['data']),
            ]];
        }
        $parts[] = ['text' => 'Final quality-control pass: inspect the completed illustration at 200% before returning it. Re-render any soft, fuzzy, pixelated, jagged, smeared, or color-fringed boundary as a crisp precision-vector edge. Keep shapes bold and economical so the final 1024×1024 JPEG can be optimized below 100 KB without visible artifacts. Return only the finished image.'];

        Log::info('CMS artwork sending direct Gemini fallback request', $logContext + [
            'gemini_model' => $model,
            'gemini_url' => $url,
            'prompt_bytes' => strlen($prompt),
            'reference_png_bytes' => strlen($reference),
            'composition_reference_bytes' => strlen($compositionReference),
            'style_reference_count' => count($styleReferences),
        ]);

        $geminiResponse = Http::withHeaders([
            'x-goog-api-key' => config('services.gemini_fallback.key'),
        ])
            ->acceptJson()
            ->asJson()
            ->timeout(180)
            ->post($url, [
                'contents' => [[
                    'role' => 'user',
                    'parts' => $parts,
                ]],
                'generationConfig' => [
                    'responseModalities' => ['IMAGE'],
                    'imageConfig' => [
                        'aspectRatio' => '1:1',
                        'imageSize' => '2K',
                    ],
                ],
            ]);

        Log::info('CMS artwork direct Gemini fallback responded', $logContext + [
            'gemini_status' => $geminiResponse->status(),
            'gemini_content_type' => $geminiResponse->header('Content-Type'),
            'gemini_response_bytes' => strlen($geminiResponse->body()),
            'gemini_error' => $geminiResponse->successful()
                ? null
                : Str::limit((string) data_get($geminiResponse->json(), 'error.message', $geminiResponse->body()), 2000),
        ]);

        $body = $geminiResponse->throw()->json();
        $encoded = null;
        foreach ((array) data_get($body, 'candidates.0.content.parts', []) as $part) {
            $candidate = data_get($part, 'inlineData.data') ?? data_get($part, 'inline_data.data');
            if (is_string($candidate) && $candidate !== '') {
                $encoded = $candidate;
            }
        }

        abort_unless($encoded, 502, 'Gemini fallback returned no image data.');

        return ['data' => [['b64_json' => $encoded]]];
    }

    private function silhouetteReferences(): array
    {
        $png = $this->silhouettePng();
        $references = [[
            'type' => 'image_url',
            'image_url' => [
                'url' => 'data:image/png;base64,'.base64_encode($png),
            ],
        ]];

        $references[] = [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'data:image/jpeg;base64,'.base64_encode($this->compositionReferenceImage()),
            ],
        ];

        foreach ($this->styleReferenceImages() as $styleReference) {
            $references[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:'.$styleReference['mime_type'].';base64,'.base64_encode($styleReference['data']),
                ],
            ];
        }

        return $references;
    }

    private function compositionReferenceImage(): string
    {
        $path = resource_path('ai/composition-reference.jpg');
        abort_unless(is_file($path), 500, 'Primary composition reference is missing.');
        $jpeg = file_get_contents($path);
        abort_unless($jpeg !== false && $jpeg !== '', 500, 'Primary composition reference could not be read.');

        return $jpeg;
    }

    private function styleReferenceImages(): array
    {
        return array_map(function (array $reference): array {
            $filename = $reference['filename'];
            $path = resource_path('ai/'.$filename);
            abort_unless(is_file($path), 500, "Style reference {$filename} is missing.");
            $data = file_get_contents($path);
            abort_unless($data !== false && $data !== '', 500, "Style reference {$filename} could not be read.");

            return ['mime_type' => 'image/jpeg', 'data' => $this->cropStyleReference($data, $reference['crop'], $filename)];
        }, [
            ['filename' => 'style-reference-1.jpg', 'crop' => [20, 76, 260, 112]],
            ['filename' => 'style-reference-2.jpg', 'crop' => [1500, 760, 1200, 850]],
            ['filename' => 'style-reference-3.jpg', 'crop' => [240, 155, 195, 145]],
        ]);
    }

    private function cropStyleReference(string $jpeg, array $crop, string $filename): string
    {
        abort_unless(function_exists('imagecreatefromstring'), 500, 'GD is required to prepare style references.');
        $source = @imagecreatefromstring($jpeg);
        abort_unless($source !== false, 500, "Style reference {$filename} is not a valid image.");
        [$x, $y, $width, $height] = $crop;
        abort_if($x < 0 || $y < 0 || $x + $width > imagesx($source) || $y + $height > imagesy($source), 500, "Style reference {$filename} has invalid crop dimensions.");
        $cropped = imagecrop($source, ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]);
        imagedestroy($source);
        abort_unless($cropped !== false, 500, "Style reference {$filename} could not be cropped.");
        ob_start();
        imagejpeg($cropped, null, 92);
        $result = ob_get_clean();
        imagedestroy($cropped);
        abort_unless(is_string($result) && $result !== '', 500, "Style reference {$filename} could not be encoded.");

        return $result;
    }

    private function silhouettePng(): string
    {
        $path = resource_path('ai/main-silhouette.png');
        abort_unless(is_file($path), 500, 'Main silhouette reference PNG is missing.');
        $png = file_get_contents($path);
        abort_unless($png !== false && $png !== '', 500, 'Main silhouette reference PNG could not be read.');

        return $png;
    }

    private function silhouetteSvg(): string
    {
        $path = resource_path('ai/main-silhouette.svg');
        abort_unless(is_file($path), 500, 'Main silhouette reference SVG is missing.');
        $svg = file_get_contents($path);
        abort_unless($svg !== false && $svg !== '', 500, 'Main silhouette reference SVG could not be read.');

        return $svg;
    }

    private function convertGeneratedImageToWebp(string $image): string
    {
        abort_unless(function_exists('imagecreatefromstring') && function_exists('imagewebp'), 502, 'GD WebP support is unavailable.');
        $source = @imagecreatefromstring($image);
        abort_unless($source !== false, 502, 'Generated image could not be decoded.');
        $source = $this->removeDetectedBrightFrame($source);
        $source = $this->enforceGeneratedPaletteAndSquareCorners($source);

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $webpCanvas = imagecreatetruecolor($sourceWidth, $sourceHeight);
        $black = imagecolorallocate($webpCanvas, 0, 0, 0);
        imagefill($webpCanvas, 0, 0, $black);
        imagealphablending($webpCanvas, true);
        imagecopy($webpCanvas, $source, 0, 0, 0, 0, $sourceWidth, $sourceHeight);

        $result = $this->encodeCardWebp($webpCanvas);
        imagedestroy($source);
        imagedestroy($webpCanvas);

        abort_unless(is_string($result) && $result !== '', 502, 'Generated image could not be encoded as WebP.');

        return $result;
    }

    private function convertImageToWebp(string $image, int $size = 768, int $status = 422): string
    {
        abort_unless(function_exists('imagecreatefromstring') && function_exists('imagewebp'), $status, 'GD WebP support is unavailable.');
        $source = @imagecreatefromstring($image);
        abort_unless($source !== false, $status, 'The artwork is not a valid image.');

        $width = imagesx($source);
        $height = imagesy($source);
        abort_if($width < 256 || $height < 256 || $width !== $height, $status, 'The artwork must be a square image of at least 256 pixels.');

        if ($width !== $size) {
            $resized = imagecreatetruecolor($size, $size);
            $black = imagecolorallocate($resized, 0, 0, 0);
            imagefill($resized, 0, 0, $black);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $size, $size, $width, $height);
            imagedestroy($source);
            $source = $resized;
        }

        $source = $this->enforceGeneratedPaletteAndSquareCorners($source);
        $result = '';
        foreach ([90, 86, 82, 78, 74, 70, 66, 62, 58, 54] as $quality) {
            ob_start();
            imagewebp($source, null, $quality);
            $encoded = ob_get_clean();
            abort_unless(is_string($encoded) && $encoded !== '', $status, 'Card artwork could not be encoded as WebP.');
            $result = $encoded;
            if (strlen($result) <= self::MAX_GENERATED_JPEG_BYTES) break;
        }
        imagedestroy($source);

        abort_unless($result !== '', $status, 'The artwork could not be encoded as WebP.');

        return $result;
    }

    private function encodeCardJpeg(\GdImage $source): string
    {
        imageinterlace($source, true);
        $result = '';

        foreach ([92, 88, 84, 80, 76, 72, 68, 64, 60] as $quality) {
            ob_start();
            imagejpeg($source, null, $quality);
            $encoded = ob_get_clean();
            abort_unless(is_string($encoded) && $encoded !== '', 500, 'Card artwork could not be encoded as JPEG.');
            $result = $encoded;

            if (strlen($result) <= self::MAX_GENERATED_JPEG_BYTES) {
                break;
            }
        }

        return $result;
    }

    private function encodeCardWebp(\GdImage $source): string
    {
        abort_unless(function_exists('imagewebp'), 500, 'GD WebP support is unavailable.');
        $result = '';

        foreach ([90, 86, 82, 78, 74, 70, 66, 62, 58, 54] as $quality) {
            ob_start();
            imagewebp($source, null, $quality);
            $encoded = ob_get_clean();
            abort_unless(is_string($encoded) && $encoded !== '', 500, 'Card artwork could not be encoded as WebP.');
            $result = $encoded;

            if (strlen($result) <= self::MAX_GENERATED_JPEG_BYTES) {
                break;
            }
        }

        return $result;
    }

    private function enhanceCardArtwork(\GdImage $source): \GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $workingSize = 2048;
        $working = imagecreatetruecolor($workingSize, $workingSize);
        $black = imagecolorallocate($working, 0, 0, 0);
        imagefill($working, 0, 0, $black);
        imagecopyresampled($working, $source, 0, 0, 0, 0, $workingSize, $workingSize, $sourceWidth, $sourceHeight);

        // A gentle supersampled cleanup removes JPEG/crop stair-stepping without
        // inventing details or changing the generated composition.
        imagefilter($working, IMG_FILTER_SMOOTH, 1);
        imagefilter($working, IMG_FILTER_CONTRAST, -3);
        imageconvolution($working, [
            [0, -0.12, 0],
            [-0.12, 1.48, -0.12],
            [0, -0.12, 0],
        ], 1, 0);

        $result = imagecreatetruecolor(1024, 1024);
        $resultBlack = imagecolorallocate($result, 0, 0, 0);
        imagefill($result, 0, 0, $resultBlack);
        imagecopyresampled($result, $working, 0, 0, 0, 0, 1024, 1024, $workingSize, $workingSize);
        imagedestroy($working);

        return $result;
    }

    private function normalizeEnhancedArtworkColors(\GdImage $source): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $black = imagecolorallocate($source, 0, 0, 0);
        $white = imagecolorallocate($source, 255, 255, 255);
        $miseryYellow = imagecolorallocate($source, 250, 204, 21);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($source, $x, $y);
                $red = ($color >> 16) & 0xFF;
                $green = ($color >> 8) & 0xFF;
                $blue = $color & 0xFF;
                if ($red <= 24 && $green <= 24 && $blue <= 24) {
                    imagesetpixel($source, $x, $y, $black);
                    continue;
                }
                if ($red >= 238 && $green >= 238 && $blue >= 238) {
                    imagesetpixel($source, $x, $y, $white);
                    continue;
                }
                $yellowDistance = ($red - 250) ** 2 + ($green - 204) ** 2 + ($blue - 21) ** 2;
                if ($yellowDistance <= 70 ** 2) {
                    imagesetpixel($source, $x, $y, $miseryYellow);
                }
            }
        }

        return $source;
    }

    private function enforceGeneratedPaletteAndSquareCorners(\GdImage $source): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $normalized = imagecreatetruecolor($width, $height);
        $palette = [
            [0, 0, 0, imagecolorallocate($normalized, 0, 0, 0)],
            [255, 255, 255, imagecolorallocate($normalized, 255, 255, 255)],
            [250, 204, 21, imagecolorallocate($normalized, 250, 204, 21)],
        ];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($source, $x, $y);
                $red = ($color >> 16) & 0xFF;
                $green = ($color >> 8) & 0xFF;
                $blue = $color & 0xFF;
                $nearest = $palette[0];
                $nearestDistance = PHP_INT_MAX;
                foreach ($palette as $candidate) {
                    $distance = ($red - $candidate[0]) ** 2 + ($green - $candidate[1]) ** 2 + ($blue - $candidate[2]) ** 2;
                    if ($distance < $nearestDistance) {
                        $nearest = $candidate;
                        $nearestDistance = $distance;
                    }
                }
                imagesetpixel($normalized, $x, $y, $nearest[3]);
            }
        }
        imagedestroy($source);

        $black = $palette[0][3];
        $white = $palette[1][3];
        foreach ([[0, 0], [$width - 1, 0], [0, $height - 1], [$width - 1, $height - 1]] as [$x, $y]) {
            if (imagecolorat($normalized, $x, $y) === $white) {
                imagefill($normalized, $x, $y, $black);
            }
        }

        return $normalized;
    }

    private function removeDetectedBrightFrame(\GdImage $source): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $xLimit = max(1, (int) floor($width * 0.15));
        $yLimit = max(1, (int) floor($height * 0.15));
        $isBright = static function (int $color): bool {
            return (($color >> 16) & 0xFF) >= 220
                && (($color >> 8) & 0xFF) >= 220
                && ($color & 0xFF) >= 220;
        };
        $brightRow = static function (int $y) use ($source, $width, $isBright): bool {
            $bright = 0;
            for ($x = 0; $x < $width; $x += 2) {
                $bright += $isBright(imagecolorat($source, $x, $y)) ? 1 : 0;
            }

            return $bright / max(1, (int) ceil($width / 2)) >= 0.72;
        };
        $brightColumn = static function (int $x) use ($source, $height, $isBright): bool {
            $bright = 0;
            for ($y = 0; $y < $height; $y += 2) {
                $bright += $isBright(imagecolorat($source, $x, $y)) ? 1 : 0;
            }

            return $bright / max(1, (int) ceil($height / 2)) >= 0.72;
        };

        $top = $bottom = $left = $right = null;
        for ($y = 0; $y < $yLimit; $y++) {
            if ($brightRow($y)) {
                $top = $y;
            }
            if ($brightRow($height - 1 - $y)) {
                $bottom = $height - 1 - $y;
            }
        }
        for ($x = 0; $x < $xLimit; $x++) {
            if ($brightColumn($x)) {
                $left = $x;
            }
            if ($brightColumn($width - 1 - $x)) {
                $right = $width - 1 - $x;
            }
        }

        if ($top === null || $bottom === null || $left === null || $right === null) {
            return $source;
        }

        $padding = max(2, (int) round(min($width, $height) * 0.01));
        $cropX = $left + $padding;
        $cropY = $top + $padding;
        $cropWidth = $right - $cropX - $padding;
        $cropHeight = $bottom - $cropY - $padding;
        if ($cropWidth < $width * 0.6 || $cropHeight < $height * 0.6) {
            return $source;
        }

        $result = imagecreatetruecolor($width, $height);
        imagecopyresampled($result, $source, 0, 0, $cropX, $cropY, $width, $height, $cropWidth, $cropHeight);
        imagedestroy($source);

        return $result;
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'title_bs' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:1000'],
            'subtitle_bs' => ['nullable', 'string', 'max:1000'],
            'score' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'boolean'],
            'stack_id' => ['required', 'exists:stacks,id'],
            'image' => ['nullable', 'string', 'max:2048'],
            'image_upload' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:8192'],
        ]);
        $stack = Stack::findOrFail($data['stack_id']);
        $data['deck'] = $stack->slug;
        unset($data['image_upload']);
        $data['image'] = trim((string) ($data['image'] ?? '')) ?: '0';
        $data['status'] = $request->has('status') ? $request->boolean('status') : true;
        $data['title_bs'] = trim((string) ($data['title_bs'] ?? '')) ?: null;
        $data['subtitle_bs'] = trim((string) ($data['subtitle_bs'] ?? '')) ?: null;

        return $data;
    }

    private function cardsReturnUrl(Request $request): string
    {
        $default = route('cms.cards.index');
        $candidate = $request->string('return')->toString();
        if ($candidate === '') {
            return $default;
        }

        $target = parse_url($candidate);
        $index = parse_url($default);
        if ($target === false
            || ($target['path'] ?? '') !== ($index['path'] ?? '')
            || (isset($target['host']) && strcasecmp($target['host'], (string) ($index['host'] ?? '')) !== 0)
            || (isset($target['scheme']) && strcasecmp($target['scheme'], (string) ($index['scheme'] ?? '')) !== 0)
            || (isset($target['port']) && $target['port'] !== ($index['port'] ?? null))) {
            return $default;
        }

        return $candidate;
    }

    private function cardEditUrl(Request $request, Card $card): string
    {
        $parameters = ['card' => $card];
        if ($request->string('return')->isNotEmpty()) {
            $parameters['return'] = $this->cardsReturnUrl($request);
        }

        return route('cms.cards.edit', $parameters);
    }

    private function storeUpload(Request $request, Card $card): void
    {
        if (! $request->hasFile('image_upload')) {
            return;
        }
        $this->deleteManagedImage($card->image);
        $upload = $request->file('image_upload');
        $extension = Str::lower($upload->guessExtension() ?: $upload->getClientOriginalExtension());
        $formatFolder = match ($extension) {
            'webp' => 'webp',
            'jpg', 'jpeg' => 'jpg',
            default => 'other',
        };
        $path = $upload->store('cards/uploads/'.$formatFolder, 'public');
        $card->update(['image' => $path, 'artwork_enhanced' => false]);
    }

    private function deleteManagedImage(?string $path): void
    {
        if ($path && ! Str::startsWith($path, ['http://', 'https://']) && $path !== '0') {
            Storage::disk('public')->delete(preg_replace('#^storage/#', '', ltrim($path, '/')));
        }
    }

    private function deleteManagedImageIfUnused(?string $path): void
    {
        if (! $path || Card::query()->where('image', $path)->exists()) {
            return;
        }

        $this->deleteManagedImage($path);
    }

    private function cardImageUrl(?string $path): ?string
    {
        if (! $path || $path === '0') {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : url('/card-images/'.preg_replace('#^storage/#', '', ltrim($path, '/')));
    }

    private function deleteManagedSvg(?string $path): void
    {
        if ($path && Str::startsWith($path, 'cards/generated-svg/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
