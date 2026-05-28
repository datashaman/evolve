<?php
// Tiny file-backed backend for the Component Workbench.
// Run: php -S localhost:8000 router.php
//
// The library lives as real files on disk:
//   library/components/<id>.html   definition + an inert usage block
//   library/layouts/<id>.html      definition + an inert usage block
//   library/pages/<id>.html        definition + an inert usage block
//
// API:
//   GET  /api/library  -> { components: [...], layouts: [...], pages: [...] }
//   PUT  /api/library  -> body { components, layouts, pages }; writes files,
//                         deletes any that are no longer present.

const COMPONENTS_DIR = __DIR__ . '/library/components';
const LAYOUTS_DIR    = __DIR__ . '/library/layouts';
const PAGES_DIR      = __DIR__ . '/library/pages';
const SEED_DIR       = __DIR__ . '/library/.seed';

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/api/library') {
    header('Content-Type: application/json');
    if ($method === 'GET')  { ensureSeeded(); echo json_encode(readLibrary()); exit; }
    if ($method === 'PUT')  { echo json_encode(writeLibrary(json_decode(file_get_contents('php://input'), true) ?: [])); exit; }
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    exit;
}

// Reset the sample artifacts and tokens to their canonical seed (overwrites
// matching ids, adds missing; custom artifacts are left alone).
if ($uri === '/api/reset' && $method === 'POST') {
    header('Content-Type: application/json');
    seedArtifacts();
    seedTokens();
    echo json_encode(readLibrary());
    exit;
}

