<?php

namespace App\Http\Controllers;

use App\Services\EvolveContentModelScaffolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class EvolveContentController extends Controller
{
    public function index(): JsonResponse
    {
        $models = $this->contentModels();
        $data = $models
            ->mapWithKeys(fn (array $model): array => [
                $model['id'] => $this->rowsFor($model),
            ]);

        return response()->json([
            'models' => $models->map(fn (array $model): array => [
                ...$model,
                'meta' => $data[$model['id']]->count().' rows',
            ])->values(),
            'data' => $data,
            'services' => $data['services'] ?? [],
        ]);
    }

    public function storeModel(Request $request, EvolveContentModelScaffolder $scaffolder): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9 _-]*$/'],
        ]);

        $scaffolder->create($data['name']);

        return $this->index();
    }

    public function update(Request $request): JsonResponse
    {
        $models = $this->contentModels();
        $data = collect($request->input('data', []));
        if ($request->has('services')) {
            $data['services'] = $request->input('services', []);
        }

        $rows = $models->mapWithKeys(function (array $model) use ($data): array {
            $payloads = collect($data[$model['id']] ?? []);

            return [$model['id'] => $this->updateRows($model, $payloads)];
        });

        return response()->json([
            'models' => $models->map(fn (array $model): array => [
                ...$model,
                'meta' => $rows[$model['id']]->count().' rows',
            ])->values(),
            'data' => $rows,
            'services' => $rows['services'] ?? [],
        ]);
    }

    protected function updateRows(array $model, mixed $payloads): mixed
    {
        $class = $model['class'];
        $payloads = collect($payloads);
        $incomingIds = $payloads
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        if ($incomingIds->isEmpty()) {
            $class::query()->delete();
        } else {
            $class::query()->whereNotIn('id', $incomingIds)->delete();
        }

        return $payloads
            ->map(function (array $payload, int $index) use ($class): array {
                $row = is_numeric($payload['id'] ?? null)
                    ? $class::query()->findOrNew((int) $payload['id'])
                    : new $class;

                $row->fill([
                    'icon' => (string) ($payload['icon'] ?? ''),
                    'title' => (string) ($payload['title'] ?? ''),
                    'summary' => (string) ($payload['summary'] ?? ''),
                    'position' => (int) ($payload['position'] ?? $index + 1),
                    'is_published' => (bool) ($payload['is_published'] ?? false),
                ]);
                $row->save();

                return [
                    'client_id' => (string) ($payload['id'] ?? ''),
                    'row' => $row,
                ];
            })
            ->sortBy(fn (array $entry) => $entry['row']->position)
            ->map(fn (array $entry) => [
                ...$this->rowPayload($entry['row']),
                'client_id' => $entry['client_id'],
            ])
            ->values();
    }

    protected function rowsFor(array $model): mixed
    {
        $class = $model['class'];

        return $class::query()
            ->ordered()
            ->get()
            ->map(fn ($row): array => $this->rowPayload($row))
            ->values();
    }

    protected function rowPayload($row): array
    {
        return [
            'id' => (string) $row->id,
            'kind' => 'content',
            'model' => class_basename($row),
            'name' => $row->title,
            'title' => $row->title,
            'icon' => $row->icon,
            'summary' => $row->summary,
            'position' => $row->position,
            'is_published' => $row->is_published,
            'path' => 'app/Models/'.class_basename($row).'.php',
        ];
    }

    protected function contentModels(): mixed
    {
        return collect(File::glob(app_path('Models/*.php')))
            ->map(function (string $path): ?array {
                $name = basename($path, '.php');
                if ($name === 'User') {
                    return null;
                }

                $class = "App\\Models\\{$name}";
                if (! class_exists($class)) {
                    return null;
                }

                $model = new $class;
                $table = $model->getTable();
                $columns = ['icon', 'title', 'summary', 'position', 'is_published'];
                if (! Schema::hasTable($table) || collect($columns)->contains(fn (string $column): bool => ! Schema::hasColumn($table, $column))) {
                    return null;
                }

                return [
                    'id' => $table,
                    'kind' => 'content',
                    'name' => $name,
                    'model' => $name,
                    'class' => $class,
                    'table' => $table,
                    'path' => "app/Models/{$name}.php",
                ];
            })
            ->filter()
            ->sortBy('name')
            ->values();
    }
}
