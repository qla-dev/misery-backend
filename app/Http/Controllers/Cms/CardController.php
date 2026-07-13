<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Stack;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $card->delete();

        return redirect()->route('cms.cards.index')->with('success', 'Card deleted.');
    }

    public function generate(Card $card)
    {
        abort_unless(config('services.openrouter.key'), 422, 'OPENROUTER_API_KEY is not configured on the backend.');
        $prompt = implode("\n", [
            'Use case: stylized-concept',
            'Asset type: transparent illustration for a Misery Index game card',
            "Situation: {$card->title}",
            $card->subtitle ? "Context: {$card->subtitle}" : '',
            'Create one bold, instantly readable pictogram scene that clearly explains the unfortunate situation.',
            'Be highly creative: invent a clever, surprising visual metaphor for this specific situation while keeping the scene instantly understandable at card size. Avoid generic or repetitive compositions.',
            'Style: clever editorial safety-sign illustration built entirely from simple, solid, flat-filled geometric people and objects.',
            'Mandatory fill style: every visible person, object, and detail must be a solid filled silhouette or filled shape. Do not use outline-only, stroke-only, line-art, wireframe, hollow, contour, border-style, or unfilled elements. Do not rely on borders to define any object.',
            'Palette: use exactly two visible colors only: pure white #FFFFFF and primary amber #FACC15.',
            'Mandatory color balance: BOTH colors must be clearly visible and important to the scene. Include at least one large, distinct, solid-white object or person occupying a meaningful part of the illustration; tiny white accents, outlines, or highlights do not count. Use amber for at least one other substantial object or person. Never return an amber-only image.',
            'People: depict humans only as anonymous, featureless safety-sign silhouettes with simple circular heads. Faces must be completely blank: no eyes, pupils, eyebrows, eyelashes, nose, nostrils, mouth, lips, teeth, ears, hair, facial hair, or facial expression.',
            'Mandatory subject color: the main human silhouette must ALWAYS be solid pure white #FFFFFF. Never make the main silhouette amber, gray, black, transparent, outlined, or any other color.',
            'Mandatory event color: the event-specific element that causes or represents the misery must be solid primary amber #FACC15. Supporting hazard or action elements should also use amber when useful.',
            'Reference image: use the attached main-silhouette SVG as the required visual reference for the anonymous white human figure, including its simple safety-sign character and proportions. Adapt its pose creatively to the situation; do not copy the reference as a static logo.',
            'Composition: centered single scene, generous transparent padding, readable at small mobile-card size.',
            'Background: fully transparent alpha.',
            'File requirement: return a highly optimized PNG whose encoded file size is no larger than 100 KB (102,400 bytes). Keep shapes simple and the palette limited so it compresses efficiently.',
            'Constraints: PNG; crisp edges; no text, letters, numbers, logos, watermark, card frame, border, gradients, shadows, gray, black, or any third color.',
        ]);

        try {
            $headers = array_filter([
                'HTTP-Referer' => config('services.openrouter.http_referer'),
                'X-Title' => config('services.openrouter.title'),
            ]);
            $response = Http::withToken(config('services.openrouter.key'))
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
                    'input_references' => $this->silhouetteReferences(),
                ])->throw()->json();
        } catch (RequestException $error) {
            return back()->withErrors(['generation' => data_get($error->response?->json(), 'error.message', $error->getMessage())]);
        }

        $encoded = data_get($response, 'data.0.b64_json');
        abort_unless($encoded, 502, 'Image provider returned no image data.');
        $path = 'cards/generated/card-'.$card->id.'-'.now()->format('YmdHis').'.png';
        $png = base64_decode($encoded, true);
        abort_unless($png !== false, 502, 'Image provider returned invalid image data.');
        $png = $this->optimizeGeneratedPng($png);
        abort_if(strlen($png) > self::MAX_GENERATED_PNG_BYTES, 502, 'Generated PNG is larger than 100 KB after optimization. Please generate it again.');
        Storage::disk('public')->put($path, $png);
        $this->deleteManagedImage($card->image);
        $card->update(['image' => $path]);

        return back()->with('success', 'Transparent artwork generated and saved.')->with('generated_prompt', $prompt);
    }

    private function silhouetteReferences(): array
    {
        $path = base_path('../frontend/assets/images/i-letter.svg');
        abort_unless(is_file($path), 500, 'Main silhouette reference SVG is missing.');
        $svg = file_get_contents($path);
        abort_unless($svg !== false && $svg !== '', 500, 'Main silhouette reference SVG could not be read.');

        return [[
            'type' => 'image_url',
            'image_url' => [
                'url' => 'data:image/svg+xml;base64,'.base64_encode($svg),
            ],
        ]];
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
}
