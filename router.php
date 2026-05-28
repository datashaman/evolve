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

// Real, visitable routes: every library entry is an HTML-backed artifact.
if (preg_match('#^/c/([a-z0-9-]+)$#', $uri, $m)) { renderComponentRoute($m[1]); exit; }
if (preg_match('#^/l/([a-z0-9-]+)$#', $uri, $m)) { renderLayoutRoute($m[1]); exit; }
if (preg_match('#^/p/([a-z0-9-]+)$#', $uri, $m)) { renderPageRoute($m[1]); exit; }

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
if ($uri === '/' || $uri === '') {
    header('Content-Type: text/html');
    readfile(__DIR__ . '/public/index.html');
    exit;
}

// Let the built-in server handle any other real file under public/.
$file = __DIR__ . '/public' . $uri;
if (is_file($file)) return false;

http_response_code(404);
echo 'Not found';

// --- helpers ---

function safeId(string $id): string {
    return preg_replace('/[^a-z0-9-]/', '', strtolower($id));
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

// A component file is a real, includable artifact: the definition (template +
// style + script). The display name rides in a leading comment, and the example
// usage in a trailing inert <script type="text/html"> block (ignored by the
// browser when the file is injected into a page).
function serializeComponent(array $c): string {
    $name       = $c['name'] ?? $c['id'] ?? '';
    $requires   = array_values(array_filter($c['requires'] ?? []));
    $definition = rtrim($c['definition'] ?? '');
    $usage      = trim($c['usage'] ?? '');
    $header = "<!-- name: {$name} -->\n";
    if ($requires) {
        $header .= '<!-- requires: ' . implode(', ', $requires) . " -->\n";
    }
    return "{$header}{$definition}\n\n<script type=\"text/html\" data-usage>\n{$usage}\n</script>\n";
}

function parseComponent(string $id, string $content): array {
    $name = $id;
    if (preg_match('/<!--\s*name:\s*(.*?)\s*-->/', $content, $m)) {
        $name = $m[1];
    }
    $requires = [];
    if (preg_match('/<!--\s*requires:\s*(.*?)\s*-->/', $content, $m)) {
        $requires = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
    }
    $usage = '';
    if (preg_match('/<script type="text\/html" data-usage>\s*([\s\S]*?)\s*<\/script>\s*$/', $content, $m)) {
        $usage = $m[1];
    }
    // Definition is everything except the name comment and the usage block.
    $definition = $content;
    $definition = preg_replace('/<!--\s*name:.*?-->\s*/', '', $definition, 1);
    $definition = preg_replace('/<!--\s*requires:.*?-->\s*/', '', $definition, 1);
    $definition = preg_replace('/<script type="text\/html" data-usage>[\s\S]*?<\/script>\s*$/', '', $definition);
    return [
        'id'         => $id,
        'name'       => $name,
        'requires'   => $requires,
        'definition' => trim($definition),
        'usage'      => $usage,
    ];
}

function loadTokens(): string {
    $path = __DIR__ . '/library/tokens.css';
    return is_file($path) ? file_get_contents($path) : '';
}

// Mirror of the client's buildDocument: tokens + definitions (registered once)
// in <head>, usage in <body>. 'component' centers a single element on a grid
// backdrop; pages/layouts own the full canvas.
function buildDocument(string $usage, array $definitions, string $mode): string {
    $body = $mode === 'page'
        ? "html, body { min-height: 100%; }
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
        : "html, body { min-height: 100%; }
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
    $defs = implode("\n", $definitions);
    return "<!DOCTYPE html>
<html>
<head>
  <meta charset=\"utf-8\">
  <style>{$tokens} {$body}</style>
  <script>
    window.defineArtifact = (tag, templateId, styleId) => {
      if (customElements.get(tag)) return;
      customElements.define(tag, class extends HTMLElement {
        connectedCallback() {
          if (this.shadowRoot) return;
          const tpl = document.getElementById(templateId);
          const css = document.getElementById(styleId);
          const style = document.createElement('style');
          style.textContent = css ? css.textContent : '';
          this.attachShadow({ mode: 'open' }).append(style, tpl.content.cloneNode(true));
        }
      });
    };
  </script>
{$defs}
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
    renderArtifactRoute($id, PAGES_DIR, 'page', 'Page not found');
}

function renderArtifactRoute(string $id, string $dir, string $mode, string $notFound): void {
    $id = safeId($id);
    $path = $dir . "/$id.html";
    if (!is_file($path)) { http_response_code(404); echo $notFound; return; }
    $artifact = parseComponent($id, file_get_contents($path));

    header('Content-Type: text/html');
    echo buildDocument($artifact['usage'], requiredDefinitions($artifact), $mode);
}

function requiredDefinitions(array $artifact): array {
    $defs = [];
    $seen = [];
    foreach ($artifact['requires'] ?? [] as $ref) {
        collectRequiredDefinition($ref, $defs, $seen);
    }
    $defs[] = $artifact['definition'];
    return $defs;
}

function collectRequiredDefinition(string $ref, array &$defs, array &$seen): void {
    $ref = trim($ref);
    if ($ref === '' || isset($seen[$ref])) return;
    $seen[$ref] = true;

    $path = requiredPath($ref);
    if (!is_file($path)) return;

    $artifact = parseComponent(basename($path, '.html'), file_get_contents($path));
    foreach ($artifact['requires'] ?? [] as $childRef) {
        collectRequiredDefinition($childRef, $defs, $seen);
    }
    $defs[] = $artifact['definition'];
}

function requiredPath(string $ref): string {
    $ref = str_replace('\\', '/', trim($ref));
    if ($ref === '' || str_starts_with($ref, '/') || str_contains($ref, '..')) {
        return '';
    }
    return __DIR__ . '/library/' . $ref;
}
