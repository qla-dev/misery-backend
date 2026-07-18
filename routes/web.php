<?php

use App\Http\Controllers\Cms\CardController as CmsCardController;
use App\Http\Controllers\Cms\CardGeneratorController as CmsCardGeneratorController;
use App\Http\Controllers\Cms\ContentGeneratorController as CmsContentGeneratorController;
use App\Http\Controllers\Cms\ScreenshotMakerController as CmsScreenshotMakerController;
use App\Http\Controllers\Cms\StackController as CmsStackController;
use App\Http\Resources\GameResource;
use App\Models\Game;
use App\Models\Stack;
use App\Services\RealtimeTransportAllocator;
use App\Services\GameCleanupService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/.well-known/apple-app-site-association', function () {
    return response()->json([
        'applinks' => ['details' => [[
            'appIDs' => [config('game.ios_app_team_id').'.misery.qla.dev'],
            'components' => [['/' => '/code/*']],
        ]]],
    ])->header('Content-Type', 'application/json');
});
Route::get('/.well-known/assetlinks.json', function () {
    return response()->json([[
        'relation' => ['delegate_permission/common.handle_all_urls'],
        'target' => [
            'namespace' => 'android_app',
            'package_name' => 'misery.qla.dev',
            'sha256_cert_fingerprints' => config('game.android_app_sha256_cert_fingerprints'),
        ],
    ]])->header('Content-Type', 'application/json');
});
Route::get('/favicon.ico', function () {
    $path = base_path('../frontend/assets/images/favicon.png');
    abort_unless(is_file($path), 404);

    return response()->file($path, [
        'Cache-Control' => 'public, max-age=604800',
        'Content-Type' => 'image/png',
    ]);
});
Route::get('/code/{code}', function (string $code) {
    $code = strtoupper($code);
    abort_unless((bool) preg_match('/^(?=(?:.*[A-Z]){4})(?=(?:.*\d){4})[A-Z\d]{8}$/', $code), 404);

    return view('deep-link', compact('code'));
});

Route::get('/', fn () => response()->file(public_path('dist/index.html')));
Route::get('/how-to-play', fn () => response()->file(public_path('dist/index.html')));
Route::get('/cookies', fn () => response()->file(public_path('dist/cookies/index.html')));
Route::get('/privacy', fn () => response()->file(public_path('dist/privacy/index.html')));
Route::get('/terms', fn () => response()->file(public_path('dist/terms/index.html')));
Route::get('/misery-og.png', function () {
    abort_unless(extension_loaded('gd'), 404);

    $sourcePath = base_path('../frontend/assets/images/icon.png');
    abort_unless(is_file($sourcePath), 404);

    $canvas = imagecreatetruecolor(1200, 630);
    $background = imagecolorallocate($canvas, 0, 0, 0);
    $grid = imagecolorallocate($canvas, 17, 17, 17);
    $amber = imagecolorallocate($canvas, 250, 204, 21);
    imagefill($canvas, 0, 0, $background);

    for ($x = 0; $x <= 1200; $x += 60) {
        imageline($canvas, $x, 0, $x, 630, $grid);
    }
    for ($y = 0; $y <= 630; $y += 60) {
        imageline($canvas, 0, $y, 1200, $y, $grid);
    }

    $source = imagecreatefrompng($sourcePath);
    imagecopyresampled($canvas, $source, 335, 30, 0, 0, 530, 530, imagesx($source), imagesy($source));
    imagefilledrectangle($canvas, 0, 612, 1200, 630, $amber);

    ob_start();
    imagepng($canvas, null, 8);
    $png = ob_get_clean();
    imagedestroy($source);
    imagedestroy($canvas);

    return response($png, 200, [
        'Cache-Control' => 'public, max-age=86400',
        'Content-Type' => 'image/png',
    ]);
})->name('landing.og-image');
Route::get('/simulator', fn () => view('play', [
    'stacks' => Stack::query()->orderBy('name')->get(['name', 'slug']),
]))->middleware('cms.auth')->name('simulator');
Route::get('/simulator/realtime-status', fn (RealtimeTransportAllocator $allocator) => response()->json($allocator->status()))
    ->middleware('cms.auth')
    ->name('simulator.realtime-status');