// Write the shared design tokens (read via /library/tokens.css).
if ($uri === '/api/tokens' && $method === 'PUT') {
    writeIfChanged(__DIR__ . '/library/tokens.css', file_get_contents('php://input'));
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// Real, visitable routes: components and layouts stay in their workbench
// namespaces; pages are site routes and are resolved after assets/workbench.
if (preg_match('#^/c/([a-z0-9-]+)$#', $uri, $m)) { renderComponentRoute($m[1]); exit; }
if (preg_match('#^/l/([a-z0-9-]+)$#', $uri, $m)) { renderLayoutRoute($m[1]); exit; }

// Serve the shared tokens (and anything else read-only under /library/).
if (str_starts_with($uri, '/library/')) {
    $path = __DIR__ . $uri;
    if (is_file($path)) {
        if (str_ends_with($path, '.css')) header('Content-Type: text/css');
        readfile($path);
        exit;
    }
    http_response_code(404);
    exit;
}

// Serve the workbench UI.
if ($uri === '/workbench') {
    header('Content-Type: text/html');
    readfile(__DIR__ . '/public/index.html');
    exit;
}

// Let the built-in server handle any other real file under public/.
$file = __DIR__ . '/public' . $uri;
if (is_file($file)) return false;

if ($uri === '/' || $uri === '') {
    renderPageRoute('');
    exit;
}

renderPageRoute($uri);
exit;

http_response_code(404);
echo 'Not found';

// --- helpers ---

function safeId(string $id): string {
    return preg_replace('/[^a-z0-9-]/', '', strtolower($id));
}

function safeSlug(string $slug): string {
    $slug = strtolower(str_replace('\\', '/', trim($slug)));
    if ($slug === '' || $slug === '/') return '/';
    $slug = preg_replace('#/+#', '/', $slug);
    $slug = preg_replace('#[^a-z0-9/-]#', '-', $slug);
    $slug = preg_replace('#-+#', '-', $slug);
    $slug = '/' . trim($slug, '/-');
    return $slug === '' ? '/' : $slug;
}

// Copy canonical seed artifact files into the live library (overwrite matching
// ids, add missing). The single source of truth for the samples is library/.seed.
function seedArtifacts(): void {
    foreach (artifactDirs() as $folder => $dir) {
        @mkdir($dir, 0777, true);
        foreach (glob(SEED_DIR . "/{$folder}/*.html") ?: [] as $src) {
            writeIfChanged($dir . '/' . basename($src), file_get_contents($src));
        }
    }
}

function seedTokens(): void {
    $src = SEED_DIR . '/tokens.css';
    if (is_file($src)) {
        writeIfChanged(__DIR__ . '/library/tokens.css', file_get_contents($src));
    }
}

// First run: an empty library gets the full canonical seed.
function ensureSeeded(): void {
    if (!glob(COMPONENTS_DIR . '/*.html') && !glob(LAYOUTS_DIR . '/*.html') && !glob(PAGES_DIR . '/*.html')) {
        seedArtifacts();
    }
    if (!is_file(__DIR__ . '/library/tokens.css')) {
        seedTokens();
    }
}

function readLibrary(): array {
    return [
        'components' => readArtifacts(COMPONENTS_DIR, 'components'),
        'layouts'    => readArtifacts(LAYOUTS_DIR, 'layouts'),
        'pages'      => readArtifacts(PAGES_DIR, 'pages'),
    ];
}

function writeLibrary(array $payload): array {
    writeArtifacts(COMPONENTS_DIR, $payload['components'] ?? []);
    writeArtifacts(LAYOUTS_DIR, $payload['layouts'] ?? []);
    writeArtifacts(PAGES_DIR, $payload['pages'] ?? []);
    return ['ok' => true];
}

function artifactDirs(): array {
    return [
        'components' => COMPONENTS_DIR,
        'layouts'    => LAYOUTS_DIR,
        'pages'      => PAGES_DIR,
    ];
}

function readArtifacts(string $dir, string $folder): array {
    $artifacts = [];
    foreach (glob($dir . '/*.html') ?: [] as $path) {
        $id = basename($path, '.html');
        $artifact = parseComponent($id, file_get_contents($path));
        $artifact['path'] = "{$folder}/{$id}.html";
        $artifacts[] = $artifact;
    }
    return $artifacts;
}

function writeArtifacts(string $dir, array $artifacts): void {
    @mkdir($dir, 0777, true);
    $keep = [];
    foreach ($artifacts as $artifact) {
        $id = safeId($artifact['id'] ?? '');
        if ($id === '') continue;
        $keep[$id] = true;
        writeIfChanged($dir . "/$id.html", serializeComponent($artifact));
    }
    foreach (glob($dir . '/*.html') ?: [] as $path) {
        if (empty($keep[basename($path, '.html')])) unlink($path);
    }
}

// Only touch a file when its contents actually change, so git diffs stay clean.
function writeIfChanged(string $path, string $content): void {
    if (!is_file($path) || file_get_contents($path) !== $content) {
        file_put_contents($path, $content);
    }
}

// An artifact file is source plus metadata. The display name/tag ride in leading
// comments, and the example usage lives in a trailing inert script block.
function serializeComponent(array $c): string {
    $name       = $c['name'] ?? $c['id'] ?? '';
    $tag        = safeTag($c['tag'] ?? artifactTag($c));
    $title      = $c['title'] ?? '';
    $slug       = array_key_exists('slug', $c) ? safeSlug($c['slug']) : '';
    $layout     = safeLayoutName($c['layout'] ?? '');
    $definition = rtrim($c['definition'] ?? '');
    $usage      = trim($c['usage'] ?? '');
    $header = "<!-- name: {$name} -->\n";
    if ($tag !== '') {
        $header .= "<!-- tag: {$tag} -->\n";
    }
    if ($title !== '') {
        $header .= "<!-- title: {$title} -->\n";
    }
    if ($slug !== '') {
        $header .= "<!-- slug: {$slug} -->\n";
    }
    if ($layout !== '') {
        $header .= "<!-- layout: {$layout} -->\n";
    }
    return "{$header}{$definition}\n\n<script type=\"text/html\" data-usage>\n{$usage}\n</script>\n";
}

function parseComponent(string $id, string $content): array {
    $name = $id;
    if (preg_match('/<!--\s*name:\s*(.*?)\s*-->/', $content, $m)) {
        $name = $m[1];
    }
    $tag = '';
    if (preg_match('/<!--\s*tag:\s*(.*?)\s*-->/', $content, $m)) {
        $tag = safeTag($m[1]);
    }
    $title = '';
    if (preg_match('/<!--\s*title:\s*(.*?)\s*-->/', $content, $m)) {
        $title = $m[1];
    }
    $slug = '';
    if (preg_match('/<!--\s*slug:\s*(.*?)\s*-->/', $content, $m)) {
        $slug = safeSlug($m[1]);
    }
    $layout = '';
    if (preg_match('/<!--\s*layout:\s*(.*?)\s*-->/', $content, $m)) {
        $layout = safeLayoutName($m[1]);
    }
    $usage = '';
    if (preg_match('/<script type="text\/html" data-usage>\s*([\s\S]*?)\s*<\/script>\s*$/', $content, $m)) {
        $usage = $m[1];
    }
    // Definition is the source block after metadata and before usage.
    $definition = $content;
    $definition = preg_replace('/<!--\s*name:.*?-->\s*/', '', $definition, 1);
    $definition = preg_replace('/<!--\s*tag:.*?-->\s*/', '', $definition, 1);
    $definition = preg_replace('/<!--\s*title:.*?-->\s*/', '', $definition, 1);
    $definition = preg_replace('/<!--\s*slug:.*?-->\s*/', '', $definition, 1);
    $definition = preg_replace('/<!--\s*layout:.*?-->\s*/', '', $definition, 1);
    $definition = preg_replace('/<script type="text\/html" data-usage>[\s\S]*?<\/script>\s*$/', '', $definition);
    return [
        'id'         => $id,
        'name'       => $name,
        'tag'        => $tag,
        'title'      => $title,
        'slug'       => $slug,
        'layout'     => $layout,
        'definition' => trim($definition),
        'usage'      => $usage,
    ];
}

function loadTokens(): string {
    $path = __DIR__ . '/library/tokens.css';
    return is_file($path) ? file_get_contents($path) : '';
}

// Mirror of the client's buildDocument: tokens plus rendered artifact markup.
// Component previews center a single artifact; pages/layouts own the full canvas.
function buildDocument(string $usage, array $definitions, string $mode, string $title = 'Component Workbench'): string {
    $body = $mode === 'page'
        ? "html { scrollbar-gutter: stable; }
          html, body { min-height: 100%; }
          body {
            margin: 0;
            min-height: 100vh;
            font-family: var(--font-sans, system-ui, sans-serif);
            color: var(--text);
            background: var(--surface-soft, var(--surface));
          }
          body > * {
            display: block;
            min-height: 100vh;
          }"
        : "html { scrollbar-gutter: stable; }
          html, body { min-height: 100%; }
          body {
            margin: 0; min-height: 100vh;
            display: grid; place-items: center; padding: 40px;
            font-family: var(--font-sans, system-ui, sans-serif);
            background: #fafafa;
            background-image:
              linear-gradient(#0000000a 1px, transparent 1px),
              linear-gradient(90deg, #0000000a 1px, transparent 1px);
            background-size: 16px 16px;
          }";
    $tokens = loadTokens();
    return "<!DOCTYPE html>
<html>
<head>
  <meta charset=\"utf-8\">
  <title>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>
  <style>{$tokens} {$body}</style>
  <script>
    (() => {
      const activate = (root = document) => {
        root.querySelectorAll('template[shadowrootmode]').forEach((tpl) => {
          const host = tpl.parentElement;
          if (!host || host.shadowRoot) return;
          const shadow = host.attachShadow({ mode: tpl.getAttribute('shadowrootmode') || 'open' });
          shadow.append(tpl.content);
          tpl.remove();
          activate(shadow);
        });
      };
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => activate());
      } else {
        activate();
      }
    })();
  </script>
</head>
<body>
{$usage}
</body>
</html>";
}

function renderComponentRoute(string $id): void {
    renderArtifactRoute($id, COMPONENTS_DIR, 'component', 'Component not found');
}

function renderLayoutRoute(string $id): void {
    renderArtifactRoute($id, LAYOUTS_DIR, 'page', 'Layout not found');
}

function renderPageRoute(string $id): void {
    renderPageArtifactRoute($id);
}

function renderArtifactRoute(string $id, string $dir, string $mode, string $notFound): void {
    $id = safeId($id);
    $path = $dir . "/$id.html";
    if (!is_file($path)) { http_response_code(404); echo $notFound; return; }
    $artifact = parseComponent($id, file_get_contents($path));
    [$usage, $definitions] = renderedArtifactParts($artifact);

    header('Content-Type: text/html');
    echo buildDocument($usage, $definitions, $mode, $artifact['title'] ?: $artifact['name']);
}

function renderPageArtifactRoute(string $slug): void {
    $slug = safeSlug($slug);
    $path = pagePathBySlug($slug);
    if ($path === '') { http_response_code(404); echo 'Page not found'; return; }
    $artifact = parseComponent(basename($path, '.html'), file_get_contents($path));
    [$usage, $definitions] = renderedArtifactParts($artifact);

    header('Content-Type: text/html');
    echo buildDocument($usage, $definitions, 'page', $artifact['title'] ?: $artifact['name']);
}

function pagePathBySlug(string $slug): string {
    foreach (glob(PAGES_DIR . '/*.html') ?: [] as $path) {
        $artifact = parseComponent(basename($path, '.html'), file_get_contents($path));
        if ($artifact['slug'] === $slug) return $path;
    }
    return '';
}

function renderedArtifactParts(array $artifact): array {
    $defs = [];
    $seen = [];
    $context = ['page' => $artifact];
    $usage = $artifact['slug'] !== '' && $artifact['layout'] !== ''
        ? renderPageParts($artifact, $defs, $seen, $context)
        : renderArtifactUsage($artifact, expandUses($artifact['usage'], $defs, $seen, $context), $defs, $seen, $context);
    return [$usage, $defs];
}

function expandPartFallbacks(string $html, array &$defs, array &$seen, array $context): string {
    return expandParts($html, [], $defs, $seen, $context);
}

function renderPageParts(array $page, array &$defs, array &$seen, array $context): string {
    $layoutPath = requiredPath(layoutRef($page['layout']));
    if (!is_file($layoutPath)) return expandUses($page['definition'], $defs, $seen);

    $layout = parseComponent(basename($layoutPath, '.html'), file_get_contents($layoutPath));
    return renderLayoutChain($layout, pageParts($page['definition']), artifactStyle($page), $defs, $seen, $context);
}

function renderLayoutChain(array $layout, array $parts, string $childStyle, array &$defs, array &$seen, array $context): string {
    $template = expandParts(expandUses(artifactTemplate($layout), $defs, $seen, $context), $parts, $defs, $seen, $context);

    if (($layout['layout'] ?? '') === '') {
        $childStyle = trim($childStyle);
        if ($childStyle !== '') $template = "<style>{$childStyle}</style>{$template}";
        return renderArtifactInstance($layout, '', '', $defs, $seen, $context, $template);
    }

    $parentPath = requiredPath(layoutRef($layout['layout']));
    if (!is_file($parentPath)) {
        $style = trim($childStyle);
        if ($style !== '') $template = "<style>{$style}</style>{$template}";
        return renderArtifactInstance($layout, '', '', $defs, $seen, $context, $template);
    }

    $parent = parseComponent(basename($parentPath, '.html'), file_get_contents($parentPath));
    $parentParts = pageParts($template);
    if (!$parentParts) $parentParts = ['main' => $template];
    $style = trim(artifactStyle($layout) . "\n" . $childStyle);
    if ($style !== '') {
        $target = array_key_exists('main', $parentParts) ? 'main' : array_key_first($parentParts);
        $parentParts[$target] = "<style>{$style}</style>" . $parentParts[$target];
    }
    return renderLayoutChain($parent, $parentParts, '', $defs, $seen, $context);
}

function pageParts(string $html): array {
    $parts = [];
    $pattern = '#<part\b([^>]*)>((?:(?!<part\b).)*?)</part>#is';
    preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        if (!preg_match('/\bname\s*=\s*(["\'])(.*?)\1/i', $m[1], $name)) continue;
        $key = safePartName($name[2]);
        if ($key !== '') $parts[$key] = $m[2];
    }
    return $parts;
}

