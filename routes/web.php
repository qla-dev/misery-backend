<?php

use App\Http\Controllers\Cms\CardController as CmsCardController;
use App\Http\Controllers\Cms\QuestionController as CmsQuestionController;
use App\Http\Controllers\Cms\StackController as CmsStackController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', fn () => response()->file(public_path('dist/index.html')));
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
Route::view('/simulator', 'play')->middleware('cms.auth')->name('simulator');
Route::get('/card-images/{path}', function (string $path) {
    abort_if(str_contains($path, '..') || ! str_starts_with($path, 'cards/'), 404);
    abort_unless(Storage::disk('public')->exists($path), 404);

    return response()->file(Storage::disk('public')->path($path), [
        'Cache-Control' => 'public, max-age=31536000, immutable',
        'Content-Type' => Storage::disk('public')->mimeType($path) ?: 'image/png',
    ]);
})->where('path', '.+')->name('card-images.show');
Route::middleware('cms.auth')->prefix('cms')->name('cms.')->group(function () {
    Route::view('/', 'cms.home')->name('home');
    Route::resource('cards', CmsCardController::class)->except('show');
    Route::post('cards/{card}/generate', [CmsCardController::class, 'generate'])->name('cards.generate');
    Route::get('questions/generate-ai', [CmsQuestionController::class, 'generateForm'])->name('questions.generate-form');
    Route::post('questions/generate-ai', [CmsQuestionController::class, 'generate'])->name('questions.generate');
    Route::resource('questions', CmsQuestionController::class)->except('show');
    Route::get('stacks', [CmsStackController::class, 'index'])->name('stacks.index');
    Route::post('stacks', [CmsStackController::class, 'store'])->name('stacks.store');
    Route::delete('stacks/{stack}', [CmsStackController::class, 'destroy'])->name('stacks.destroy');
});
