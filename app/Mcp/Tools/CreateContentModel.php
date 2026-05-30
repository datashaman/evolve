<?php

namespace App\Mcp\Tools;

use App\Services\EvolveContentRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new Evolve content model. Defaults to dry-run.')]
class CreateContentModel extends Tool
{
    public function handle(Request $request, EvolveContentRepository $content): ResponseFactory
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9 _-]*$/'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        $dryRun = (bool) ($data['dry_run'] ?? true);

        return Response::structured($dryRun
            ? $content->previewContentModel($data['name'])
            : [
                'dry_run' => false,
                'created' => true,
                ...$content->createContentModel($data['name']),
            ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Content model name, for example Case Study.')->required(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Set false to scaffold files/table.')->nullable(),
        ];
    }
}
