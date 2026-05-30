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

Pages and forms may define routes. Views are plain Blade files and may optionally define `route`, `route_name`, and `middleware` when they should be served directly as full pages. Components, layouts, styles, and snippets use path metadata, not routes.

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
- `route_name`: named-route alias used in views (`route('users.profile')`). Optional. When omitted, evolve derives a stable name from the route (`/` → `home`; `/users/{id}` → `users.id`) and persists it on the artifact.
- `middleware`: optional array of middleware aliases applied to the generated route (e.g. `['auth', 'verified', 'throttle:60,1']`). Empty means no extra middleware beyond the framework default. In the workbench UI the input is one entry per line so parameterized middleware (`throttle:60,1`, `role:admin,editor`) survives intact.
- `parent_id`: logical parent page id
- `order`: order within the parent
- `metadata`: optional custom metadata

Path and route are intentionally separate. Do not infer that route changes imply file moves unless the user explicitly changes path/slug metadata.

Forms that define a `route` carry `route_name` and `middleware` on the same terms as pages.

## Preview impersonation

The workbench preview iframe loads the artifact's real route, so middleware on that route applies. To preview a page as a different identity, the workbench supports `?preview_as={user_id}` on any request in the `web` middleware group. The `EvolvePreviewImpersonation` middleware logs in as the chosen user for that single request (via `Auth::onceUsingId`) without touching the workbench session. Use `?preview_as=guest` to mask the workbench session and run the preview request as an unauthenticated visitor.

Controls:

- `config('evolve.preview.allow_impersonation')` — default `true`, override with `EVOLVE_PREVIEW_ALLOW_IMPERSONATION=false`.
- `GET /api/preview/users` — returns the list shown in the toolbar picker; 403 when impersonation is disabled.
- The picker reloads the iframe whenever the selection changes; the same `preview_as` param is carried through when the "Open" button opens the preview in a new tab. The built-in choices are Guest, then the first 100 users; there is no Self option because the workbench editor session and preview identity are intentionally separate.
- Per-target authorization is gated by the `evolve.preview.impersonate` Laravel Gate. The default registration (in `AppServiceProvider::configurePreviewImpersonationGate`) allows any authenticated workbench user to impersonate any user. Override the gate definition to introduce role-aware rules (e.g. block non-admins from impersonating admin users).
- Every successful impersonation logs `evolve.preview.impersonation` at info level with `workbench_user_id`, `target_user_id`, `ip`, `path`, and whether the swap came from the `preview_as` query or the `X-Preview-As` header. Guest preview logs `target_user_id: null` and `target: guest`.

Impersonation requires the requester to already be authenticated to the workbench; an unauthenticated request with `preview_as` is rejected with 403.

The middleware also accepts an `X-Preview-As` header. After the initial preview render, the response (when HTML) gets a small script injected that adds `X-Preview-As: {target_id}` to any subsequent fetch the iframe makes to `/livewire/*`. That keeps Livewire interactions (button clicks, form submits) running as the impersonated user or guest too, not as the workbench user.

## View artifacts

The `view` kind covers plain Blade files under `resources/views/` that aren't already covered by the typed kinds (pages, forms, layouts, snippets, components). It's how the workbench reaches `dashboard.blade.php`, `welcome.blade.php`, anything under `partials/`, and other top-level Blade templates the starter kit ships.

Behavior:

- **Discovery.** `EvolveLibrary::readViews()` scans `resources/views/` and excludes the typed kind directories (`components`, `forms`, `layouts`, `pages`, `snippets`), `evolve/`, and `workbench.blade.php` itself. Any other `.blade.php` files surface as view artifacts.
- **Shape.** Views carry `blade`, `path`, `usage` (defaults to `@include('id.with.dots')`), and metadata. No PHP block, no styles, no Livewire namespace. Views may also carry `route`, `route_name`, and `middleware` when they are served directly as full pages. Route-backed starter-kit views such as `welcome` and `dashboard` carry their real route metadata so preview identity runs through the same middleware as the public page.
- **Editing.** New views can be created at any path under `resources/views/`. The workbench, the controller, and the MCP `UpsertArtifact` tool all accept `kind: 'view'`.
- **Starter-kit + restore.** Views matching `dashboard`, `welcome`, `partials/*`, or `flux/*` are flagged starter-kit and follow the snapshot-and-restore flow (see below). `workbench` and `evolve/*` are workbench-internal and hard-locked.

## Starter-kit artifacts

The Livewire starter kit ships several artifacts into the same directories the workbench manages — settings pages (`pages/settings/*`), auth layouts (`layouts/auth*`, `layouts/app*`), and named components (`auth-header`, `app-logo`, `desktop-user-menu`, `passkey-*`, etc.). They are marked `is_starter_kit: true` in the library response and rendered in the workbench with a "kit" badge.

Behavior:

- **Editable.** Writing through the workbench, the API, or the MCP `UpsertArtifact` tool succeeds normally.
- **Snapshot on first write.** Before the first overwrite, `EvolveLibrary` copies the on-disk file to `resources/evolve/originals/{kind}/{id}.blade.php` (or `.css` for styles, with layouts also snapshotting their layout-scoped CSS). Subsequent edits never re-snapshot, so the original stays pristine.
- **Restorable.** The artifact then carries `has_original: true`. The workbench shows a "Restore original" button in the toolbar; the API exposes `POST /api/library/{kind}/{id}/restore`; the MCP `RestoreArtifact` tool restores via dry-run-then-confirm_id, matching `DeleteArtifact` semantics.
- **Workbench-internal exception.** `resources/css/app.css` is reclassified as workbench-internal (`isWorkbenchInternalArtifact`) and stays hard-locked — overwriting it would break the workbench shell itself, which loads the same bundle.

## Linting

`php artisan evolve:lint` audits the manifest for:

- duplicate `route_name` values across page/form artifacts
- artifact route names that shadow framework-registered routes (`dashboard`, `home`, etc.)
- middleware aliases that are not registered in the kernel and are not resolvable class names

Use `--json` for machine-readable output. Non-zero exit when findings exist; suitable for CI.

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
