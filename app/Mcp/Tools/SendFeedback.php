<?php

namespace App\Mcp\Tools;

use App\Services\EvolveFeedbackRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Record product, content, or implementation feedback for the Evolve workbench. Defaults to dry-run.')]
class SendFeedback extends Tool
{
    public function handle(Request $request, EvolveFeedbackRepository $feedback): ResponseFactory
    {
        $data = $request->validate([
            'id' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_.:-]+$/'],
            'type' => ['nullable', 'string', 'in:bug,feature,content,design,question,other'],
            'message' => ['required', 'string'],
            'source' => ['nullable', 'string', 'max:80'],
            'author' => ['nullable', 'string', 'max:120'],
            'url' => ['nullable', 'string'],
            'artifact_kind' => ['nullable', 'string', 'in:style,component,form,layout,page,snippet,view'],
            'artifact_id' => ['nullable', 'string'],
            'context' => ['nullable', 'array'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        $dryRun = (bool) ($data['dry_run'] ?? true);
        $result = $dryRun
            ? $feedback->previewFeedback($data)
            : $feedback->sendFeedback($data);

        return Response::structured([
            'dry_run' => $dryRun,
            ...$result,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Optional stable feedback id. Generated when omitted.')->nullable(),
            'type' => $schema->string()->enum(['bug', 'feature', 'content', 'design', 'question', 'other'])->description('Feedback category. Defaults to other.')->nullable(),
            'message' => $schema->string()->description('The feedback text.')->required(),
            'source' => $schema->string()->description('Where the feedback came from. Defaults to mcp.')->nullable(),
            'author' => $schema->string()->nullable(),
            'url' => $schema->string()->description('Optional page or preview URL related to the feedback.')->nullable(),
            'artifact_kind' => $schema->string()->enum(['style', 'component', 'form', 'layout', 'page', 'snippet', 'view'])->nullable(),
            'artifact_id' => $schema->string()->description('Optional artifact id related to the feedback.')->nullable(),
            'context' => $schema->object()->description('Optional structured context captured with the feedback.')->nullable(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Set false to persist feedback.')->nullable(),
        ];
    }
}
