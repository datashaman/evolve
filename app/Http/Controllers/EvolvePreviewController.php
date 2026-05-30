<?php

namespace App\Http\Controllers;

use App\Services\EvolveLibrary;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\View;

class EvolvePreviewController extends Controller
{
    public function show(EvolveLibrary $library, string $kind, string $id): Response|View
    {
        $usage = $library->usage($kind, $id);
        $content = $usage !== '' ? Blade::render($usage) : '<div class="empty-preview">No preview usage defined.</div>';

        if ($kind === 'layout') {
            return response($content);
        }

        return view('evolve.preview', [
            'kind' => $kind,
            'content' => $content,
        ]);
    }
}
