<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EvolveContentRepository
{
    public function __construct(
        protected EvolveContentModelScaffolder $scaffolder,
    ) {}

    public function payload(): array
    {
        $models = $this->models();
        $data = $models->mapWithKeys(fn (array $model): array => [
            $model['id'] => $this->rowsFor($model['id']),
        ]);

        return [
            'models' => $models->map(fn (array $model): array => [
                ...$model,
                'meta' => $data[$model['id']]->count().' rows',
            ])->values(),
            'data' => $data,
            'services' => $data['services'] ?? [],
        ];
    }

    public function updatePayload(array $payload): array
    {
        $models = $this->models();
        $data = collect($payload['data'] ?? []);
        if (array_key_exists('services', $payload)) {
            $data['services'] = $payload['services'];
        }

        $rows = $models->mapWithKeys(function (array $model) use ($data): array {
            return [$model['id'] => $this->replaceRows($model['id'], collect($data[$model['id']] ?? []))];
        });

        return [
            'models' => $models->map(fn (array $model): array => [
                ...$model,
                'meta' => $rows[$model['id']]->count().' rows',
            ])->values(),
            'data' => $rows,
            'services' => $rows['services'] ?? [],
        ];
    }

    public function models(): Collection
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

    public function rowsFor(string $modelId): Collection
    {
        $class = $this->model($modelId)['class'];

        return $class::query()
            ->ordered()
            ->get()
            ->map(fn ($row): array => $this->rowPayload($row))
            ->values();
    }

    public function previewRow(string $modelId, array $payload): array
    {
        $model = $this->model($modelId);
        $class = $model['class'];
        $row = is_numeric($payload['id'] ?? null) ? $class::query()->find((int) $payload['id']) : null;

        return [
            'dry_run' => true,
            'model' => $this->withoutClass($model),
            'would_create' => $row === null,
            'would_update' => $row !== null,
            'row' => $this->normalizedRowPayload($payload, $row?->position),
        ];
    }

    public function upsertRow(string $modelId, array $payload): array
    {
        $model = $this->model($modelId);
        $class = $model['class'];
        $row = is_numeric($payload['id'] ?? null)
            ? $class::query()->findOrNew((int) $payload['id'])
            : new $class;

        $row->fill($this->normalizedRowPayload($payload, $row->exists ? $row->position : null));
        $row->save();

        return [
            'model' => $this->withoutClass($model),
            'row' => $this->rowPayload($row),
        ];
    }

    public function previewDeleteRow(string $modelId, string $id): array
    {
        $model = $this->model($modelId);
        $class = $model['class'];
        $row = $class::query()->find($id);

        return [
            'dry_run' => true,
            'model' => $this->withoutClass($model),
            'would_delete' => $row !== null,
            'row' => $row ? $this->rowPayload($row) : null,
        ];
    }

    public function deleteRow(string $modelId, string $id): array
    {
        $model = $this->model($modelId);
        $class = $model['class'];
        $row = $class::query()->findOrFail($id);
        $payload = $this->rowPayload($row);
        $row->delete();

        return [
            'deleted' => true,
            'model' => $this->withoutClass($model),
            'row' => $payload,
        ];
    }

    public function previewContentModel(string $name): array
    {
        $classBase = Str::studly(Str::singular($name));
        $table = Str::plural(Str::snake($classBase));

        return [
            'dry_run' => true,
            'model' => $classBase,
            'table' => $table,
            'path' => "app/Models/{$classBase}.php",
            'migration_pattern' => "database/migrations/*_create_{$table}_table.php",
            'would_create' => $classBase !== 'User' && ! File::exists(app_path("Models/{$classBase}.php")),
        ];
    }

    public function createContentModel(string $name): array
    {
        $this->scaffolder->create($name);

        return $this->payload();
    }

    protected function replaceRows(string $modelId, Collection $payloads): Collection
    {
        $model = $this->model($modelId);
        $class = $model['class'];
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

                $row->fill($this->normalizedRowPayload($payload, $index + 1));
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

    protected function model(string $modelId): array
    {
        $model = $this->models()->firstWhere('id', $modelId);

        if (! $model) {
            throw ValidationException::withMessages(['model' => "Content model [{$modelId}] was not found."]);
        }

        return $model;
    }

    protected function normalizedRowPayload(array $payload, ?int $fallbackPosition = null): array
    {
        return [
            'icon' => (string) ($payload['icon'] ?? ''),
            'title' => (string) ($payload['title'] ?? ''),
            'summary' => (string) ($payload['summary'] ?? ''),
            'position' => (int) ($payload['position'] ?? $fallbackPosition ?? 1),
            'is_published' => (bool) ($payload['is_published'] ?? false),
        ];
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

    protected function withoutClass(array $model): array
    {
        unset($model['class']);

        return $model;
    }
}
