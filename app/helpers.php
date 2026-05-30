<?php

use App\Support\EvolveContentTree;
use Illuminate\Support\HtmlString;

if (! function_exists('evolve_navigation')) {
    function evolve_navigation(?string $parentId = null, ?int $maxDepth = null): array
    {
        return app(EvolveContentTree::class)->navigation($parentId, $maxDepth);
    }
}

if (! function_exists('evolve_child_pages')) {
    function evolve_child_pages(?string $pageId = null): array
    {
        return app(EvolveContentTree::class)->childPages($pageId);
    }
}

if (! function_exists('evolve_sibling_pages')) {
    function evolve_sibling_pages(?string $pageId = null): array
    {
        return app(EvolveContentTree::class)->siblingPages($pageId);
    }
}

if (! function_exists('evolve_metadata')) {
    function evolve_metadata(?string $key = null, mixed $default = null, ?string $pageId = null): mixed
    {
        return app(EvolveContentTree::class)->metadata($key, $default, $pageId);
    }
}

if (! function_exists('evolve_snippet')) {
    function evolve_snippet(string $id, array $data = []): HtmlString
    {
        return app(EvolveContentTree::class)->snippet($id, $data);
    }
}