function expandParts(string $html, array $parts, array &$defs, array &$seen, array $context): string {
    $paired = '#<x-part\b([^>]*)>((?:(?!<x-part\b).)*?)</x-part>#is';
    $html = preg_replace_callback($paired, function ($m) use ($parts, &$defs, &$seen, $context) {
        return renderPartTag($m[1], $m[2], $parts, $defs, $seen, $context);
    }, $html);

    return preg_replace_callback('#<x-part\b([^>/]*?)\s*/>#is', function ($m) use ($parts, &$defs, &$seen, $context) {
        return renderPartTag($m[1], '', $parts, $defs, $seen, $context);
    }, $html);
}

function renderPartTag(string $attrs, string $fallback, array $parts, array &$defs, array &$seen, array $context): string {
    if (!preg_match('/\bname\s*=\s*(["\'])(.*?)\1/i', $attrs, $m)) return '';
    $name = safePartName($m[2]);
    return expandUses($parts[$name] ?? $fallback, $defs, $seen, $context);
}

function expandPages(string $html, array &$defs, array &$seen, array $context): string {
    $paired = '#<x-pages\b([^>]*)>((?:(?!<x-pages\b).)*?)</x-pages>#is';
    return preg_replace_callback($paired, function ($m) use (&$defs, &$seen, $context) {
        $parent = pageParentFromAttrs($m[1], $context);
        $children = childPages($parent);
        $out = '';
        foreach ($children as $page) {
            $out .= expandUses(renderPageTemplate($m[2], $page), $defs, $seen, ['page' => $page]);
        }
        return $out;
    }, $html);
}

