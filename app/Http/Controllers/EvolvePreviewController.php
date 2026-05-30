<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EvolveLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\View;

class EvolvePreviewController extends Controller
{
    public function show(EvolveLibrary $library, string $kind, string $id): Response|View
    {
        $usage = $library->usage($kind, $id);
        $content = $usage !== '' ? Blade::render($usage) : '<div class="empty-preview">No preview usage defined.</div>';

        if ($kind === 'layout' || ($kind === 'view' && $library->viewRole($id) === 'page')) {
            return response($content);
        }

        return view('evolve.preview', [
            'kind' => $kind,
            'content' => $content,
        ]);
    }

    public function users(): JsonResponse
    {
        abort_unless((bool) config('evolve.preview.allow_impersonation'), 403);

        return response()->json([
            'allow_impersonation' => true,
            'users' => User::query()
                ->orderBy('name')
                ->limit(100)
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user): array => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->all(),
        ]);
    }
}