Route::get('/simulator/rooms', fn () => GameResource::collection(
    Game::query()->whereNull('terminated_at')->with('members')->latest()->get()
))->middleware('cms.auth')->name('simulator.rooms');
Route::get('/simulator/rooms/{game}', fn (Game $game) => new GameResource($game->load([
    'members',
    'currentCard',
    'stack',
    'moves' => fn ($query) => $query->with(['player', 'card'])->latest(),
    'messages' => fn ($query) => $query->with('user')->latest()->limit(100),
])))->middleware('cms.auth')->name('simulator.rooms.show');
// Kept for compatibility with existing admin clients. The simulator UI uses
// the Basic-auth-protected API endpoint so its Kill action does not rely on CSRF.
Route::delete('/simulator/rooms/{game}', function (Game $game, GameCleanupService $cleanup) {
    $cleanup->forceDelete($game);

    return response()->noContent();
})->middleware('cms.auth')->name('simulator.rooms.destroy');
Route::get('/card-images/{path}', function (string $path) {
    abort_if(str_contains($path, '..') || ! str_starts_with($path, 'cards/'), 404);
    abort_unless(Storage::disk('public')->exists($path), 404);
    $mimeType = Storage::disk('public')->mimeType($path) ?: (str_ends_with($path, '.svg') ? 'image/svg+xml' : 'image/png');
    $headers = [
        'Cache-Control' => 'public, max-age=31536000, immutable',
        'Content-Type' => $mimeType,
        'X-Content-Type-Options' => 'nosniff',
    ];
    if ($mimeType === 'image/svg+xml') {
        $headers['Content-Security-Policy'] = "default-src 'none'; sandbox";
    }

    return response()->file(Storage::disk('public')->path($path), $headers);
})->where('path', '.+')->name('card-images.show');
Route::middleware('cms.auth')->prefix('cms')->name('cms.')->group(function () {
    Route::view('/', 'cms.home')->name('home');
    Route::get('gallery', [CmsCardController::class, 'gallery'])->name('gallery.index');
    Route::get('native-card-artwork', function () {
        return response()->file(base_path('../frontend/assets/images/def-card.png'), [
            'Cache-Control' => 'public, max-age=86400',
            'Content-Type' => 'image/png',
        ]);
    })->name('native-card-artwork');
    Route::resource('cards', CmsCardController::class)->except('show');
    Route::post('cards/bulk-destroy', [CmsCardController::class, 'bulkDestroy'])->name('cards.bulk-destroy');
    Route::post('cards/swap-artwork', [CmsCardController::class, 'swapArtwork'])->name('cards.swap-artwork');
    Route::post('cards/{card}/assign-artwork', [CmsCardController::class, 'assignArtwork'])->name('cards.assign-artwork');
    Route::patch('cards/{card}/score', [CmsCardController::class, 'updateScore'])->name('cards.score');
    Route::post('cards/{card}/generate', [CmsCardController::class, 'generate'])->name('cards.generate');
    Route::post('cards/{card}/translate-bs', [CmsCardController::class, 'translateToBosnian'])->name('cards.translate-bs');
    Route::post('cards/{card}/crop-generated', [CmsCardController::class, 'saveGeneratedCrop'])->name('cards.crop-generated');
    Route::post('cards/{card}/convert-webp', [CmsCardController::class, 'convertArtworkToWebp'])->name('cards.convert-webp');
    Route::post('cards/{card}/enhance-artwork', [CmsCardController::class, 'enhanceArtwork'])->name('cards.enhance-artwork');
    Route::post('cards/{card}/generate-svg', [CmsCardController::class, 'generateSvg'])->name('cards.generate-svg');
    Route::post('cards/{card}/status', [CmsCardController::class, 'setStatus'])->name('cards.status');
    Route::get('generator', [CmsCardGeneratorController::class, 'index'])->name('generator.index');
    Route::post('generator', [CmsCardGeneratorController::class, 'generate'])->name('generator.generate');
    Route::get('content', [CmsContentGeneratorController::class, 'index'])->name('content.index');
    Route::post('content/generate', [CmsContentGeneratorController::class, 'generate'])->name('content.generate');
    Route::post('content/generate-silhouette', [CmsContentGeneratorController::class, 'generateSilhouette'])->name('content.generate-silhouette');
    Route::get('content-silhouette', function () {
        return response()->file(resource_path('ai/main-silhouette.svg'), ['Content-Type' => 'image/svg+xml']);
    })->name('content.silhouette');
    Route::get('content-logo-letter', function () {
        return response()->file(resource_path('ai/i-letter.png'), ['Content-Type' => 'image/png']);
    })->name('content.logo-letter');
    Route::get('content-assets/{filename}', function (string $filename) {
        abort_if(str_contains($filename, '/') || str_contains($filename, '..'), 404);
        $path = 'content/silhouettes/'.$filename;
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), ['Cache-Control' => 'private, max-age=86400']);
    })->name('content.assets');
    Route::get('screenshot-maker', [CmsScreenshotMakerController::class, 'index'])->name('screenshots.index');
    Route::post('screenshot-maker/generate', [CmsScreenshotMakerController::class, 'generate'])->name('screenshots.generate');
    Route::post('screenshot-maker/save', [CmsScreenshotMakerController::class, 'save'])->name('screenshots.save');
    Route::post('screenshot-maker/references', [CmsScreenshotMakerController::class, 'storeReferences'])->name('screenshots.references.store');
    Route::delete('screenshot-maker/references/{filename}', [CmsScreenshotMakerController::class, 'destroyReference'])->name('screenshots.references.destroy');
    Route::get('screenshot-maker/assets/{path}', [CmsScreenshotMakerController::class, 'asset'])
        ->where('path', '.+')->name('screenshots.assets');
    Route::get('stacks', [CmsStackController::class, 'index'])->name('stacks.index');
    Route::post('stacks', [CmsStackController::class, 'store'])->name('stacks.store');
    Route::patch('stacks/{stack}', [CmsStackController::class, 'update'])->name('stacks.update');
    Route::delete('stacks/{stack}', [CmsStackController::class, 'destroy'])->name('stacks.destroy');
});
