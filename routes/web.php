<?php

use App\Http\Controllers\Cms\CardController as CmsCardController;
use App\Http\Controllers\Cms\QuestionController as CmsQuestionController;
use App\Http\Controllers\Cms\StackController as CmsStackController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::view('/', 'play');
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
