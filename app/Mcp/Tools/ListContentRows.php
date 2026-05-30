<?php

namespace App\Mcp\Tools;

use App\Services\EvolveContentRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List rows for one Evolve content model.')]
class ListContentRows extends Tool
{
    public function handle(Request $request, EvolveContentRepository $content): ResponseFactory
    {
        $data = $request->validate([
            'model' => ['required', 'string'],
        ]);

        return Response::structured([
            'model' => $data['model'],
            'rows' => $content->rowsFor($data['model']),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()->description('Content model/table id, for example services.')->required(),
        ];
    }
}
