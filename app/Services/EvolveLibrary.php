<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class EvolveLibrary
{
    public function all(): array
    {
        $manifest = $this->manifest();

        return [
            'styles' => $this->readStyles($manifest['styles'] ?? []),
            'components' => $this->readGroup('component', $manifest['components'] ?? []),
            'forms' => $this->readGroup('form', $manifest['forms'] ?? []),
            'layouts' => $this->readGroup('layout', $manifest['layouts'] ?? []),
            'pages' => $this->readGroup('page', $manifest['pages'] ?? []),
        ];
    }

    public function write(array $payload): void
    {
        $previous = $this->manifest();

        $manifest = [
            'styles' => $this->writeStyles($payload['styles'] ?? [], $previous['styles'] ?? []),
            'components' => $this->writeGroup('component', $payload['components'] ?? [], $previous['components'] ?? []),
            'forms' => $this->writeGroup('form', $payload['forms'] ?? [], $previous['forms'] ?? []),
            'layouts' => $this->writeGroup('layout', $payload['layouts'] ?? [], $previous['layouts'] ?? []),
            'pages' => $this->writeGroup('page', $payload['pages'] ?? [], $previous['pages'] ?? []),
        ];

        $this->writeFile($this->manifestPath(), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    public function writeArtifact(string $kind, string $id, array $artifact): void
    {
        $group = $this->groupKey($kind);
        $payload = $this->all();
        $replaced = false;

        $payload[$group] = collect($payload[$group] ?? [])
            ->map(function (array $existing) use ($id, $artifact, &$replaced) {
                if (($existing['id'] ?? '') !== $id) {
                    return $existing;
                }

                $replaced = true;

                return $artifact;
            })
            ->values()
            ->all();

        if (! $replaced) {
            $payload[$group][] = $artifact;
        }

        $this->write($payload);
    }

    public function deleteArtifact(string $kind, string $id): void
    {
        $group = $this->groupKey($kind);
        $payload = $this->all();

        $payload[$group] = collect($payload[$group] ?? [])
            ->reject(fn (array $artifact) => ($artifact['id'] ?? '') === $id)
            ->values()
            ->all();

        $this->write($payload);
    }

    public function orderStyles(array $ids): void
    {
        $payload = $this->all();
        $positions = array_flip(array_values($ids));

        $payload['styles'] = collect($payload['styles'])
            ->sortBy(fn (array $style, int $index) => $positions[$style['id'] ?? ''] ?? count($positions) + $index)
            ->values()
            ->all();

        $this->write($payload);
    }

    public function artifactRoutes(): array
    {
        return collect([
            ...collect($this->manifest()['pages'] ?? [])->map(fn (array $page) => [
                'route' => $this->safeRoute($page['route'] ?? $page['slug'] ?? '/'.($page['id'] ?? '')),
                'component' => 'pages::'.$this->componentName($page['id'] ?? ''),
            ]),
            ...collect($this->manifest()['forms'] ?? [])
                ->filter(fn (array $form) => filled($form['route'] ?? ''))
                ->map(fn (array $form) => [
                    'route' => $this->safeRoute($form['route'] ?? ''),
                    'component' => 'forms::'.$this->componentName($form['id'] ?? ''),
                ]),
        ])
            ->filter(fn (array $route) => $route['component'] !== 'pages::' && $route['component'] !== 'forms::')
            ->values()
            ->all();
    }

    public function pageRoutes(): array
    {
        return $this->artifactRoutes();
    }

    public function usage(string $kind, string $id): string
    {
        $key = match ($kind) {
            'component' => 'components',
            'form' => 'forms',
            'layout' => 'layouts',
            'page' => 'pages',
            default => '',
        };

        foreach ($this->manifest()[$key] ?? [] as $artifact) {
            if (($artifact['id'] ?? '') === $id) {
                return (string) ($artifact['usage'] ?? '');
            }
        }

        return '';
    }

    protected function groupKey(string $kind): string
    {
        return match ($kind) {
            'style' => 'styles',
            'component' => 'components',
            'form' => 'forms',
            'layout' => 'layouts',
            'page' => 'pages',
            default => throw new \InvalidArgumentException("Unsupported artifact kind [{$kind}]."),
        };
    }

    public function stylesheet(): string
    {
        $manifest = $this->manifest();
        $styles = [];

        foreach ($manifest['styles'] ?? [] as $artifact) {
            $id = $this->safeId($artifact['id'] ?? '');
            $style = $this->globalStyle($id);

            if ($id !== '' && trim($style) !== '') {
                $styles[] = "/* style: {$id} */\n".trim($style);
            }
        }

        foreach ($manifest['layouts'] ?? [] as $artifact) {
            $id = $this->safeId($artifact['id'] ?? '');
            $style = $this->layoutStyle($id);

            if ($id === '' || trim($style) === '') {
                continue;
            }

            $styles[] = "/* layout: {$id} */\n".trim($style);
        }

        return implode("\n\n", $styles)."\n";
    }

    protected function readStyles(array $entries): array
    {
        return collect($entries)
            ->map(function (array $entry) {
                $id = $this->safeId($entry['id'] ?? '');
                if ($id === '') {
                    return null;
                }

                return [
                    'id' => $id,
                    'kind' => 'style',
                    'name' => $entry['name'] ?? Str::headline(basename($id)),
                    'slug' => '',
                    'php' => '',
                    'blade' => '',
                    'style' => $this->globalStyle($id),
                    'usage' => '',
                    'path' => $entry['path'] ?? $this->relativeStylePath($id),
                    'source_path' => $this->relativeStylePath($id),
                    'component' => '',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function writeStyles(array $artifacts, array $previousEntries): array
    {
        $entries = [];
        $keep = [];

        foreach ($artifacts as $artifact) {
            $id = $this->idFromPath($artifact['path'] ?? $artifact['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $keep[] = $id;
            $this->writeFile($this->stylePath($id), rtrim((string) ($artifact['style'] ?? ''))."\n");
            $entries[] = [
                'id' => $id,
                'name' => (string) ($artifact['name'] ?? Str::headline(basename($id))),
                'path' => $this->relativeStylePath($id),
            ];
        }

        foreach ($previousEntries as $entry) {
            $id = $this->safeId($entry['id'] ?? '');
            if ($id !== '' && ! in_array($id, $keep, true)) {
                File::delete($this->stylePath($id));
            }
        }

        return $entries;
    }

    protected function readGroup(string $kind, array $entries): array
    {
        return collect($entries)
            ->map(function (array $entry) use ($kind) {
                $id = $this->safeId($entry['id'] ?? '');
                if ($id === '') {
                    return null;
                }

                return [
                    'id' => $id,
                    'kind' => $kind,
                    'name' => $entry['name'] ?? Str::headline(basename($id)),
                    ...($kind === 'form' ? ['route' => $this->safeRoute($entry['route'] ?? $entry['slug'] ?? '')] : []),
                    ...($kind === 'page' ? ['route' => $this->safeRoute($entry['route'] ?? $entry['slug'] ?? '')] : []),
                    'usage' => $entry['usage'] ?? '',
                    'path' => $entry['path'] ?? $this->relativePath($kind, $id),
                    'source_path' => $this->relativePath($kind, $id),
                    'component' => $this->componentReference($kind, $id),
                    ...($kind === 'layout'
                        ? ['php' => '', 'blade' => $this->parseLayout($this->filePath($kind, $id)), 'style' => $this->layoutStyle($id)]
                        : $this->parseSfc($this->filePath($kind, $id))),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function writeGroup(string $kind, array $artifacts, array $previousEntries): array
    {
        $entries = [];
        $keep = [];

        foreach ($artifacts as $artifact) {
            $id = match ($kind) {
                'form' => $this->idFromPath($artifact['path'] ?? $artifact['slug'] ?? '') ?: $this->safeId($artifact['id'] ?? ''),
                'page' => $this->idFromPath($artifact['path'] ?? $artifact['slug'] ?? '') ?: $this->safeId($artifact['id'] ?? ''),
                default => $this->idFromPath($artifact['path'] ?? $artifact['id'] ?? ''),
            };
            if ($id === '') {
                continue;
            }

            $keep[] = $id;
            if ($kind === 'layout') {
                $this->writeFile($this->filePath($kind, $id), trim((string) ($artifact['blade'] ?? '{{ $slot }}'))."\n");
                $this->writeFile($this->layoutStylePath($id), trim((string) ($artifact['style'] ?? ''))."\n");
            } else {
                $this->writeFile($this->filePath($kind, $id), $this->serializeSfc(
                    (string) ($artifact['php'] ?? $this->defaultPhp()),
                    (string) ($artifact['blade'] ?? '<div></div>'),
                    (string) ($artifact['style'] ?? ''),
                    $kind === 'page'
                ));
            }

            $entry = [
                'id' => $id,
                'name' => (string) ($artifact['name'] ?? Str::headline(basename($id))),
            ];

            if (in_array($kind, ['component', 'layout'], true)) {
                $entry['path'] = $this->relativePath($kind, $id);
            }

            if ($kind === 'page') {
                $entry['path'] = $this->relativePath($kind, $id);
                $entry['route'] = $this->safeRoute($artifact['route'] ?? $artifact['slug'] ?? '/'.$id);
                $entry['usage'] = (string) ($artifact['usage'] ?? '');
            }

            if ($kind === 'form') {
                $entry['path'] = $this->relativePath($kind, $id);
                $entry['route'] = $this->safeRoute($artifact['route'] ?? $artifact['slug'] ?? '');
            }

            if (in_array($kind, ['component', 'form', 'layout'], true)) {
                $entry['usage'] = (string) ($artifact['usage'] ?? '');
            }

            $entries[] = $entry;
        }

        $this->deleteMissing($kind, $keep, $previousEntries);

        return $entries;
    }

    protected function parseSfc(string $path): array
    {
        $content = File::exists($path) ? File::get($path) : '';
        $php = '';

        if (preg_match('/^\s*<\?php\s*([\s\S]*?)\s*\?>\s*/', $content, $match)) {
            $php = trim($match[1]);
            $content = substr($content, strlen($match[0]));
        }

        $style = '';
        if (preg_match('/\s*<style\b[^>]*>\s*([\s\S]*?)\s*<\/style>\s*$/i', $content, $match)) {
            $style = trim($match[1]);
            $content = substr($content, 0, -strlen($match[0]));
        }

        return [
            'php' => $php,
            'blade' => trim($content),
            'style' => $style,
        ];
    }

    protected function parseLayout(string $path): string
    {
        return trim(File::exists($path) ? File::get($path) : '');
    }

    protected function serializeSfc(string $php, string $blade, string $style, bool $globalStyle = false): string
    {
        $source = "<?php\n\n".trim($php)."\n?>\n\n".trim($blade)."\n";
        $style = trim($style);

        if ($style !== '') {
            $tag = $globalStyle ? 'style global' : 'style';
            $source .= "\n<{$tag}>\n{$style}\n</style>\n";
        }

        return $source;
    }

    protected function manifest(): array
    {
        if (! File::exists($this->manifestPath())) {
            return $this->emptyManifest();
        }

        $decoded = json_decode(File::get($this->manifestPath()), true);

        return is_array($decoded) ? array_replace($this->emptyManifest(), $decoded) : $this->emptyManifest();
    }

    protected function emptyManifest(): array
    {
        return ['styles' => [], 'components' => [], 'forms' => [], 'layouts' => [], 'pages' => []];
    }

    protected function writeFile(string $path, string $content): void
    {
        File::ensureDirectoryExists(dirname($path));

        if (! File::exists($path) || File::get($path) !== $content) {
            File::put($path, $content);
        }
    }

    protected function deleteMissing(string $kind, array $keep, array $previousEntries): void
    {
        foreach ($previousEntries as $entry) {
            $id = $this->safeId($entry['id'] ?? '');

            if ($id !== '' && ! in_array($id, $keep, true)) {
                File::delete($this->filePath($kind, $id));
                if ($kind === 'layout') {
                    File::delete($this->layoutStylePath($id));
                }
            }
        }
    }

    protected function manifestPath(): string
    {
        return resource_path('evolve/manifest.json');
    }

    protected function globalStyle(string $id): string
    {
        $path = $this->stylePath($id);

        return File::exists($path) ? trim(File::get($path)) : '';
    }

    protected function stylePath(string $id): string
    {
        return resource_path('css/'.$this->safeId($id).'.css');
    }

    protected function relativeStylePath(string $id): string
    {
        return 'resources/css/'.$this->safeId($id).'.css';
    }

    protected function layoutStyle(string $id): string
    {
        $path = $this->layoutStylePath($id);

        return File::exists($path) ? trim(File::get($path)) : '';
    }

    protected function layoutStylePath(string $id): string
    {
        return resource_path('css/layouts/'.$this->safeId($id).'.css');
    }

    protected function filePath(string $kind, string $id): string
    {
        return $this->rootFor($kind).'/'.$this->safeId($id).'.blade.php';
    }

    protected function rootFor(string $kind): string
    {
        return match ($kind) {
            'form' => resource_path('views/forms'),
            'layout' => resource_path('views/layouts'),
            'page' => resource_path('views/pages'),
            default => resource_path('views/components'),
        };
    }

    protected function relativePath(string $kind, string $id): string
    {
        return match ($kind) {
            'form' => 'resources/views/forms/'.$id.'.blade.php',
            'layout' => 'resources/views/layouts/'.$id.'.blade.php',
            'page' => 'resources/views/pages/'.$id.'.blade.php',
            default => 'resources/views/components/'.$id.'.blade.php',
        };
    }

    protected function componentReference(string $kind, string $id): string
    {
        return match ($kind) {
            'form' => 'forms::'.$this->componentName($id),
            'layout' => 'layouts::'.$this->componentName($id),
            'page' => 'pages::'.$this->componentName($id),
            default => $this->componentName($id),
        };
    }

    protected function componentName(string $id): string
    {
        return str_replace('/', '.', $this->safeId($id));
    }

    protected function idFromSlug(string $slug): string
    {
        return $this->safeId($this->safeSlug($slug));
    }

    protected function idFromPath(string $path): string
    {
        $path = preg_replace('#\.(blade\.php|css)$#', '', str_replace('\\', '/', trim($path)));
        $path = preg_replace('#^(resources/views/(components|forms|layouts|pages)/|resources/css/layouts/|resources/css/)#', '', $path);

        return $this->safeId($path);
    }

    protected function safeId(string $id): string
    {
        $id = strtolower(str_replace('\\', '/', trim($id)));
        $id = trim(preg_replace('#/+#', '/', $id), '/');
        $id = collect(explode('/', $id))->map(fn ($part) => Str::slug($part))->filter()->implode('/');

        return str_contains($id, '..') ? '' : $id;
    }

    protected function safeSlug(string $slug): string
    {
        return $this->safePath($slug);
    }

    protected function safePath(string $path): string
    {
        $path = strtolower(str_replace('\\', '/', trim($path)));
        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/'.trim(preg_replace('#/+#', '/', preg_replace('#[^a-z0-9/-]#', '-', $path)), '/-');
    }

    protected function safeRoute(string $route): string
    {
        $route = strtolower(str_replace('\\', '/', trim($route)));
        if ($route === '' || $route === '/') {
            return '/';
        }

        $route = preg_replace('#[^a-z0-9/_{}?-]#', '-', $route);

        return '/'.trim(preg_replace('#/+#', '/', $route), '/-');
    }

    protected function defaultPhp(): string
    {
        return "use Livewire\\Component;\n\nnew class extends Component {\n    //\n};";
    }
}
