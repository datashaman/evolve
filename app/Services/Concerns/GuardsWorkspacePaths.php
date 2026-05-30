<?php

namespace App\Services\Concerns;

use Illuminate\Validation\ValidationException;

trait GuardsWorkspacePaths
{
    protected function assertPathInsideWorkspace(string $path): void
    {
        $root = $this->normalizedWorkspaceRoot();
        $target = $this->normalizedWorkspacePath($path);

        if ($target === $root || str_starts_with($target, $root.'/')) {
            return;
        }

        throw ValidationException::withMessages([
            'path' => 'Path updates must stay inside the current working folder.',
        ]);
    }

    protected function normalizedWorkspaceRoot(): string
    {
        return rtrim(str_replace('\\', '/', realpath(base_path()) ?: base_path()), '/');
    }

    protected function normalizedWorkspacePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $realPath = realpath($path);

        if ($realPath !== false) {
            return rtrim(str_replace('\\', '/', $realPath), '/');
        }

        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        return $this->collapseWorkspacePath($path);
    }

    protected function collapseWorkspacePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $root = str_starts_with($path, '/') ? '/' : '';
        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $part;
        }

        return rtrim($root.implode('/', $parts), '/');
    }
}
