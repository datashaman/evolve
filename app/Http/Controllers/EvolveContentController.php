<?php

namespace App\Http\Controllers;

use App\Services\EvolveContentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvolveContentController extends Controller
{
    public function index(EvolveContentRepository $content): JsonResponse
    {
        return response()->json($content->payload());
    }

    public function storeModel(Request $request, EvolveContentRepository $content): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9 _-]*$/'],
        ]);

        $content->createContentModel($data['name']);

        return $this->index($content);
    }

    public function update(Request $request, EvolveContentRepository $content): JsonResponse
    {
        return response()->json($content->updatePayload($request->all()));
    }
}
