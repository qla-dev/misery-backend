<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Stack;
use DOMDocument;
use DOMElement;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CardController extends Controller
{
    private const MAX_GENERATED_PNG_BYTES = 102400;

    public function index(Request $request)
    {
        $cards = Card::with('stack')->when($request->string('q')->toString(), function ($query, $search) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('subtitle', 'like', "%{$search}%"));
        })->orderBy('score')->paginate(25)->withQueryString();

        return view('cms.cards.index', compact('cards'));
    }

    public function create()
    {
        return view('cms.cards.form', ['card' => new Card, 'stacks' => Stack::orderBy('name')->get()]);
    }

    public function edit(Card $card)
    {
        return view('cms.cards.form', compact('card') + ['stacks' => Stack::orderBy('name')->get()]);
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
            $this->deleteManagedImage($previousImage);
        }
        $this->storeUpload($request, $card);

        return back()->with('success', 'Card saved.');
    }

    public function destroy(Card $card)
    {
        $this->deleteManagedImage($card->image);
        $this->deleteManagedSvg($card->svg_img);
        $card->delete();

        return redirect()->route('cms.cards.index')->with('success', 'Card deleted.');
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

            return back()->withErrors(['generation' => "OPENROUTER_API_KEY is not configured on the backend. [Generation: {$generationId}]"]);
        }
        $prompt = implode("\n", [
            'Use case: stylized-concept',
            'Asset type: transparent illustration for a Misery Index game card',
            "Situation: {$card->title}",
            $card->subtitle ? "Context: {$card->subtitle}" : '',
            'Create one bold, instantly readable pictogram scene that clearly explains the unfortunate situation.',
            'Be highly creative: invent a clever, surprising visual metaphor for this specific situation while keeping the scene instantly understandable at card size. Avoid generic or repetitive compositions.',
            'Scene planning: before composing the image, silently determine whether this specific situation logically needs one person, two people, or three people. Use the smallest cast that fully communicates the event, but do not force every situation into a one-person scene. Interpersonal events, collisions, assistance, conflict, crowds, shared activities, or cause-and-effect interactions may require two or three clearly posed people.',
            'Multi-person clarity: when two or three people are needed, give each person a distinct pose and clear role in the action. Keep them visually separated enough to read at small size while preserving one unified scene.',
            'Style: clever editorial safety-sign illustration built entirely from simple, solid, flat-filled geometric people and objects.',
            'Mandatory fill style: every visible person, object, and detail must be a solid filled silhouette or filled shape. Do not use outline-only, stroke-only, line-art, wireframe, hollow, contour, border-style, or unfilled elements. Do not rely on borders to define any object.',
            'Palette roles: use pure white #FFFFFF for people, primary amber #FACC15 for the event or hazard, and only a very limited range of neutral gray filled shapes for environmental grounding. Suggested environment grays are dark #262626 and medium #525252. Never use gray for the main person or the main event element, and use no other colors.',
            'Mandatory color balance: white and amber must both be clearly visible and important to the scene. Include at least one large, distinct, solid-white person occupying a meaningful part of the illustration; tiny white accents, outlines, or highlights do not count. Use amber for at least one substantial event or hazard element. Never return an amber-only image.',
            'People: depict humans only as anonymous, featureless safety-sign silhouettes with simple circular heads. Faces must be completely blank: no eyes, pupils, eyebrows, eyelashes, nose, nostrils, mouth, lips, teeth, ears, hair, facial hair, or facial expression.',
            'Mandatory subject color: the main human silhouette must ALWAYS be solid pure white #FFFFFF. Never make the main silhouette amber, gray, black, transparent, outlined, or any other color.',
            'Mandatory event color: the event-specific element that causes or represents the misery must be solid primary amber #FACC15. Supporting hazard or action elements should also use amber when useful.',
            'Reference image: use the attached main-silhouette SVG as the required visual reference for the anonymous white human figure, including its simple safety-sign character and proportions. Adapt its pose creatively to the situation; do not copy the reference as a static logo.',
            'Environmental grounding: people and objects must not look like they are floating. Whenever spatially appropriate, add a minimal flat-filled environment such as a road, lane marking, sidewalk, floor, curb, platform, wall edge, room surface, slope, or other scene-specific ground plane in the permitted gray shades. The environment should support the action without overpowering it.',
            'Environment style limit: keep the environment extremely simple, clean, geometric, and vector-like, using only a few large flat-filled shapes. It must use the exact same safety-sign visual language as the people and event elements. Do not add realistic scenery, textures, tiny details, complex perspective, decorative clutter, or a separate background scene. The environment must feel like a natural part of the single pictogram, remain visually subordinate, and never compete with the main action.',
            'Composition: centered single scene, generous transparent padding, readable at small mobile-card size. Keep every person and important object visibly connected to the ground, environment, or another object.',
            'Background: fully transparent alpha. Environmental grounding must remain isolated filled shapes, never a full rectangular background.',
            'File requirement: return a highly optimized PNG whose encoded file size is no larger than 100 KB (102,400 bytes). Keep shapes simple and the palette limited so it compresses efficiently.',
            'Constraints: PNG; crisp edges; no text, letters, numbers, logos, watermark, card frame, border, gradients, lighting effects, or shadows. Apart from white, amber, and the explicitly permitted neutral environment grays, use no other colors.',
        ]);

        $providerUsed = 'openrouter';
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
                    'quality' => 'medium',
                    'background' => 'transparent',
                    'output_format' => 'png',
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
        } catch (RequestException $error) {
            $message = (string) data_get($error->response?->json(), 'error.message', $error->getMessage());
            Log::error('CMS artwork generation provider request failed', $logContext + [
                'exception' => $error,
                'provider_status' => $error->response?->status(),
                'provider_error' => Str::limit($message, 2000),
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            if (! $this->isInsufficientCreditError($error, $message)) {
                return back()->withErrors(['generation' => $message." [Generation: {$generationId}]"]);
            }

            if (! config('services.gemini_fallback.key')) {
                Log::warning('CMS artwork Gemini fallback unavailable: API key missing', $logContext);

                return back()->withErrors([
                    'generation' => $message." Gemini fallback is not configured; add FALLBACK_GEMINI_API_KEY. [Generation: {$generationId}]",
                ]);
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

                return back()->withErrors([
                    'generation' => 'OpenRouter has insufficient credit and Gemini fallback failed: '.($fallbackMessage ?: 'Unknown Gemini error')." [Generation: {$generationId}]",
                ]);
            }
        } catch (Throwable $error) {
            Log::error('CMS artwork generation could not start', $logContext + [
                'exception' => $error,
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            $message = $error->getMessage() ?: 'Artwork generation could not be started.';

            return back()->withErrors(['generation' => $message." [Generation: {$generationId}]"]);
        }

        try {
            $encoded = data_get($response, 'data.0.b64_json');
            Log::info('CMS artwork generation parsing image data', $logContext + [
                'response_keys' => is_array($response) ? array_keys($response) : [],
                'encoded_image_present' => filled($encoded),
                'encoded_image_bytes' => is_string($encoded) ? strlen($encoded) : 0,
            ]);
            abort_unless($encoded, 502, 'Image provider returned no image data.');
            $path = 'cards/generated/card-'.$card->id.'-'.now()->format('YmdHis').'.png';
            $png = base64_decode($encoded, true);
            abort_unless($png !== false, 502, 'Image provider returned invalid image data.');
            $originalBytes = strlen($png);
            $png = $this->optimizeGeneratedPng($png);
            Log::info('CMS artwork generation optimized image', $logContext + [
                'original_png_bytes' => $originalBytes,
                'optimized_png_bytes' => strlen($png),
                'storage_path' => $path,
            ]);
            abort_if(strlen($png) > self::MAX_GENERATED_PNG_BYTES, 502, 'Generated PNG is larger than 100 KB after optimization. Please generate it again.');
            Storage::disk('public')->put($path, $png);
            abort_unless(Storage::disk('public')->exists($path), 500, 'Generated PNG was not found after writing it to storage.');
            $this->deleteManagedImage($card->image);
            $card->update(['image' => $path]);
        } catch (Throwable $error) {
            Log::error('CMS artwork generation image processing failed', $logContext + [
                'exception' => $error,
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            $message = $error->getMessage() ?: 'Generated artwork could not be saved.';

            return back()->withErrors(['generation' => $message." [Generation: {$generationId}]"]);
        }

        Log::info('CMS artwork generation completed', $logContext + [
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'provider' => $providerUsed,
            'storage_path' => $path,
        ]);

        return back()
            ->with('success', 'Transparent artwork generated and saved via '.($providerUsed === 'gemini-fallback' ? 'direct Gemini fallback' : 'OpenRouter').". [Generation: {$generationId}]")
            ->with('generated_prompt', $prompt);
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
                    'systemInstruction' => [
                        'parts' => [[
                            'text' => 'You create safe, minimal SVG pictograms. Return only complete SVG markup with no Markdown or explanation.',
                        ]],
                    ],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => $prompt]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.85,
                    ],
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
        $svg = $this->silhouetteSvg();

        Log::info('CMS artwork sending direct Gemini fallback request', $logContext + [
            'gemini_model' => $model,
            'gemini_url' => $url,
            'prompt_bytes' => strlen($prompt),
            'reference_svg_bytes' => strlen($svg),
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
                    'parts' => [
                        ['text' => $prompt],
                        ['text' => "Required main-silhouette visual reference, supplied as SVG markup. Preserve its anonymous safety-sign proportions and color roles while adapting the pose:\n{$svg}"],
                    ],
                ]],
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
        $svg = $this->silhouetteSvg();

        return [[
            'type' => 'image_url',
            'image_url' => [
                'url' => 'data:image/svg+xml;base64,'.base64_encode($svg),
            ],
        ]];
    }

    private function silhouetteSvg(): string
    {
        $path = resource_path('ai/main-silhouette.svg');
        abort_unless(is_file($path), 500, 'Main silhouette reference SVG is missing.');
        $svg = file_get_contents($path);
        abort_unless($svg !== false && $svg !== '', 500, 'Main silhouette reference SVG could not be read.');

        return $svg;
    }

    private function optimizeGeneratedPng(string $png): string
    {
        if (strlen($png) <= self::MAX_GENERATED_PNG_BYTES) {
            return $png;
        }
        abort_unless(function_exists('imagecreatefromstring'), 502, 'Generated PNG exceeds 100 KB and GD is unavailable for optimization.');
        $source = @imagecreatefromstring($png);
        abort_unless($source !== false, 502, 'Generated PNG exceeds 100 KB and could not be optimized.');

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, 512 / max($sourceWidth, $sourceHeight));
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $optimized = imagecreatetruecolor($width, $height);
        imagealphablending($optimized, false);
        imagesavealpha($optimized, true);
        $transparent = imagecolorallocatealpha($optimized, 0, 0, 0, 127);
        imagefill($optimized, 0, 0, $transparent);
        imagecopyresampled($optimized, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
        imagetruecolortopalette($optimized, true, 16);

        ob_start();
        imagepng($optimized, null, 9);
        $result = ob_get_clean();
        imagedestroy($source);
        imagedestroy($optimized);

        return is_string($result) && $result !== '' ? $result : $png;
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:1000'],
            'score' => ['required', 'numeric', 'min:0', 'max:100'],
            'stack_id' => ['required', 'exists:stacks,id'],
            'image' => ['nullable', 'string', 'max:2048'],
            'image_upload' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:8192'],
        ]);
        $stack = Stack::findOrFail($data['stack_id']);
        $data['deck'] = $stack->slug;
        unset($data['image_upload']);
        $data['image'] = trim((string) ($data['image'] ?? '')) ?: '0';

        return $data;
    }

    private function storeUpload(Request $request, Card $card): void
    {
        if (! $request->hasFile('image_upload')) {
            return;
        }
        $this->deleteManagedImage($card->image);
        $path = $request->file('image_upload')->store('cards/uploads', 'public');
        $card->update(['image' => $path]);
    }

    private function deleteManagedImage(?string $path): void
    {
        if ($path && ! Str::startsWith($path, ['http://', 'https://']) && $path !== '0') {
            Storage::disk('public')->delete(preg_replace('#^storage/#', '', ltrim($path, '/')));
        }
    }

    private function deleteManagedSvg(?string $path): void
    {
        if ($path && Str::startsWith($path, 'cards/generated-svg/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