function pageParentFromAttrs(string $attrs, array $context): string {
    if (preg_match('/\bparent\s*=\s*(["\'])(.*?)\1/i', $attrs, $m)) {
        return safeSlug($m[2]);
    }
    if (($context['page']['slug'] ?? '') !== '') {
        return safeSlug($context['page']['slug']);
    }
    return '/';
}

function childPages(string $parent): array {
    $children = [];
    foreach (pageIndex() as $page) {
        if (($page['parent'] ?? '') === $parent) $children[] = $page;
    }
    usort($children, fn($a, $b) => strcmp($a['slug'], $b['slug']));
    return $children;
}

function pageIndex(): array {
    $pages = [];
    foreach (glob(PAGES_DIR . '/*.html') ?: [] as $path) {
        $page = parseComponent(basename($path, '.html'), file_get_contents($path));
        if (($page['slug'] ?? '') === '') continue;
        $page['url'] = $page['slug'];
        $page['parent'] = parentSlug($page['slug']);
        $pages[] = $page;
    }
    return $pages;
}

function parentSlug(string $slug): string {
    $slug = safeSlug($slug);
    if ($slug === '/') return '';
    $path = trim($slug, '/');
    if (!str_contains($path, '/')) return '/';
    return '/' . dirname($path);
}

function renderPageTemplate(string $template, array $page): string {
    $values = [
        'name' => $page['name'] ?? '',
        'title' => $page['title'] ?: ($page['name'] ?? ''),
        'slug' => $page['slug'] ?? '',
        'url' => $page['url'] ?? ($page['slug'] ?? ''),
    ];
    return preg_replace_callback('/\{(name|title|slug|url)\}/', fn($m) => htmlspecialchars($values[$m[1]] ?? '', ENT_QUOTES, 'UTF-8'), $template);
}

