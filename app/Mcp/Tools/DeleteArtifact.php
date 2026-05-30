<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\WorksWithEvolveArtifacts;
use App\Services\EvolveLibrary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete one Evolve artifact. Requires dry_run=false and confirm_id for deletion.')]
class DeleteArtifact extends Tool
{
    use WorksWithEvolveArtifacts;

    public function handle(Request $request, EvolveLibrary $library): ResponseFactory
    {
        $data = $request->validate([
            'kind' => ['required', 'string', 'in:style,component,form,layout,page,snippet'],
            'id' => ['required', 'string'],
            'confirm_id' => ['nullable', 'string'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        $artifact = $this->requireArtifact($library, $data['kind'], $data['id']);
        $dryRun = (bool) ($data['dry_run'] ?? true);

        if (! $dryRun) {
            if (($data['confirm_id'] ?? '') !== $data['id']) {
                throw ValidationException::withMessages(['confirm_id' => 'confirm_id must match id to delete an artifact.']);
            }

            $library->deleteArtifact($data['kind'], $data['id']);
        }

        return Response::structured([
            'dry_run' => $dryRun,
            'deleted' => ! $dryRun,
            'artifact' => $artifact,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(['style', 'component', 'form', 'layout', 'page', 'snippet'])->required(),
            'id' => $schema->string()->required(),
            'confirm_id' => $schema->string()->description('Required and must match id when dry_run is false.')->nullable(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Set false to delete.')->nullable(),
        ];
    }
}
