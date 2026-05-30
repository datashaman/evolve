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
    Route::put('api/library/styles/order', [EvolveLibraryController::class, 'orderStyles']);
    Route::put('api/library/{kind}/{id}', [EvolveLibraryController::class, 'updateArtifact'])
        ->where('id', '[A-Za-z0-9_\\-\\/]+');
    Route::delete('api/library/{kind}/{id}', [EvolveLibraryController::class, 'deleteArtifact'])
        ->where('id', '[A-Za-z0-9_\\-\\/]+');
    Route::get('api/content', [EvolveContentController::class, 'index']);
    Route::post('api/content/models', [EvolveContentController::class, 'storeModel']);
    Route::put('api/content', [EvolveContentController::class, 'update']);
    Route::get('workbench/preview/{kind}/{id}', [EvolvePreviewController::class, 'show'])
        ->where('kind', 'component|form|layout|page|snippet')
        ->where('id', '[A-Za-z0-9_\\-\\/]+');
});

Route::get('evolve.css', [EvolveLibraryController::class, 'stylesheet'])->name('evolve.styles');

require __DIR__.'/settings.php';

$artifactRoutes = app(EvolveLibrary::class)->artifactRoutes();

if (! collect($artifactRoutes)->contains(fn (array $artifactRoute): bool => $artifactRoute['route'] === '/')) {
    Route::view('/', 'welcome')->name('home');
}

foreach ($artifactRoutes as $artifactRoute) {
    $registered = Route::livewire($artifactRoute['route'], $artifactRoute['component'])
        ->name($artifactRoute['route_name']);

    if (! empty($artifactRoute['middleware'])) {
        $registered->middleware($artifactRoute['middleware']);
    }
}
