<?php

namespace App\Mcp\Tools;

use App\Services\EvolveLibrary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Reorder Evolve global styles. Defaults to dry-run.')]
class ReorderStyles extends Tool
{
    public function handle(Request $request, EvolveLibrary $library): ResponseFactory
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['string'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        $dryRun = (bool) ($data['dry_run'] ?? true);
        if (! $dryRun) {
            $library->orderStyles($data['ids']);
        }

        $styles = $this->orderedStyles($library->all()['styles'], $data['ids']);

        return Response::structured([
            'dry_run' => $dryRun,
            'style_ids' => $data['ids'],
            'styles' => $styles,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'ids' => $schema->array()->items($schema->string())->description('Style ids in desired order.')->required(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Set false to write.')->nullable(),
        ];
    }

    protected function orderedStyles(array $styles, array $ids): array
    {
        $positions = array_flip(array_values($ids));

        return collect($styles)
            ->sortBy(fn (array $style, int $index) => $positions[$style['id'] ?? ''] ?? count($positions) + $index)
            ->values()
            ->all();
    }
}