function expandUses(string $html, array &$defs, array &$seen, array $context): string {
    $html = expandPages($html, $defs, $seen, $context);
    $paired = '#<(x-use|x-component|x-layout)\b([^>]*)>((?:(?!<x-use\b|<x-component\b|<x-layout\b).)*?)</\1>#is';
    while (preg_match($paired, $html)) {
        $html = preg_replace_callback($paired, function ($m) use (&$defs, &$seen, $context) {
            return match ($m[1]) {
                'x-component' => expandComponentTag($m[2], $m[3], $defs, $seen, $context),
                'x-layout' => expandLayoutTag($m[2], $m[3], $defs, $seen, $context),
                default => expandUseTag($m[2], $m[3], $defs, $seen, $context),
            };
        }, $html);
    }

    return preg_replace_callback('#<(x-use|x-component|x-layout)\b([^>]*)/>#is', function ($m) use (&$defs, &$seen, $context) {
        return match ($m[1]) {
            'x-component' => expandComponentTag($m[2], '', $defs, $seen, $context),
            'x-layout' => expandLayoutTag($m[2], '', $defs, $seen, $context),
            default => expandUseTag($m[2], '', $defs, $seen, $context),
        };
    }, $html);
}

function expandUseTag(string $attrs, string $inner, array &$defs, array &$seen, array $context): string {
    if (!preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $attrs, $m)) return '';

    $ref = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $path = requiredPath($ref);
    if (!is_file($path)) return '';

    $artifact = parseComponent(basename($path, '.html'), file_get_contents($path));
    $attrs = preg_replace('/\s*\bsrc\s*=\s*(["\']).*?\1/i', '', $attrs, 1);
    return renderArtifactInstance($artifact, $attrs, $inner, $defs, $seen, $context);
}

function expandComponentTag(string $attrs, string $inner, array &$defs, array &$seen, array $context): string {
    if (!preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $attrs, $m)) return '';

    $ref = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $ref = componentRef($ref);
    $path = requiredPath($ref);
    if (!is_file($path)) return '';

    $artifact = parseComponent(basename($path, '.html'), file_get_contents($path));
    $attrs = preg_replace('/\s*\bsrc\s*=\s*(["\']).*?\1/i', '', $attrs, 1);
    return renderArtifactInstance($artifact, $attrs, $inner, $defs, $seen, $context);
}

function expandLayoutTag(string $attrs, string $inner, array &$defs, array &$seen, array $context): string {
    if (!preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $attrs, $m)) return '';

    $ref = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $ref = layoutRef($ref);
    $path = requiredPath($ref);
    if (!is_file($path)) return '';

    $artifact = parseComponent(basename($path, '.html'), file_get_contents($path));
    return renderLayoutChain($artifact, pageParts($inner), '', $defs, $seen, $context);
}

