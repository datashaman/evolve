<?php

namespace App\Mcp\Tools;

use App\Services\EvolveContentRepository;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List Evolve content models managed by the workbench.')]
class ListContentModels extends Tool
{
    public function handle(Request $request, EvolveContentRepository $content): ResponseFactory
    {
        return Response::structured([
            'models' => $content->models()->map(function (array $model): array {
                unset($model['class']);

                return $model;
            })->values(),
        ]);
    }
}
