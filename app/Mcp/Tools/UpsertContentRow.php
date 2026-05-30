<?php

namespace App\Mcp\Tools;

use App\Services\EvolveContentRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update one Evolve content row. Defaults to dry-run.')]
class UpsertContentRow extends Tool
{
    public function handle(Request $request, EvolveContentRepository $content): ResponseFactory
    {
        $data = $request->validate([
            'model' => ['required', 'string'],
            'id' => ['nullable', 'string'],
            'icon' => ['nullable', 'string'],
            'title' => ['required', 'string'],
            'summary' => ['nullable', 'string'],
            'position' => ['nullable', 'integer'],
            'is_published' => ['sometimes', 'boolean'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        $dryRun = (bool) ($data['dry_run'] ?? true);
        $result = $dryRun
            ? $content->previewRow($data['model'], $data)
            : $content->upsertRow($data['model'], $data);

        return Response::structured([
            'dry_run' => $dryRun,
            ...$result,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()->description('Content model/table id, for example articles.')->required(),
            'id' => $schema->string()->description('Existing row id for updates.')->nullable(),
            'icon' => $schema->string()->nullable(),
            'title' => $schema->string()->required(),
            'summary' => $schema->string()->nullable(),
            'position' => $schema->integer()->nullable(),
            'is_published' => $schema->boolean()->nullable(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Set false to write.')->nullable(),
        ];
    }
}
