<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\WorksWithEvolveArtifacts;
use App\Services\EvolveLibrary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Read one Evolve artifact by kind and id.')]
class ReadArtifact extends Tool
{
    use WorksWithEvolveArtifacts;

    public function handle(Request $request, EvolveLibrary $library): ResponseFactory
    {
        $data = $request->validate([
            'kind' => ['required', 'string', 'in:style,component,form,layout,page,snippet,view'],
            'id' => ['required', 'string'],
        ]);

        return Response::structured([
            'artifact' => $this->requireArtifact($library, $data['kind'], $data['id']),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(['style', 'component', 'form', 'layout', 'page', 'snippet', 'view'])->required(),
            'id' => $schema->string()->description('Artifact id, for example contact or sections/hero.')->required(),
        ];
    }
}
