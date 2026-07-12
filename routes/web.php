<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Cms\CardController as CmsCardController;
use App\Http\Controllers\Cms\StackController as CmsStackController;

Route::view('/', 'play');
Route::middleware('cms.auth')->prefix('cms')->name('cms.')->group(function () {
    Route::view('/', 'cms.home')->name('home');
    Route::resource('cards', CmsCardController::class)->except('show');
    Route::post('cards/{card}/generate', [CmsCardController::class, 'generate'])->name('cards.generate');
    Route::get('stacks', [CmsStackController::class, 'index'])->name('stacks.index');
    Route::post('stacks', [CmsStackController::class, 'store'])->name('stacks.store');
    Route::delete('stacks/{stack}', [CmsStackController::class, 'destroy'])->name('stacks.destroy');
});
