<?php

namespace App\Services;

use App\Services\Concerns\GuardsWorkspacePaths;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EvolveLibrary
{
    use GuardsWorkspacePaths;

    public function all(): array
    {
        $manifest = $this->manifest();

        $inventory = [
            'styles' => $this->readStyles($manifest['styles'] ?? []),
            'components' => $this->readGroup('component', $manifest['components'] ?? []),
            'forms' => $this->readGroup('form', $manifest['forms'] ?? []),
            'layouts' => $this->readGroup('layout', $manifest['layouts'] ?? []),
            'pages' => $this->readGroup('page', $manifest['pages'] ?? []),
            'snippets' => $this->readGroup('snippet', $manifest['snippets'] ?? []),
            'views' => $this->readViews($manifest['views'] ?? []),
        ];

        return [
            ...$inventory,
            'surfaces' => $this->surfaces($inventory),
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
            'snippets' => $this->writeGroup('snippet', $payload['snippets'] ?? [], $previous['snippets'] ?? []),
            'views' => $this->writeViews($payload['views'] ?? null, $previous['views'] ?? []),
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
            ...collect($this->normalizePageTree($this->manifest()['pages'] ?? []))->map(function (array $page) {
                $route = $this->safeOptionalRoute($page['route'] ?? $page['slug'] ?? '');

                return [
                    'route' => $route,
                    'route_name' => $route !== '' ? $this->resolveRouteName($page['route_name'] ?? null, $route) : '',
                    'middleware' => $this->safeMiddleware($page['middleware'] ?? []),
                    'component' => 'pages::'.$this->componentName($page['id'] ?? ''),
                ];
            }),
            ...collect($this->manifest()['forms'] ?? [])
                ->filter(fn (array $form) => filled($form['route'] ?? ''))
                ->map(function (array $form) {
                    $route = $this->safeRoute($form['route'] ?? '');

                    return [
                        'route' => $route,
                        'route_name' => $this->resolveRouteName($form['route_name'] ?? null, $route),
                        'middleware' => $this->safeMiddleware($form['middleware'] ?? []),
                        'component' => 'forms::'.$this->componentName($form['id'] ?? ''),
                    ];
                }),
            ...collect($this->manifest()['views'] ?? [])
                ->map(function (array $view) {
                    $route = $this->safeOptionalRoute($view['route'] ?? '');
                    $id = $this->safeId($view['id'] ?? '');

                    return [
                        'route' => $route,
                        'route_name' => $route !== '' ? $this->resolveRouteName($view['route_name'] ?? null, $route) : '',
                        'middleware' => $this->safeMiddleware($view['middleware'] ?? []),
                        'component' => 'views::'.$this->componentName($id),
                        'kind' => 'view',
                        'view' => str_replace('/', '.', $id),
                    ];
                }),
        ])
            ->filter(fn (array $route) => $route['route'] !== '' && ! in_array($route['component'], ['pages::', 'forms::', 'views::'], true))
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
            'snippet' => 'snippets',
            'view' => 'views',
            default => '',
        };

        foreach ($this->manifest()[$key] ?? [] as $artifact) {
            if (($artifact['id'] ?? '') === $id) {
                return (string) ($artifact['usage'] ?? '');
            }
        }

        if ($kind === 'view') {
            return "@include('".str_replace('/', '.', $this->safeId($id))."')";
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
            'snippet' => 'snippets',
            'view' => 'views',
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

    protected function surfaces(array $inventory): array
    {
        return [
            'website' => $this->surface($inventory, ['website']),
            'app_shell' => $this->surface($inventory, ['website', 'app_shell']),
            'developer' => $this->surface($inventory, ['website', 'app_shell', 'developer']),
        ];
    }

    protected function surface(array $inventory, array $surfaces): array
    {
        $projected = [];

        foreach (['styles', 'layouts', 'pages', 'snippets', 'components', 'forms', 'views'] as $group) {
            $projected[$group] = collect($inventory[$group] ?? [])
                ->filter(fn (array $artifact): bool => ($artifact['visibility'] ?? 'default') !== 'hidden')
                ->filter(fn (array $artifact): bool => in_array($artifact['surface'] ?? 'website', $surfaces, true))
                ->values()
                ->all();
        }

        return $projected;
    }

    protected function artifactClassification(string $kind, string $id): array
    {
        $id = $this->safeId($id);

        if ($this->isWorkbenchInternalArtifact($kind, $id)) {
            return [
                'origin' => 'workbench',
                'lifecycle' => 'workbench',
                'surface' => 'internal',
                'visibility' => 'hidden',
                'role' => $this->artifactRole($kind, $id),
                'is_hidden' => true,
            ];
        }

        $origin = $this->isStarterKitArtifact($kind, $id) ? 'starter_kit' : 'project';
        $surface = 'website';
        $visibility = 'default';

        if ($kind === 'view' && $this->viewRole($id) === 'kit_internal') {
            $surface = 'developer';
            $visibility = 'hidden';
        } elseif ($origin === 'starter_kit' && $kind === 'view' && $this->isAppShellPartialView($id)) {
            $surface = 'app_shell';
            $visibility = 'advanced';
        } elseif ($origin === 'starter_kit' && $kind === 'page' && (str_starts_with($id, 'auth/') || str_starts_with($id, 'settings/'))) {
            $surface = filled($this->starterKitRouteMetadata($kind, $id)['route'] ?? '') ? 'app_shell' : 'developer';
            $visibility = 'advanced';
        } elseif ($origin === 'starter_kit' && (
            $kind === 'layout' && ($id === 'auth' || $id === 'app' || str_starts_with($id, 'auth/') || str_starts_with($id, 'app/'))
        )) {
            $surface = 'app_shell';
            $visibility = 'advanced';
        } elseif ($origin === 'starter_kit' && in_array($kind, ['component'], true)) {
            $surface = 'developer';
            $visibility = 'advanced';
        }

        return [
            'origin' => $origin,
            'lifecycle' => $surface === 'website' ? 'website' : 'developer',
            'surface' => $surface,
            'visibility' => $visibility,
            'role' => $this->artifactRole($kind, $id),
            'is_hidden' => $visibility === 'hidden',
        ];
    }

    protected function artifactRole(string $kind, string $id): string
    {
        return $kind === 'view' ? $this->viewRole($id) : $kind;
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
                    'is_starter_kit' => $this->isStarterKitArtifact('style', $id),
                    'has_original' => $this->hasStarterKitOriginal('style', $id),
                    ...$this->artifactClassification('style', $id),
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

            $this->assertArtifactCanBeChanged('style', $id);

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
                $this->assertArtifactCanBeChanged('style', $id);

                $this->deleteFile($this->stylePath($id));
            }
        }

        return $entries;
    }

    protected function readViews(array $entries): array
    {
        $manifestById = collect($entries)
            ->filter(fn ($entry): bool => is_array($entry) && filled($entry['id'] ?? ''))
            ->keyBy(fn (array $entry): string => $this->safeId($entry['id']))
            ->all();

        $discovered = $this->discoverViewIds();
        $ids = collect($discovered)->merge(array_keys($manifestById))->unique()->values();

        return $ids
            ->map(function (string $id) use ($manifestById): ?array {
                if ($id === '') {
                    return null;
                }

                $entry = $manifestById[$id] ?? [];
                $path = $this->filePath('view', $id);
                $relative = $this->relativePath('view', $id);

                $viewRole = $this->viewRole($id);
                $routeMetadata = $this->viewRouteMetadata($entry, $id);

                return [
                    'id' => $id,
                    'kind' => 'view',
                    'name' => $entry['name'] ?? Str::headline(basename($id)),
                    'is_starter_kit' => $this->isStarterKitArtifact('view', $id),
                    'has_original' => $this->hasStarterKitOriginal('view', $id),
                    ...$this->artifactClassification('view', $id),
                    'view_role' => $viewRole,
                    'is_hidden' => $viewRole === 'kit_internal',
                    'metadata' => is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [],
                    'usage' => $entry['usage'] ?? "@include('".str_replace('/', '.', $id)."')",
                    ...$routeMetadata,
                    'path' => $relative,
                    'source_path' => $relative,
                    'component' => $this->componentReference('view', $id),
                    'php' => '',
                    'blade' => File::exists($path) ? File::get($path) : '',
                    'style' => '',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function mergeDiscoveredStarterKitEntries(string $kind, array $entries): array
    {
        if (! in_array($kind, ['component', 'layout', 'page'], true)) {
            return $entries;
        }

        $existingIds = collect($entries)
            ->filter(fn ($entry): bool => is_array($entry))
            ->map(fn (array $entry): string => $this->safeId((string) ($entry['id'] ?? $this->idFromPath((string) ($entry['path'] ?? '')))))
            ->filter()
            ->flip();

        foreach ($this->discoverStarterKitEntries($kind) as $entry) {
            if (! isset($existingIds[$entry['id']])) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    protected function discoverStarterKitEntries(string $kind): array
    {
        $root = $this->rootFor($kind);

        if (! File::isDirectory($root)) {
            return [];
        }

        return collect(File::allFiles($root))
            ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.blade.php'))
            ->map(function ($file) use ($kind, $root): ?array {
                $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');
                $path = rtrim($this->relativeRootFor($kind), '/').'/'.$relative;
                $id = $this->idFromPath($path);

                if ($id === '' || ! $this->isStarterKitArtifact($kind, $id)) {
                    return null;
                }

                return [
                    'id' => $id,
                    'name' => Str::headline(basename($id)),
                    'path' => $path,
                    ...$this->starterKitRouteMetadata($kind, $id),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function starterKitRouteMetadata(string $kind, string $id): array
    {
        if ($kind === 'view') {
            return match ($id) {
                'dashboard' => ['route' => '/dashboard', 'route_name' => 'dashboard', 'middleware' => ['auth', 'verified']],
                'welcome' => ['route' => '/', 'route_name' => 'home', 'middleware' => []],
                default => ['route' => '', 'route_name' => '', 'middleware' => []],
            };
        }

        if ($kind !== 'page') {
            return [];
        }

        $routes = [
            'auth/login' => ['route' => '/login', 'route_name' => 'login', 'middleware' => ['guest']],
            'auth/register' => ['route' => '/register', 'route_name' => 'register', 'middleware' => ['guest']],
            'auth/forgot-password' => ['route' => '/forgot-password', 'route_name' => 'password.request', 'middleware' => ['guest']],
            'auth/reset-password' => ['route' => '/reset-password/{token}', 'route_name' => 'password.reset', 'middleware' => ['guest']],
            'auth/confirm-password' => ['route' => '/confirm-password', 'route_name' => 'password.confirm', 'middleware' => ['auth']],
            'auth/two-factor-challenge' => ['route' => '/two-factor-challenge', 'route_name' => 'two-factor.login', 'middleware' => ['guest']],
            'auth/verify-email' => ['route' => '/email/verify', 'route_name' => 'verification.notice', 'middleware' => ['auth']],
            'settings/profile' => ['route' => '/settings/profile', 'route_name' => 'profile.edit', 'middleware' => ['auth']],
            'settings/appearance' => ['route' => '/settings/appearance', 'route_name' => 'appearance.edit', 'middleware' => ['auth', 'verified']],
            'settings/security' => ['route' => '/settings/security', 'route_name' => 'security.edit', 'middleware' => ['auth', 'verified', 'password.confirm']],
        ];

        return $routes[$id] ?? [
            'route' => '',
            'route_name' => '',
            'middleware' => [],
        ];
    }

    protected function viewRouteMetadata(array $entry, string $id): array
    {
        $defaults = $this->starterKitRouteMetadata('view', $id);
        $hasRoute = array_key_exists('route', $entry);
        $route = $hasRoute
            ? $this->safeOptionalRoute($entry['route'] ?? '')
            : (string) ($defaults['route'] ?? '');

        return [
            'route' => $route,
            'route_name' => $route !== ''
                ? $this->resolveRouteName($entry['route_name'] ?? $defaults['route_name'] ?? null, $route)
                : '',
            'middleware' => $route !== ''
                ? $this->safeMiddleware($entry['middleware'] ?? $defaults['middleware'] ?? [])
                : [],
        ];
    }

    protected function writeViews(?array $artifacts, array $previousEntries): array
    {
        if ($artifacts === null) {
            return $previousEntries;
        }

        $entries = [];
        $keep = [];

        foreach ($artifacts as $artifact) {
            $rawPath = (string) ($artifact['path'] ?? $artifact['source_path'] ?? '');
            $id = $rawPath !== ''
                ? $this->viewIdFromPath($rawPath)
                : $this->safeId((string) ($artifact['id'] ?? ''));

            if ($id === '') {
                continue;
            }

            $this->assertArtifactCanBeChanged('view', $id);

            $keep[] = $id;
            $this->writeFile($this->filePath('view', $id), trim((string) ($artifact['blade'] ?? '<div></div>'))."\n");

            $entry = [
                'id' => $id,
                'name' => (string) ($artifact['name'] ?? Str::headline(basename($id))),
                'path' => $this->relativePath('view', $id),
            ];

            if (is_array($artifact['metadata'] ?? null) && $artifact['metadata'] !== []) {
                $entry['metadata'] = $artifact['metadata'];
            }

            if (filled($artifact['usage'] ?? '')) {
                $entry['usage'] = (string) $artifact['usage'];
            }

            if (array_key_exists('route', $artifact)) {
                $entry['route'] = $this->safeOptionalRoute($artifact['route'] ?? '');
                $entry['route_name'] = $entry['route'] !== ''
                    ? $this->resolveRouteName($artifact['route_name'] ?? null, $entry['route'])
                    : '';
                $entry['middleware'] = $entry['route'] !== '' ? $this->safeMiddleware($artifact['middleware'] ?? []) : [];
            }

            $entries[] = $entry;
        }

        $this->deleteMissingViews($keep, $previousEntries);

        return $entries;
    }

    protected function deleteMissingViews(array $keep, array $previousEntries): void
    {
        foreach ($previousEntries as $entry) {
            $id = $this->safeId($entry['id'] ?? '');

            if ($id === '' || in_array($id, $keep, true)) {
                continue;
            }

            $this->assertArtifactCanBeChanged('view', $id);
            $this->deleteFile($this->filePath('view', $id));
        }
    }

    protected function discoverViewIds(): array
    {
        $root = resource_path('views');

        if (! File::isDirectory($root)) {
            return [];
        }

        $excludedDirs = ['components', 'forms', 'layouts', 'pages', 'snippets', 'evolve'];
        $excludedFiles = ['workbench'];

        return collect(File::allFiles($root))
            ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.blade.php'))
            ->map(function ($file) use ($root): string {
                $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');

                return preg_replace('/\.blade\.php$/', '', $relative);
            })
            ->reject(function (string $id) use ($excludedDirs, $excludedFiles): bool {
                if (in_array($id, $excludedFiles, true)) {
                    return true;
                }

                foreach ($excludedDirs as $dir) {
                    if (str_starts_with($id, $dir.'/')) {
                        return true;
                    }
                }

                return false;
            })
            ->map(fn (string $id): string => $this->safeId($id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function viewIdFromPath(string $path): string
    {
        $path = preg_replace('/\.blade\.php$/', '', str_replace('\\', '/', trim($path)));
        $path = preg_replace('#^resources/views/#', '', $path);

        return $this->safeId(trim((string) $path, '/'));
    }

    protected function readGroup(string $kind, array $entries): array
    {
        $entries = $this->mergeDiscoveredStarterKitEntries($kind, $entries);

        if ($kind === 'page') {
            $entries = $this->normalizePageTree($entries);
        }

        return collect($entries)
            ->map(function (array $entry) use ($kind) {
                $id = $this->safeId($entry['id'] ?? '');
                if ($id === '') {
                    return null;
                }

                $sourcePath = $this->relativeSourcePath($kind, $id, $entry);
                $absoluteSourcePath = $this->sourceFilePath($kind, $id, $sourcePath);

                return [
                    'id' => $id,
                    'kind' => $kind,
                    'name' => $entry['name'] ?? Str::headline(basename($id)),
                    'is_starter_kit' => $this->isStarterKitArtifact($kind, $id),
                    'has_original' => $this->hasStarterKitOriginal($kind, $id),
                    ...$this->artifactClassification($kind, $id),
                    ...($kind === 'form' ? (function () use ($entry) {
                        $route = $this->safeRoute($entry['route'] ?? $entry['slug'] ?? '');
                        $hasRoute = filled($entry['route'] ?? $entry['slug'] ?? '');

                        return [
                            'route' => $route,
                            'route_name' => $hasRoute
                                ? $this->resolveRouteName($entry['route_name'] ?? null, $route)
                                : '',
                            'middleware' => $hasRoute ? $this->safeMiddleware($entry['middleware'] ?? []) : [],
                        ];
                    })() : []),
                    ...($kind === 'page' ? (function () use ($entry) {
                        $route = $this->safeOptionalRoute($entry['route'] ?? $entry['slug'] ?? '');

                        return [
                            'route' => $route,
                            'route_name' => $route !== '' ? $this->resolveRouteName($entry['route_name'] ?? null, $route) : '',
                            'middleware' => $this->safeMiddleware($entry['middleware'] ?? []),
                            'parent_id' => $this->safeId($entry['parent_id'] ?? ''),
                            'order' => (int) ($entry['order'] ?? 0),
                            'depth' => (int) ($entry['depth'] ?? 0),
                        ];
                    })() : []),
                    'metadata' => is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [],
                    'usage' => $entry['usage'] ?? '',
                    'path' => $sourcePath,
                    'source_path' => $sourcePath,
                    'component' => $this->componentReference($kind, $id),
                    ...(in_array($kind, ['layout', 'snippet'], true)
                        ? ['php' => '', 'blade' => $this->parseLayout($absoluteSourcePath), 'style' => $kind === 'layout' ? $this->layoutStyle($id) : '']
                        : $this->parseSfc($absoluteSourcePath)),
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

            $targetPath = $this->targetFilePath($kind, $id, $artifact);
            $relativeTargetPath = $this->relativeTargetPath($kind, $id, $artifact);

            $this->assertArtifactCanBeChanged($kind, $id, [$targetPath]);

            $keep[] = $id;
            if (in_array($kind, ['layout', 'snippet'], true)) {
                $this->writeFile($targetPath, trim((string) ($artifact['blade'] ?? '{{ $slot }}'))."\n");
                if ($kind === 'layout') {
                    $this->writeFile($this->layoutStylePath($id), trim((string) ($artifact['style'] ?? ''))."\n");
                }
            } else {
                $this->writeFile($targetPath, $this->serializeSfc(
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

            if (in_array($kind, ['component', 'layout', 'snippet'], true)) {
                $entry['path'] = $relativeTargetPath;
            }

            if ($kind === 'page') {
                $entry['path'] = $relativeTargetPath;
                $hasExplicitRoute = array_key_exists('route', $artifact) || array_key_exists('slug', $artifact);
                $entry['route'] = $hasExplicitRoute
                    ? $this->safeOptionalRoute($artifact['route'] ?? $artifact['slug'] ?? '')
                    : $this->safeRoute('/'.$id);
                $entry['route_name'] = $entry['route'] !== '' ? $this->resolveRouteName($artifact['route_name'] ?? null, $entry['route']) : '';
                $entry['middleware'] = $this->safeMiddleware($artifact['middleware'] ?? []);
                $entry['parent_id'] = $this->safeId($artifact['parent_id'] ?? '');
                $entry['order'] = max(0, (int) ($artifact['order'] ?? count($entries) + 1));
                $entry['usage'] = (string) ($artifact['usage'] ?? '');
            }

            if ($kind === 'form') {
                $entry['path'] = $relativeTargetPath;
                $entry['route'] = $this->safeRoute($artifact['route'] ?? $artifact['slug'] ?? '');
                $hasRoute = filled($artifact['route'] ?? $artifact['slug'] ?? '');
                $entry['route_name'] = $hasRoute
                    ? $this->resolveRouteName($artifact['route_name'] ?? null, $entry['route'])
                    : '';
                $entry['middleware'] = $hasRoute ? $this->safeMiddleware($artifact['middleware'] ?? []) : [];
            }

            if (in_array($kind, ['component', 'form', 'layout', 'snippet'], true)) {
                $entry['usage'] = (string) ($artifact['usage'] ?? '');
            }

            if (is_array($artifact['metadata'] ?? null) && $artifact['metadata'] !== []) {
                $entry['metadata'] = $artifact['metadata'];
            }

            $entries[] = $entry;
        }

        if ($kind === 'page') {
            $entries = $this->normalizePageTree($entries);
        }

        $this->deleteMissing($kind, $keep, $previousEntries);

        return $entries;
    }

    protected function normalizePageTree(array $entries): array
    {
        $ids = collect($entries)
            ->pluck('id')
            ->map(fn (mixed $id): string => $this->safeId((string) $id))
            ->filter()
            ->values()
            ->all();

        $entries = collect($entries)
            ->map(function (array $entry, int $index) use ($ids): array {
                $id = $this->safeId($entry['id'] ?? '');
                $parentId = $this->safeId($entry['parent_id'] ?? '');

                if ($parentId === $id || ! in_array($parentId, $ids, true)) {
                    $parentId = '';
                }

                return [
                    ...$entry,
                    'id' => $id,
                    'parent_id' => $parentId,
                    'order' => max(0, (int) ($entry['order'] ?? $index + 1)),
                ];
            })
            ->filter(fn (array $entry): bool => $entry['id'] !== '')
            ->values();

        $byId = $entries->keyBy('id');
        $entries = $entries->map(function (array $entry) use ($byId): array {
            if ($entry['parent_id'] !== '' && $this->pageParentCreatesCycle($entry['id'], $entry['parent_id'], $byId->all())) {
                $entry['parent_id'] = '';
            }

            return $entry;
        });

        $children = $entries
            ->groupBy(fn (array $entry): string => $entry['parent_id'] ?: '__root__')
            ->map(fn ($items) => $items
                ->sortBy([
                    ['order', 'asc'],
                    ['name', 'asc'],
                    ['id', 'asc'],
                ])
                ->values());

        return $this->flattenPageChildren($children)->all();
    }

    protected function pageParentCreatesCycle(string $id, string $parentId, array $entriesById): bool
    {
        $seen = [$id => true];

        while ($parentId !== '') {
            if (isset($seen[$parentId])) {
                return true;
            }

            $seen[$parentId] = true;
            $parentId = $this->safeId($entriesById[$parentId]['parent_id'] ?? '');
        }

        return false;
    }

    protected function flattenPageChildren($children, string $parentId = '__root__', int $depth = 0)
    {
        return collect($children[$parentId] ?? [])
            ->flatMap(function (array $entry) use ($children, $depth) {
                $entry['depth'] = $depth;

                return collect([$entry])->merge($this->flattenPageChildren($children, $entry['id'], $depth + 1));
            })
            ->values();
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
        return ['styles' => [], 'components' => [], 'forms' => [], 'layouts' => [], 'pages' => [], 'snippets' => []];
    }

    protected function writeFile(string $path, string $content): void
    {
        $this->assertPathInsideWorkspace($path);
        File::ensureDirectoryExists(dirname($path));
        $this->assertPathInsideWorkspace(dirname($path));

        if (File::exists($path) || is_link($path)) {
            $this->assertPathInsideWorkspace($path);
        }

        if (! File::exists($path) || File::get($path) !== $content) {
            File::put($path, $content);
        }
    }

    protected function deleteFile(string $path): void
    {
        $this->assertPathInsideWorkspace($path);

        if (File::exists($path) || is_link($path)) {
            $this->assertPathInsideWorkspace($path);
        }

        File::delete($path);
    }

    protected function deleteMissing(string $kind, array $keep, array $previousEntries): void
    {
        foreach ($previousEntries as $entry) {
            $id = $this->safeId($entry['id'] ?? '');

            if ($id !== '' && ! in_array($id, $keep, true)) {
                $targetPath = $this->sourceFilePath($kind, $id, $this->relativeSourcePath($kind, $id, $entry));

                $this->assertArtifactCanBeChanged($kind, $id, [$targetPath]);

                $this->deleteFile($targetPath);
                if ($kind === 'layout') {
                    $this->deleteFile($this->layoutStylePath($id));
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
            'snippet' => resource_path('views/snippets'),
            'view' => resource_path('views'),
            default => resource_path('views/components'),
        };
    }

    protected function relativeRootFor(string $kind): string
    {
        return match ($kind) {
            'form' => 'resources/views/forms',
            'layout' => 'resources/views/layouts',
            'page' => 'resources/views/pages',
            'snippet' => 'resources/views/snippets',
            'view' => 'resources/views',
            default => 'resources/views/components',
        };
    }

    protected function relativePath(string $kind, string $id): string
    {
        return match ($kind) {
            'form' => 'resources/views/forms/'.$id.'.blade.php',
            'layout' => 'resources/views/layouts/'.$id.'.blade.php',
            'page' => 'resources/views/pages/'.$id.'.blade.php',
            'snippet' => 'resources/views/snippets/'.$id.'.blade.php',
            'view' => 'resources/views/'.$id.'.blade.php',
            default => 'resources/views/components/'.$id.'.blade.php',
        };
    }

    protected function relativeSourcePath(string $kind, string $id, array $entry): string
    {
        $path = (string) ($entry['path'] ?? $entry['source_path'] ?? '');

        if ($this->validRelativeArtifactPath($kind, $id, $path)) {
            return str_replace('\\', '/', trim($path));
        }

        return $this->relativePath($kind, $id);
    }

    protected function relativeTargetPath(string $kind, string $id, array $artifact): string
    {
        $path = (string) ($artifact['path'] ?? $artifact['source_path'] ?? '');

        if ($this->validRelativeArtifactPath($kind, $id, $path)) {
            return str_replace('\\', '/', trim($path));
        }

        return $this->relativePath($kind, $id);
    }

    protected function sourceFilePath(string $kind, string $id, string $relativePath): string
    {
        if (! $this->validRelativeArtifactPath($kind, $id, $relativePath)) {
            return $this->filePath($kind, $id);
        }

        return base_path(str_replace('\\', '/', trim($relativePath)));
    }

    protected function targetFilePath(string $kind, string $id, array $artifact): string
    {
        return $this->sourceFilePath($kind, $id, $this->relativeTargetPath($kind, $id, $artifact));
    }

    protected function validRelativeArtifactPath(string $kind, string $id, string $path): bool
    {
        $path = str_replace('\\', '/', trim($path));

        return $path !== ''
            && str_starts_with($path, rtrim($this->relativeRootFor($kind), '/').'/')
            && str_ends_with($path, '.blade.php')
            && $this->idFromPath($path) === $this->safeId($id);
    }

    protected function componentReference(string $kind, string $id): string
    {
        return match ($kind) {
            'form' => 'forms::'.$this->componentName($id),
            'layout' => 'layouts::'.$this->componentName($id),
            'page' => 'pages::'.$this->componentName($id),
            'snippet' => 'snippets::'.$this->componentName($id),
            'view' => $this->componentName($id),
            default => $this->componentName($id),
        };
    }

    protected function assertArtifactCanBeChanged(string $kind, string $id, array $sourcePaths = []): void
    {
        if ($this->isWorkbenchInternalArtifact($kind, $id)) {
            throw ValidationException::withMessages([
                'path' => "The workbench cannot modify protected {$kind} artifact [{$id}]; it is required by the workbench itself.",
            ]);
        }

        if ($this->isStarterKitArtifact($kind, $id)) {
            $this->snapshotStarterKitOriginal($kind, $id, $sourcePaths);
        }
    }

    public function viewRole(string $id): string
    {
        $id = $this->safeId($id);

        if (str_starts_with($id, 'flux/')) {
            return 'kit_internal';
        }

        if (str_starts_with($id, 'partials/')) {
            return 'partial';
        }

        return 'page';
    }

    protected function isAppShellPartialView(string $id): bool
    {
        $id = $this->safeId($id);

        return $id === 'partials/head' || str_starts_with($id, 'partials/settings-');
    }

    public function isWorkbenchInternalArtifact(string $kind, string $id): bool
    {
        $id = $this->safeId($id);

        if ($kind === 'style' && $id === 'app') {
            return true;
        }

        if ($kind === 'view') {
            return $id === 'workbench' || str_starts_with($id, 'evolve/');
        }

        return false;
    }

    public function isStarterKitArtifact(string $kind, string $id): bool
    {
        return $this->starterKitMatches($kind, $this->safeId($id));
    }

    public function hasStarterKitOriginal(string $kind, string $id): bool
    {
        $id = $this->safeId($id);

        if (! $this->isStarterKitArtifact($kind, $id)) {
            return false;
        }

        foreach ($this->originalSourcePaths($kind, $id) as $original) {
            if (File::exists($original)) {
                return true;
            }
        }

        return false;
    }

    public function restoreArtifactOriginal(string $kind, string $id): array
    {
        $id = $this->safeId($id);

        if (! $this->isStarterKitArtifact($kind, $id)) {
            throw ValidationException::withMessages([
                'id' => "{$kind} artifact [{$id}] is not a starter-kit artifact and has no original to restore.",
            ]);
        }

        if (! $this->hasStarterKitOriginal($kind, $id)) {
            throw ValidationException::withMessages([
                'id' => "No starter-kit original on file for {$kind} artifact [{$id}].",
            ]);
        }

        $restored = [];
        foreach ($this->originalSourcePaths($kind, $id) as $original) {
            if (! File::exists($original)) {
                continue;
            }

            $target = $this->originalTargetPath($kind, $id, $original);
            File::ensureDirectoryExists(dirname($target));
            File::copy($original, $target);
            $restored[] = $target;
        }

        return $restored;
    }

    protected function snapshotStarterKitOriginal(string $kind, string $id, array $sourcePaths = []): void
    {
        $id = $this->safeId($id);
        $sources = $sourcePaths !== [] ? $sourcePaths : $this->originalTargetSources($kind, $id);

        if ($kind === 'layout' && ! in_array($this->layoutStylePath($id), $sources, true)) {
            $sources[] = $this->layoutStylePath($id);
        }

        foreach ($sources as $source) {
            $snapshot = $this->originalSourcePathFor($kind, $id, $source);

            if (File::exists($snapshot) || ! File::exists($source)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($snapshot));
            File::copy($source, $snapshot);
        }
    }

    protected function originalsRoot(): string
    {
        return resource_path('evolve/originals');
    }

    protected function originalsDir(string $kind): string
    {
        $dir = match ($kind) {
            'style' => 'styles',
            'component' => 'components',
            'form' => 'forms',
            'layout' => 'layouts',
            'page' => 'pages',
            'snippet' => 'snippets',
            'view' => 'views',
            default => $kind,
        };

        return $this->originalsRoot().'/'.$dir;
    }

    protected function originalSourcePathFor(string $kind, string $id, string $livePath): string
    {
        $extension = pathinfo($livePath, PATHINFO_EXTENSION);
        $base = $extension === 'php' && str_ends_with($livePath, '.blade.php') ? 'blade.php' : $extension;
        $isLayoutStyle = $kind === 'layout' && str_ends_with($livePath, '.css');
        $subdir = $isLayoutStyle ? '/styles' : '';

        return $this->originalsDir($kind).$subdir.'/'.$id.'.'.$base;
    }

    protected function originalSourcePaths(string $kind, string $id): array
    {
        $paths = [$this->originalSourcePathFor($kind, $id, $this->filePath($kind, $id))];

        if ($kind === 'layout') {
            $paths[] = $this->originalSourcePathFor($kind, $id, $this->layoutStylePath($id));
        }

        return $paths;
    }

    protected function originalTargetSources(string $kind, string $id): array
    {
        $sources = [$this->filePath($kind, $id)];

        if ($kind === 'layout') {
            $sources[] = $this->layoutStylePath($id);
        }

        return $sources;
    }

    protected function originalTargetPath(string $kind, string $id, string $snapshot): string
    {
        if ($kind === 'layout' && str_contains($snapshot, '/styles/')) {
            return $this->layoutStylePath($id);
        }

        foreach ($this->discoverStarterKitEntries($kind) as $entry) {
            if (($entry['id'] ?? '') === $this->safeId($id) && filled($entry['path'] ?? '')) {
                return $this->sourceFilePath($kind, $id, (string) $entry['path']);
            }
        }

        return $this->filePath($kind, $id);
    }

    protected function starterKitMatches(string $kind, string $id): bool
    {
        $protected = match ($kind) {
            'style' => [
                'exact' => [],
                'prefixes' => [],
            ],
            'component' => [
                'exact' => [
                    'app-logo',
                    'app-logo-icon',
                    'auth-header',
                    'auth-session-status',
                    'desktop-user-menu',
                    'passkey-registration',
                    'passkey-verify',
                    'placeholder-pattern',
                ],
                'prefixes' => [],
            ],
            'layout' => [
                'exact' => ['app', 'auth'],
                'prefixes' => ['app/', 'auth/'],
            ],
            'page' => [
                'exact' => ['auth', 'settings'],
                'prefixes' => ['auth/', 'settings/'],
            ],
            'view' => [
                'exact' => ['dashboard', 'welcome'],
                'prefixes' => ['partials/', 'flux/'],
            ],
            default => [
                'exact' => [],
                'prefixes' => [],
            ],
        };

        return in_array($id, $protected['exact'], true)
            || collect($protected['prefixes'])->contains(fn (string $prefix): bool => str_starts_with($id, $prefix));
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
        $path = preg_replace('#^(resources/views/(components|forms|layouts|pages|snippets)/|resources/css/layouts/|resources/css/)#', '', $path);

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

    protected function safeOptionalRoute(string $route): string
    {
        return trim($route) === '' ? '' : $this->safeRoute($route);
    }

    protected function safeRouteName(string $name): string
    {
        $name = strtolower(trim($name));
        if ($name === '') {
            return '';
        }

        $name = preg_replace('#[^a-z0-9._-]#', '.', $name);

        return trim(preg_replace('#\.+#', '.', $name), '.');
    }

    protected function deriveRouteName(string $route): string
    {
        $route = $this->safeRoute($route);

        if ($route === '/') {
            return 'home';
        }

        $stripped = preg_replace('#\{([^}/?]+)\??\}#', '$1', $route);

        return str_replace('/', '.', trim($stripped, '/'));
    }

    protected function resolveRouteName(?string $explicit, string $route): string
    {
        $explicit = $this->safeRouteName((string) $explicit);

        return $explicit !== '' ? $explicit : $this->deriveRouteName($route);
    }

    protected function safeMiddleware(mixed $middleware): array
    {
        if (is_string($middleware)) {
            $middleware = preg_split('/\R+/', $middleware) ?: [];
        }

        if (! is_array($middleware)) {
            return [];
        }

        return collect($middleware)
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    protected function defaultPhp(): string
    {
        return "use Livewire\\Component;\n\nnew class extends Component {\n    //\n};";
    }
}
