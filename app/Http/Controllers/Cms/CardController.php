<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Stack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Client\RequestException;

class CardController extends Controller
{
    public function index(Request $request)
    {
        $cards = Card::with('stack')->when($request->string('q')->toString(), function ($query, $search) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('subtitle', 'like', "%{$search}%"));
        })->orderBy('score')->paginate(25)->withQueryString();
        return view('cms.cards.index', compact('cards'));
    }

    public function create() { return view('cms.cards.form', ['card' => new Card(), 'stacks' => Stack::orderBy('name')->get()]); }
    public function edit(Card $card) { return view('cms.cards.form', compact('card') + ['stacks' => Stack::orderBy('name')->get()]); }

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
            'Style: clever editorial safety-sign illustration, simple geometric people and objects, thick clean shapes.',
            'Palette: use exactly two visible colors only: pure white #FFFFFF and primary amber #FACC15.',
            'Composition: centered single scene, generous transparent padding, readable at small mobile-card size.',
            'Background: fully transparent alpha.',
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
            ])->throw()->json();
        } catch (RequestException $error) {
            return back()->withErrors(['generation' => data_get($error->response?->json(), 'error.message', $error->getMessage())]);
        }

        $encoded = data_get($response, 'data.0.b64_json');
        abort_unless($encoded, 502, 'Image provider returned no image data.');
        $path = 'cards/generated/card-'.$card->id.'-'.now()->format('YmdHis').'.png';
        $png = base64_decode($encoded, true);
        abort_unless($png !== false, 502, 'Image provider returned invalid image data.');
        Storage::disk('public')->put($path, $png);
        $this->deleteManagedImage($card->image);
        $card->update(['image' => $path]);
        return back()->with('success', 'Transparent artwork generated and saved.')->with('generated_prompt', $prompt);
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
        if (! $request->hasFile('image_upload')) return;
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
