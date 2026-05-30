<?php

namespace App\Mcp\Tools;

use App\Services\EvolveContentRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete one Evolve content row. Requires dry_run=false and confirm_id.')]
class DeleteContentRow extends Tool
{
    public function handle(Request $request, EvolveContentRepository $content): ResponseFactory
    {
        $data = $request->validate([
            'model' => ['required', 'string'],
            'id' => ['required', 'string'],
            'confirm_id' => ['nullable', 'string'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        $dryRun = (bool) ($data['dry_run'] ?? true);
        if (! $dryRun && ($data['confirm_id'] ?? '') !== $data['id']) {
            throw ValidationException::withMessages(['confirm_id' => 'confirm_id must match id to delete a content row.']);
        }

        $result = $dryRun
            ? $content->previewDeleteRow($data['model'], $data['id'])
            : $content->deleteRow($data['model'], $data['id']);

        return Response::structured([
            'dry_run' => $dryRun,
            ...$result,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()->description('Content model/table id, for example articles.')->required(),
            'id' => $schema->string()->required(),
            'confirm_id' => $schema->string()->description('Required and must match id when dry_run is false.')->nullable(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Set false to delete.')->nullable(),
        ];
    }
}
