<?php

namespace App\Support;

use App\Services\EvolveLibrary;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class EvolveContentTree
{
    public function __construct(protected EvolveLibrary $library) {}

    public function navigation(?string $parentId = null, ?int $maxDepth = null): array
    {
        return $this->pageTree($this->normalizeId($parentId), $maxDepth);
    }

    public function childPages(?string $pageId = null): array
    {
        $page = $this->page($pageId);

        return $page ? $this->pagesByParent((string) $page['id']) : [];
    }

    public function siblingPages(?string $pageId = null): array
    {
        $page = $this->page($pageId);

        return $page ? $this->pagesByParent((string) ($page['parent_id'] ?? '')) : [];
    }

    public function page(?string $pageId = null): ?array
    {
        if (filled($pageId)) {
            $id = $this->normalizeId($pageId);
            $route = $this->normalizeRoute($pageId);

            return collect($this->pages())->first(fn (array $page): bool => ($page['id'] ?? '') === $id || ($page['route'] ?? '') === $route);
        }

        return $this->currentPage();
    }

    public function metadata(?string $key = null, mixed $default = null, ?string $pageId = null): mixed
    {
        $page = $this->page($pageId);

        if (! $page) {
            return $default;
        }

        $metadata = [
            ...Arr::only($page, ['id', 'name', 'route', 'path', 'source_path', 'parent_id', 'order', 'depth', 'component', 'usage']),
            ...(is_array($page['metadata'] ?? null) ? $page['metadata'] : []),
        ];

        return $key === null ? $metadata : data_get($metadata, $key, $default);
    }

    public function snippet(string $id, array $data = []): HtmlString
    {
        $snippet = collect($this->library->all()['snippets'] ?? [])
            ->firstWhere('id', $this->normalizeId($id));

        if (! $snippet) {
            return new HtmlString('');
        }

        return new HtmlString(Blade::render((string) ($snippet['blade'] ?? ''), $data));
    }

    protected function currentPage(): ?array
    {
        $path = '/'.trim(request()->path(), '/');
        $path = $path === '/' ? '/' : $path;

        return collect($this->pages())
            ->first(fn (array $page): bool => $this->routeMatches((string) ($page['route'] ?? ''), $path));
    }

    protected function pageTree(string $parentId = '', ?int $maxDepth = null, int $depth = 0): array
    {
        if ($maxDepth !== null && $depth >= $maxDepth) {
            return [];
        }

        return collect($this->pagesByParent($parentId))
            ->map(fn (array $page): array => [
                ...$page,
                'children' => $this->pageTree((string) $page['id'], $maxDepth, $depth + 1),
            ])
            ->all();
    }

    protected function pagesByParent(string $parentId): array
    {
        return collect($this->pages())
            ->filter(fn (array $page): bool => (string) ($page['parent_id'] ?? '') === $parentId)
            ->values()
            ->all();
    }

    protected function pages(): array
    {
        return $this->library->all()['pages'] ?? [];
    }

    protected function routeMatches(string $route, string $path): bool
    {
        $route = $this->normalizeRoute($route);

        if ($route === $path) {
            return true;
        }

        $pattern = preg_replace('#\\\{[^}/?]+\??\\\}#', '[^/]+', preg_quote($route, '#'));

        return (bool) preg_match('#^'.$pattern.'$#', $path);
    }

    protected function normalizeId(?string $id): string
    {
        return Str::of((string) $id)
            ->replace('\\', '/')
            ->replace('.', '/')
            ->trim('/')
            ->lower()
            ->explode('/')
            ->map(fn (string $part): string => Str::slug($part))
            ->filter()
            ->implode('/');
    }

    protected function normalizeRoute(?string $route): string
    {
        $route = trim(str_replace('\\', '/', (string) $route));

        return $route === '' || $route === '/' ? '/' : '/'.trim($route, '/');
    }
}
