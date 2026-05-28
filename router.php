<?php
// Tiny file-backed backend for the Component Workbench.
// Run: php -S localhost:8000 router.php
//
// The library lives as real files on disk:
//   library/components/<id>.html   definition + an inert usage block
//   library/pages/<id>.json        { name, items: [componentId, ...] }
//
// API:
//   GET  /api/library  -> { components: [...], pages: [...] }
//   PUT  /api/library  -> body { components, pages }; writes files,
//                         deletes any that are no longer present.

const COMPONENTS_DIR = __DIR__ . '/library/components';
const PAGES_DIR      = __DIR__ . '/library/pages';

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/api/library') {
    header('Content-Type: application/json');
    if ($method === 'GET')  { echo json_encode(readLibrary()); exit; }
    if ($method === 'PUT')  { echo json_encode(writeLibrary(json_decode(file_get_contents('php://input'), true) ?: [])); exit; }
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    exit;
}

// Real, visitable routes: a single component preview or a composed page.
if (preg_match('#^/c/([a-z0-9-]+)$#', $uri, $m)) { renderComponentRoute($m[1]); exit; }
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

function readLibrary(): array {
    $components = [];
    foreach (glob(COMPONENTS_DIR . '/*.html') as $path) {
        $components[] = parseComponent(basename($path, '.html'), file_get_contents($path));
    }
    $pages = [];
    foreach (glob(PAGES_DIR . '/*.json') as $path) {
        $data = json_decode(file_get_contents($path), true) ?: [];
        $pages[] = [
            'id'    => basename($path, '.json'),
            'name'  => $data['name'] ?? basename($path, '.json'),
            'items' => $data['items'] ?? [],
        ];
    }
    return ['components' => $components, 'pages' => $pages];
}

function writeLibrary(array $payload): array {
    @mkdir(COMPONENTS_DIR, 0777, true);
    @mkdir(PAGES_DIR, 0777, true);

    $keepComponents = [];
    foreach ($payload['components'] ?? [] as $c) {
        $id = safeId($c['id'] ?? '');
        if ($id === '') continue;
        $keepComponents[$id] = true;
        writeIfChanged(COMPONENTS_DIR . "/$id.html", serializeComponent($c));
    }
    foreach (glob(COMPONENTS_DIR . '/*.html') as $path) {
        if (empty($keepComponents[basename($path, '.html')])) unlink($path);
    }

    $keepPages = [];
    foreach ($payload['pages'] ?? [] as $p) {
        $id = safeId($p['id'] ?? '');
        if ($id === '') continue;
        $keepPages[$id] = true;
        $json = json_encode(['name' => $p['name'] ?? $id, 'items' => $p['items'] ?? []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        writeIfChanged(PAGES_DIR . "/$id.json", $json . "\n");
    }
    foreach (glob(PAGES_DIR . '/*.json') as $path) {
        if (empty($keepPages[basename($path, '.json')])) unlink($path);
    }

    return ['ok' => true];
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
    $definition = rtrim($c['definition'] ?? '');
    $usage      = trim($c['usage'] ?? '');
    return "<!-- name: {$name} -->\n{$definition}\n\n<script type=\"text/html\" data-usage>\n{$usage}\n</script>\n";
}

function parseComponent(string $id, string $content): array {
    $name = $id;
    if (preg_match('/<!--\s*name:\s*(.*?)\s*-->/', $content, $m)) {
        $name = $m[1];
    }
    $usage = '';
    if (preg_match('/<script type="text\/html" data-usage>\s*([\s\S]*?)\s*<\/script>\s*$/', $content, $m)) {
        $usage = $m[1];
    }
    // Definition is everything except the name comment and the usage block.
    $definition = $content;
    $definition = preg_replace('/<!--\s*name:.*?-->\s*/', '', $definition, 1);
    $definition = preg_replace('/<script type="text\/html" data-usage>[\s\S]*?<\/script>\s*$/', '', $definition);
    return [
        'id'         => $id,
        'name'       => $name,
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
// backdrop; 'page' lays the composition out in normal document flow.
function buildDocument(string $usage, array $definitions, string $mode): string {
    $body = $mode === 'page'
        ? "body {
            margin: 0; padding: 40px;
            display: flex; flex-direction: column; align-items: flex-start; gap: 24px;
            font-family: system-ui, sans-serif; color: var(--text); background: var(--surface);
          }"
        : "body {
            margin: 0; min-height: 100vh;
            display: grid; place-items: center; padding: 40px;
            font-family: system-ui, sans-serif;
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
{$defs}
</head>
<body>
{$usage}
</body>
</html>";
}

function renderComponentRoute(string $id): void {
    $id = safeId($id);
    $path = COMPONENTS_DIR . "/$id.html";
    if (!is_file($path)) { http_response_code(404); echo 'Component not found'; return; }
    $c = parseComponent($id, file_get_contents($path));
    header('Content-Type: text/html');
    echo buildDocument($c['usage'], [$c['definition']], 'component');
}

function renderPageRoute(string $id): void {
    $id = safeId($id);
    $path = PAGES_DIR . "/$id.json";
    if (!is_file($path)) { http_response_code(404); echo 'Page not found'; return; }
    $page = json_decode(file_get_contents($path), true) ?: [];

    $seen = [];
    $defs = [];
    $usages = [];
    foreach ($page['items'] ?? [] as $itemId) {
        $itemId = safeId($itemId);
        $cpath = COMPONENTS_DIR . "/$itemId.html";
        if (!is_file($cpath)) continue; // referenced component was deleted
        $c = parseComponent($itemId, file_get_contents($cpath));
        $usages[] = $c['usage'];
        if (empty($seen[$itemId])) { $seen[$itemId] = true; $defs[] = $c['definition']; }
    }
    header('Content-Type: text/html');
    echo buildDocument(implode("\n", $usages), $defs, 'page');
}
