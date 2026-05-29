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

    public function tokens(EvolveLibrary $library): Response
    {
        return response($library->tokens(), 200, ['Content-Type' => 'text/css']);
    }

    public function stylesheet(EvolveLibrary $library): Response
    {
        return response($library->stylesheet(), 200, ['Content-Type' => 'text/css']);
    }

    public function updateTokens(Request $request, EvolveLibrary $library): JsonResponse
    {
        $library->writeTokens($request->getContent());

        return response()->json(['ok' => true]);
    }
}
