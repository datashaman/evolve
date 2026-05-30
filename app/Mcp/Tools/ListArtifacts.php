<?php

namespace App\Mcp\Tools;

use App\Services\EvolveLibrary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List Evolve workbench artifacts by kind.')]
class ListArtifacts extends Tool
{
    public function handle(Request $request, EvolveLibrary $library): ResponseFactory
    {
        $kind = $request->get('kind');
        $data = $library->all();

        if ($kind) {
            $data = [$this->groupKey((string) $kind) => $data[$this->groupKey((string) $kind)] ?? []];
        }

        return Response::structured([
            'artifacts' => $data,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(['style', 'component', 'form', 'layout', 'page'])->description('Optional artifact kind filter.')->nullable(),
        ];
    }

    private function groupKey(string $kind): string
    {
        return match ($kind) {
            'style' => 'styles',
            'component' => 'components',
            'form' => 'forms',
            'layout' => 'layouts',
            'page' => 'pages',
            default => 'artifacts',
        };
    }
}
