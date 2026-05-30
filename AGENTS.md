# Agent Guide

## Project Shape

Evolve is a Laravel 13 and Livewire 4 workbench. It manages real framework files instead of rendering a separate CMS model.

The main workbench surface is `resources/views/workbench.blade.php`. The library API that maps workbench artifacts to files is `app/Services/EvolveLibrary.php`.

The product direction is to encourage structured, correct, efficient website composition. Prefer reusable design tokens, modular snippets/components, clear layouts, and pages assembled from those pieces. Avoid dumping entire bespoke screens into page files when the same structure should be represented as shared styles, snippets, components, layouts, or helper-driven navigation.

## Artifact Model

The manifest lives at `resources/evolve/manifest.json`. It is the source of truth for artifacts shown in the workbench.

Supported artifact groups:

- `styles`: global CSS files under `resources/css`. Use these for design tokens and shared styling foundations.
- `layouts`: Blade layout files under `resources/views/layouts`, with optional CSS under `resources/css/layouts`.
- `pages`: Livewire SFC page files under `resources/views/pages`.
- `components`: Livewire SFC component files under `resources/views/components`.
- `forms`: Livewire SFC form files under `resources/views/forms`.
- `snippets`: Blade-only snippets under `resources/views/snippets`.

Pages and forms may define routes. Components, layouts, styles, and snippets use path metadata, not routes.

Use the artifact boundaries deliberately:

- put cross-site visual primitives and variables in style artifacts
- put page chrome and repeated structural wrappers in layouts
- put reusable interactive units in Livewire components
- put small static/reusable markup fragments in snippets
- keep pages focused on route-specific orchestration and content
- use page tree metadata and content helpers for navigation instead of hard-coded nav lists when possible

## Protected Files

The workbench must not be able to modify the workbench or starter-kit shell files. Protection is enforced in `EvolveLibrary::isProtectedArtifact()`.

Current protected areas include:

- `resources/css/app.css`
- starter components such as `app-logo`, auth helpers, passkey helpers, and placeholder pattern
- `resources/views/layouts/app*`
- `resources/views/layouts/auth*`
- `resources/views/pages/auth*`
- `resources/views/pages/settings*`

Do not weaken these protections without adding tests. Artifact writes must also stay inside the current workspace; path safety is handled by `App\Services\Concerns\GuardsWorkspacePaths`.

## Livewire SFC Conventions

Pages, components, and forms are single-file Livewire components. Preserve the existing SFC structure:

```blade
<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<div>Markup</div>
```

Forms are Livewire SFCs under `resources/views/forms` and usually use `Livewire\Attributes\Layout` directly in the PHP block when they need a standalone layout.

Snippets are not Livewire components. They are Blade-only files and should be rendered with `<x-snippets::... />` or `evolve_snippet()`.

## Page Tree

Pages support:

- `path`: filesystem location
- `route`: public route, including dynamic segments such as `/resources/{resource}`
- `parent_id`: logical parent page id
- `order`: order within the parent
- `metadata`: optional custom metadata

Path and route are intentionally separate. Do not infer that route changes imply file moves unless the user explicitly changes path/slug metadata.

## Content Helpers

Agent-created templates should prefer the documented helper layer over custom manifest parsing:

- `evolve_navigation($parentId = null, $maxDepth = null)`
- `evolve_child_pages($pageId = null)`
- `evolve_sibling_pages($pageId = null)`
- `evolve_metadata($key = null, $default = null, $pageId = null)`
- `evolve_snippet($id, $data = [])`

Helpers live in `app/helpers.php`; implementation lives in `app/Support/EvolveContentTree.php`.

## MCP Tools

The Evolve MCP server is in `app/Mcp/Servers/EvolveServer.php`. Artifact tools live in `app/Mcp/Tools`.

Mutating MCP tools should default to dry-run behavior unless intentionally destructive and confirmed. Delete tools require `confirm_id`.

It is often easy to edit artifact files directly, but resist that temptation. Prefer MCP tools or the workbench library API where possible so changes flow through the structured artifact process, update manifest metadata consistently, and exercise the same safety checks users rely on. Use direct file edits for artifacts only as a last resort, or when changing application code that is outside the artifact model.

When changing artifact kinds, update:

- `EvolveLibrary`
- workbench navigation/editor behavior
- MCP tool validation and schemas
- route/preview handling when applicable
- focused tests under `tests/Unit` and `tests/Feature`

## Verification

Use focused tests while iterating, then run the full checks before committing:

```bash
php artisan test tests/Unit/EvolveLibraryPathsTest.php
php artisan test tests/Feature/EvolveMcpServerTest.php
npm run build
composer lint:check
php artisan test
```

`composer test` also runs config clear, Pint check, and the full test suite.

## Commit Hygiene

Do not commit demo/sample content unless the user explicitly asks for it. Keep `resources/evolve/manifest.json` free of temporary experiments.

If you seed existing resources into the manifest, treat that as a product decision and get explicit confirmation before committing.
