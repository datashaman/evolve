<?php

namespace App\Mcp\Tools;

use App\Services\EvolveFeedbackRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Triage an existing Evolve feedback item. Defaults to dry-run.')]
class TriageFeedback extends Tool
{
    public function handle(Request $request, EvolveFeedbackRepository $feedback): ResponseFactory
    {
        $data = $request->validate([
            'id' => ['required', 'string'],
            'status' => ['nullable', 'string', 'in:new,triaged,accepted,rejected,planned,in_progress,done'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'labels' => ['nullable', 'array'],
            'labels.*' => ['string'],
            'assignee' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        $dryRun = (bool) ($data['dry_run'] ?? true);
        $result = $dryRun
            ? $feedback->previewTriage($data['id'], $data)
            : $feedback->triageFeedback($data['id'], $data);

        return Response::structured([
            'dry_run' => $dryRun,
            ...$result,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Feedback id to triage.')->required(),
            'status' => $schema->string()->enum(['new', 'triaged', 'accepted', 'rejected', 'planned', 'in_progress', 'done'])->nullable(),
            'priority' => $schema->string()->enum(['low', 'medium', 'high', 'urgent'])->nullable(),
            'labels' => $schema->array()->items($schema->string())->description('Labels to replace on the feedback item.')->nullable(),
            'assignee' => $schema->string()->nullable(),
            'notes' => $schema->string()->description('Triage notes.')->nullable(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Set false to persist triage.')->nullable(),
        ];
    }
}
