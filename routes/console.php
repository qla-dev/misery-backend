<?php

use App\Services\GameCleanupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('games:cleanup', function (GameCleanupService $cleanup) {
    $result = $cleanup->cleanup();
    $this->info("Deleted {$result['lobby_games_deleted']} stale lobby games and {$result['started_games_deleted']} inactive started games.");

    return self::SUCCESS;
})->purpose('Delete stale lobby games and started games without recent moves');

Schedule::command('games:cleanup')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('rulebook:generate-illustration {--force : Replace the existing generated asset}', function () {
    $key = config('services.openrouter.key');
    if (! filled($key)) {
        $this->error('OPENROUTER_API_KEY is not configured.');

        return self::FAILURE;
    }

    $destination = base_path('../frontend/assets/images/rulebook-misery-spectrum.jpg');
    if (File::exists($destination) && ! $this->option('force')) {
        $this->error('The rulebook illustration already exists. Use --force to replace it.');

        return self::FAILURE;
    }

    $prompt = implode("\n", [
        'Use case: infographic-diagram',
        'Asset type: square spot illustration for the Misery Meter game rulebook',
        'Primary request: recreate a simple analog misery gauge like a humorous printed rulebook icon.',
        'Composition: one large white fan-shaped analog meter with a thick black outline fills almost the entire square. Across the upper white face, print exactly three black labels from left to right: "BAD", "AWFUL", "WTF". A solid black circular hub sits low in the center with one thick black needle pointing upward toward the right-hand "WTF" end. The complete gauge must remain visible and uncropped.',
        'Typography: the three labels must be legible, uppercase, narrow hand-lettered condensed capitals. Spell them exactly B-A-D, A-W-F-U-L, W-T-F. No other text.',
        'Style: polished but playful printed-rulebook pictogram, precision-vector appearance, clean solid geometric shapes, instantly readable at small size.',
        'Palette: use exactly pure black #000000, pure white #FFFFFF, and primary amber #FACC15. Flat solid fills only.',
        'Background: solid amber #FACC15 across the complete square canvas.',
        'Do not include people, vehicles, lightning, scenery, extra icons, tick numbers, or decorative objects. The gauge is the only subject.',
        'Meter face: solid white. Meter outline, labels, circular hub, and needle: solid black.',
        'Constraints: balanced square composition, generous safe margin, complete uncropped shapes, crisp antialiased edges, no gradients, no shadows, no texture, no transparency, no border, no frame.',
        'Text constraint: include only the three required labels "BAD", "AWFUL", and "WTF". No numbers, logos, watermark, caption, or other text.',
        'Output only the finished illustration.',
    ]);

    $this->info('Generating rulebook illustration with OpenRouter...');
    $response = Http::withToken($key)
        ->withHeaders(array_filter([
            'HTTP-Referer' => config('services.openrouter.http_referer'),
            'X-Title' => config('services.openrouter.title'),
        ]))
        ->acceptJson()
        ->asJson()
        ->timeout(180)
        ->post(rtrim((string) config('services.openrouter.base_url'), '/').'/images', [
            'model' => config('services.openrouter.image_model'),
            'prompt' => $prompt,
            'size' => '1024x1024',
            'quality' => 'high',
            'background' => 'opaque',
            'output_format' => 'jpeg',
        ])
        ->throw()
        ->json();

    $encoded = data_get($response, 'data.0.b64_json');
    if (is_string($encoded) && str_contains($encoded, ',')) {
        $encoded = substr($encoded, strpos($encoded, ',') + 1);
    }
    $image = is_string($encoded) ? base64_decode($encoded, true) : false;
    if ($image === false) {
        $url = data_get($response, 'data.0.url');
        if (is_string($url) && $url !== '') {
            $image = Http::timeout(120)->get($url)->throw()->body();
        }
    }
    if (! is_string($image) || $image === '' || @getimagesizefromstring($image) === false) {
        throw new RuntimeException('OpenRouter returned no valid image data.');
    }

    $source = @imagecreatefromstring($image);
    if ($source === false) {
        throw new RuntimeException('The generated image could not be decoded.');
    }
    ob_start();
    imagejpeg($source, null, 92);
    $jpeg = ob_get_clean();
    imagedestroy($source);
    if (! is_string($jpeg) || $jpeg === '') {
        throw new RuntimeException('The generated image could not be converted to JPEG.');
    }

    File::ensureDirectoryExists(dirname($destination));
    File::put($destination, $jpeg);
    $this->info('Saved: '.$destination);

    return self::SUCCESS;
})->purpose('Generate the Misery Meter rulebook spectrum illustration through OpenRouter');
