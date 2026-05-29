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
            'components' => $this->readGroup('component', $manifest['components'] ?? []),
            'layouts' => $this->readGroup('layout', $manifest['layouts'] ?? []),
            'pages' => $this->readGroup('page', $manifest['pages'] ?? []),
        ];
    }

    public function write(array $payload): void
    {
        $previous = $this->manifest();

        $manifest = [
            'components' => $this->writeGroup('component', $payload['components'] ?? [], $previous['components'] ?? []),
            'layouts' => $this->writeGroup('layout', $payload['layouts'] ?? [], $previous['layouts'] ?? []),
            'pages' => $this->writeGroup('page', $payload['pages'] ?? [], $previous['pages'] ?? []),
        ];

        $this->writeFile($this->manifestPath(), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    public function tokens(): string
    {
        return File::exists($this->tokenPath()) ? File::get($this->tokenPath()) : '';
    }

    public function writeTokens(string $css): void
    {
        $this->writeFile($this->tokenPath(), $css);
    }

    public function pageRoutes(): array
    {
        return collect($this->manifest()['pages'] ?? [])
            ->map(fn (array $page) => [
                'slug' => $this->safeSlug($page['slug'] ?? ''),
                'component' => 'pages::'.$this->componentName($page['id'] ?? ''),
            ])
            ->filter(fn (array $page) => $page['component'] !== 'pages::')
            ->values()
            ->all();
    }

    public function usage(string $kind, string $id): string
    {
        $key = match ($kind) {
            'component' => 'components',
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

    public function stylesheet(): string
    {
        $manifest = $this->manifest();
        $styles = [];

        foreach (['component' => 'components', 'layout' => 'layouts', 'page' => 'pages'] as $kind => $group) {
            foreach ($manifest[$group] ?? [] as $artifact) {
                $id = $this->safeId($artifact['id'] ?? '');
                $sfc = $this->parseSfc($this->filePath($kind, $id));
                $style = $sfc['style'] ?? '';

                if ($id === '' || trim($style) === '') {
                    continue;
                }

                if ($kind === 'component') {
                    $style = $this->scopeCss($style, '[wire\\:name="'.$this->componentReference($kind, $id).'"]', $this->rootTag($sfc['blade'] ?? ''));
                }

                if ($kind === 'page') {
                    $style = $this->scopeCss($style, '[wire\\:name="'.$this->componentReference($kind, $id).'"]');
                }

                $styles[] = "/* {$kind}: {$id} */\n".trim($style);
            }
        }

        return implode("\n\n", $styles)."\n";
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
                    'slug' => $entry['slug'] ?? '',
                    'usage' => $entry['usage'] ?? '',
                    'path' => $this->relativePath($kind, $id),
                    'component' => $this->componentReference($kind, $id),
                    ...$this->parseSfc($this->filePath($kind, $id)),
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
            $id = $this->safeId($artifact['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $keep[] = $id;
            $this->writeFile($this->filePath($kind, $id), $this->serializeSfc(
                (string) ($artifact['php'] ?? $this->defaultPhp()),
                (string) ($artifact['blade'] ?? '<div></div>'),
                (string) ($artifact['style'] ?? '')
            ));

            $entry = [
                'id' => $id,
                'name' => (string) ($artifact['name'] ?? Str::headline(basename($id))),
                'usage' => (string) ($artifact['usage'] ?? ''),
            ];

            if ($kind === 'page') {
                $entry['slug'] = $this->safeSlug($artifact['slug'] ?? '');
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

    protected function serializeSfc(string $php, string $blade, string $style): string
    {
        $source = "<?php\n\n".trim($php)."\n?>\n\n".trim($blade)."\n";
        $style = trim($style);

        if ($style !== '') {
            $source .= "\n<style>\n{$style}\n</style>\n";
        }

        return $source;
    }

    protected function scopeCss(string $css, string $scope, ?string $rootTag = null): string
    {
        return preg_replace_callback('/(^|[{}])(\s*)([^@{}][^{}]*?)\s*\{/', function (array $match) use ($scope, $rootTag) {
            $selectors = collect(explode(',', trim($match[3])))
                ->map(fn (string $selector) => $this->scopeSelector(trim($selector), $scope, $rootTag))
                ->implode(', ');

            return $match[1].$match[2].$selectors.' {';
        }, $css);
    }

    protected function scopeSelector(string $selector, string $scope, ?string $rootTag = null): string
    {
        if ($selector === '' || str_starts_with($selector, '@') || str_contains($selector, '@media') || str_starts_with($selector, $scope)) {
            return $selector;
        }

        if ($rootTag && preg_match('/^'.preg_quote($rootTag, '/').'(?=$|[.#:\[])(.*)$/', $selector, $match)) {
            return $scope.($match[1] ?? '');
        }

        return $scope.' '.$selector;
    }

    protected function rootTag(string $blade): ?string
    {
        return preg_match('/^\s*<([a-z][\w-]*)\b/i', $blade, $match) ? strtolower($match[1]) : null;
    }

    protected function manifest(): array
    {
        if (! File::exists($this->manifestPath())) {
            return ['components' => [], 'layouts' => [], 'pages' => []];
        }

        $decoded = json_decode(File::get($this->manifestPath()), true);

        return is_array($decoded) ? $decoded : ['components' => [], 'layouts' => [], 'pages' => []];
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
            }
        }
    }

    protected function manifestPath(): string
    {
        return resource_path('evolve/manifest.json');
    }

    protected function tokenPath(): string
    {
        return resource_path('css/tokens.css');
    }

    protected function filePath(string $kind, string $id): string
    {
        return $this->rootFor($kind).'/'.$this->safeId($id).'.blade.php';
    }

    protected function rootFor(string $kind): string
    {
        return match ($kind) {
            'layout' => resource_path('views/layouts'),
            'page' => resource_path('views/pages'),
            default => resource_path('views/components'),
        };
    }

    protected function relativePath(string $kind, string $id): string
    {
        return match ($kind) {
            'layout' => 'resources/views/layouts/'.$id.'.blade.php',
            'page' => 'resources/views/pages/'.$id.'.blade.php',
            default => 'resources/views/components/'.$id.'.blade.php',
        };
    }

    protected function componentReference(string $kind, string $id): string
    {
        return match ($kind) {
            'layout' => 'layouts::'.$this->componentName($id),
            'page' => 'pages::'.$this->componentName($id),
            default => $this->componentName($id),
        };
    }

    protected function componentName(string $id): string
    {
        return str_replace('/', '.', $this->safeId($id));
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
        $slug = strtolower(str_replace('\\', '/', trim($slug)));
        if ($slug === '' || $slug === '/') {
            return '/';
        }

        return '/'.trim(preg_replace('#/+#', '/', preg_replace('#[^a-z0-9/-]#', '-', $slug)), '/-');
    }

    protected function defaultPhp(): string
    {
        return "use Livewire\\Component;\n\nnew class extends Component {\n    //\n};";
    }
}
