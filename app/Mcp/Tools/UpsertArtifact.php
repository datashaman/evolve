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

#[Description('Create or update one Evolve artifact. Defaults to dry-run.')]
class UpsertArtifact extends Tool
{
    use WorksWithEvolveArtifacts;

    public function handle(Request $request, EvolveLibrary $library): ResponseFactory
    {
        $data = $request->validate([
            'kind' => ['required', 'string', 'in:style,component,form,layout,page'],
            'id' => ['nullable', 'string'],
            'name' => ['nullable', 'string'],
            'path' => ['nullable', 'string'],
            'route' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'string'],
            'order' => ['nullable', 'integer'],
            'php' => ['nullable', 'string'],
            'blade' => ['nullable', 'string'],
            'style' => ['nullable', 'string'],
            'usage' => ['nullable', 'string'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        $artifact = $this->normalizedArtifact($data);
        $existingId = (string) ($data['id'] ?? $artifact['id']);
        $existing = $this->artifact($library, $data['kind'], $existingId);
        $dryRun = (bool) ($data['dry_run'] ?? true);

        if (! $dryRun) {
            $library->writeArtifact($data['kind'], $existingId, $artifact);
            $artifact = $this->requireArtifact($library, $data['kind'], $artifact['id']);
        }

        return Response::structured([
            'dry_run' => $dryRun,
            'action' => $existing ? 'update' : 'create',
            'would_write' => $dryRun,
            'artifact' => $artifact,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(['style', 'component', 'form', 'layout', 'page'])->required(),
            'id' => $schema->string()->description('Existing id when updating. Optional for new artifacts if path is supplied.')->nullable(),
            'name' => $schema->string()->nullable(),
            'path' => $schema->string()->description('Target source path, such as resources/views/forms/contact.blade.php.')->nullable(),
            'route' => $schema->string()->description('Route for pages and hosted forms.')->nullable(),
            'parent_id' => $schema->string()->description('Parent page id for page tree placement.')->nullable(),
            'order' => $schema->integer()->description('Page order within its parent.')->nullable(),
            'php' => $schema->string()->nullable(),
            'blade' => $schema->string()->nullable(),
            'style' => $schema->string()->nullable(),
            'usage' => $schema->string()->nullable(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Set false to write.')->nullable(),
        ];
    }
}
