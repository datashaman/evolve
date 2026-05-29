<?php

use App\Http\Controllers\EvolveContentController;
use App\Http\Controllers\EvolveLibraryController;
use App\Http\Controllers\EvolvePreviewController;
use App\Services\EvolveLibrary;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('workbench', 'workbench')->name('workbench');

    Route::get('api/library', [EvolveLibraryController::class, 'index']);
    Route::put('api/library', [EvolveLibraryController::class, 'update']);
    Route::get('api/content', [EvolveContentController::class, 'index']);
    Route::post('api/content/models', [EvolveContentController::class, 'storeModel']);
    Route::put('api/content', [EvolveContentController::class, 'update']);
    Route::get('workbench/preview/{kind}/{id}', [EvolvePreviewController::class, 'show'])
        ->where('kind', 'component|layout|page')
        ->where('id', '[A-Za-z0-9_\\-\\/]+');
});

Route::get('evolve.css', [EvolveLibraryController::class, 'stylesheet'])->name('evolve.styles');

require __DIR__.'/settings.php';

foreach (app(EvolveLibrary::class)->pageRoutes() as $page) {
    Route::livewire($page['slug'], $page['component'])
        ->name($page['slug'] === '/' ? 'home' : trim($page['slug'], '/'));
}
