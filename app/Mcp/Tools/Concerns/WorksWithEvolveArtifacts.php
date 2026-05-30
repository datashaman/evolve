<?php

namespace App\Mcp\Tools\Concerns;

use App\Services\EvolveLibrary;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait WorksWithEvolveArtifacts
{
    protected function groupKey(string $kind): string
    {
        return match ($kind) {
            'style' => 'styles',
            'component' => 'components',
            'form' => 'forms',
            'layout' => 'layouts',
            'page' => 'pages',
            'snippet' => 'snippets',
            default => throw ValidationException::withMessages(['kind' => "Unsupported artifact kind [{$kind}]."]),
        };
    }

    protected function artifact(EvolveLibrary $library, string $kind, string $id): ?array
    {
        return collect($library->all()[$this->groupKey($kind)] ?? [])
            ->firstWhere('id', $id);
    }

    protected function requireArtifact(EvolveLibrary $library, string $kind, string $id): array
    {
        $artifact = $this->artifact($library, $kind, $id);

        if (! $artifact) {
            throw ValidationException::withMessages(['id' => "{$kind} artifact [{$id}] was not found."]);
        }

        return $artifact;
    }

    protected function normalizedArtifact(array $payload): array
    {
        $kind = (string) ($payload['kind'] ?? '');
        $id = $this->idFromPath((string) ($payload['path'] ?? $payload['id'] ?? ''));

        if ($id === '') {
            throw ValidationException::withMessages(['path' => 'Artifact path or id is required.']);
        }

        return [
            'id' => $id,
            'kind' => $kind,
            'name' => (string) ($payload['name'] ?? Str::headline(basename($id))),
            'path' => $this->relativePath($kind, $id),
            'source_path' => $this->relativePath($kind, $id),
            'route' => in_array($kind, ['form', 'page'], true) ? (string) ($payload['route'] ?? '') : '',
            'parent_id' => $kind === 'page' ? $this->idFromPath((string) ($payload['parent_id'] ?? '')) : '',
            'order' => $kind === 'page' ? (int) ($payload['order'] ?? 0) : 0,
            'component' => $this->componentReference($kind, $id),
            'usage' => (string) ($payload['usage'] ?? $this->defaultUsage($kind, $id)),
            'php' => (string) ($payload['php'] ?? ''),
            'blade' => (string) ($payload['blade'] ?? ''),
            'style' => (string) ($payload['style'] ?? ''),
        ];
    }

    protected function idFromPath(string $path): string
    {
        $path = preg_replace('#\.(blade\.php|css)$#', '', str_replace('\\', '/', trim($path)));
        $path = preg_replace('#^(resources/views/(components|forms|layouts|pages|snippets)/|resources/css/layouts/|resources/css/)#', '', $path);
        $path = strtolower(trim((string) $path, '/'));

        return collect(explode('/', preg_replace('#/+#', '/', $path)))
            ->map(fn (string $part): string => Str::slug($part))
            ->filter()
            ->implode('/');
    }

    protected function relativePath(string $kind, string $id): string
    {
        return match ($kind) {
            'style' => "resources/css/{$id}.css",
            'form' => "resources/views/forms/{$id}.blade.php",
            'layout' => "resources/views/layouts/{$id}.blade.php",
            'page' => "resources/views/pages/{$id}.blade.php",
            'snippet' => "resources/views/snippets/{$id}.blade.php",
            default => "resources/views/components/{$id}.blade.php",
        };
    }

    protected function componentReference(string $kind, string $id): string
    {
        $component = str_replace('/', '.', $id);

        return match ($kind) {
            'form' => "forms::{$component}",
            'layout' => "layouts::{$component}",
            'page' => "pages::{$component}",
            'snippet' => "snippets::{$component}",
            default => $component,
        };
    }

    protected function defaultUsage(string $kind, string $id): string
    {
        $component = str_replace('/', '.', $id);

        return match ($kind) {
            'component' => "<livewire:{$component} />",
            'form' => "<livewire:forms::{$component} />",
            'layout' => "<x-layouts::{$component}></x-layouts::{$component}>",
            'page' => "<livewire:pages::{$component} />",
            'snippet' => "<x-snippets::{$component} />",
            default => '',
        };
    }
}
