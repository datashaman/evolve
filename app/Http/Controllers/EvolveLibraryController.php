<?php

namespace App\Http\Controllers;

use App\Services\EvolveLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EvolveLibraryController extends Controller
{
    public function index(EvolveLibrary $library): JsonResponse
    {
        return response()->json($library->all());
    }

    public function update(Request $request, EvolveLibrary $library): JsonResponse
    {
        $library->write($request->all());

        return response()->json(['ok' => true]);
    }

    public function updateArtifact(Request $request, EvolveLibrary $library, string $kind, string $id): JsonResponse
    {
        $this->ensureValidKind($kind);

        $library->writeArtifact($kind, $id, $request->all());

        return response()->json($library->all());
    }

    public function deleteArtifact(EvolveLibrary $library, string $kind, string $id): JsonResponse
    {
        $this->ensureValidKind($kind);

        $library->deleteArtifact($kind, $id);

        return response()->json($library->all());
    }

    public function orderStyles(Request $request, EvolveLibrary $library): JsonResponse
    {
        $ids = $request->input('ids', []);
        $library->orderStyles(is_array($ids) ? $ids : []);

        return response()->json($library->all());
    }

    public function stylesheet(EvolveLibrary $library): Response
    {
        return response($library->stylesheet(), 200, [
            'Content-Type' => 'text/css',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function ensureValidKind(string $kind): void
    {
        abort_unless(in_array($kind, ['style', 'component', 'form', 'layout', 'page'], true), 404);
    }
}
