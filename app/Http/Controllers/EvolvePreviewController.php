<?php

namespace App\Http\Controllers;

use App\Services\EvolveLibrary;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\View;

class EvolvePreviewController extends Controller
{
    public function show(EvolveLibrary $library, string $kind, string $id): View
    {
        $usage = $library->usage($kind, $id);

        return view('evolve.preview', [
            'kind' => $kind,
            'content' => $usage !== '' ? Blade::render($usage) : '<div class="empty-preview">No preview usage defined.</div>',
        ]);
    }
}