function renderArtifactUsage(array $artifact, string $usage, array &$defs, array &$seen, array $context): string {
    $tag = artifactTag($artifact);
    if ($tag === '') return $usage;

    $paired = '#<' . preg_quote($tag, '#') . '\b([^>]*)>((?:(?!<' . preg_quote($tag, '#') . '\b).)*?)</' . preg_quote($tag, '#') . '>#is';
    $usage = preg_replace_callback($paired, function ($m) use ($artifact, &$defs, &$seen, $context) {
        if (preg_match('/^\s*<template\b[^>]*\bshadowrootmode=/i', $m[2])) return $m[0];
        return renderArtifactInstance($artifact, $m[1], $m[2], $defs, $seen, $context);
    }, $usage);

    return preg_replace_callback('#<' . preg_quote($tag, '#') . '\b([^>]*)/>#is', function ($m) use ($artifact, &$defs, &$seen, $context) {
        return renderArtifactInstance($artifact, $m[1], '', $defs, $seen, $context);
    }, $usage);
}

function renderArtifactInstance(array $artifact, string $attrs, string $inner, array &$defs, array &$seen, array $context, ?string $templateOverride = null): string {
    $tag = artifactTag($artifact);
    if ($tag === '') return $inner;

    $style = artifactStyle($artifact);
    $template = $templateOverride ?? expandPartFallbacks(expandUses(artifactTemplate($artifact), $defs, $seen, $context), $defs, $seen, $context);
    $inner = expandUses($inner, $defs, $seen, $context);
    $attrs = trim($attrs);
    $attrs = $attrs === '' ? '' : ' ' . $attrs;

    return "<{$tag}{$attrs}><template shadowrootmode=\"open\"><style>{$style}</style>{$template}</template>{$inner}</{$tag}>";
}

function artifactStyle(array $artifact): string {
    if (preg_match('/<style\b[^>]*>\s*([\s\S]*?)\s*<\/style>/i', $artifact['definition'], $m)) {
        return $m[1];
    }
    return '';
}

function artifactTemplate(array $artifact): string {
    if (preg_match('/<template\b[^>]*>\s*([\s\S]*?)\s*<\/template>/i', $artifact['definition'], $m)) {
        return $m[1];
    }
    return '';
}

function componentRef(string $src): string {
    $src = trim(str_replace('\\', '/', $src));
    $src = preg_replace('#/+#', '/', $src);
    $src = trim($src, '/');
    if ($src === '' || str_contains($src, '..')) return '';
    if (str_starts_with($src, 'components/')) {
        $src = substr($src, strlen('components/'));
    }
    if (str_ends_with($src, '.html')) {
        $src = substr($src, 0, -5);
    }
    return 'components/' . $src . '.html';
}

function layoutRef(string $src): string {
    $src = trim(str_replace('\\', '/', $src));
    $src = preg_replace('#/+#', '/', $src);
    $src = trim($src, '/');
    if ($src === '' || str_contains($src, '..')) return '';
    if (str_starts_with($src, 'layouts/')) {
        $src = substr($src, strlen('layouts/'));
    }
    if (str_ends_with($src, '.html')) {
        $src = substr($src, 0, -5);
    }
    return 'layouts/' . $src . '.html';
}

function artifactTag(array $artifact): string {
    return safeTag($artifact['tag'] ?? '');
}

function safeTag(string $tag): string {
    $tag = strtolower(trim($tag));
    return preg_match('/^[a-z][a-z0-9]*-[a-z0-9-]+$/', $tag) ? $tag : '';
}

function safeLayoutName(string $layout): string {
    $layout = trim(str_replace('\\', '/', strtolower($layout)));
    $layout = trim($layout, '/');
    return $layout !== '' && !str_contains($layout, '..') && preg_match('/^[a-z0-9-\/]+$/', $layout) ? $layout : '';
}

function safePartName(string $name): string {
    $name = strtolower(trim($name));
    return preg_match('/^[a-z0-9-]+$/', $name) ? $name : '';
}

function requiredPath(string $ref): string {
    $ref = str_replace('\\', '/', trim($ref));
    if ($ref === '' || str_starts_with($ref, '/') || str_contains($ref, '..')) {
        return '';
    }
    return __DIR__ . '/library/' . $ref;
}
