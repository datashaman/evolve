# Evolve

Evolve is a Laravel and Livewire-native workbench for building sites from the same artifacts the application runs in production: layouts, snippets, styles, components, forms, pages, and content models.

The goal is not to maintain a separate CMS renderer. The workbench edits framework files and previews the real Laravel/Livewire runtime.

## Current Shape

- Authenticated workbench at `/workbench`
- Native Livewire single-file components for pages and components
- Livewire SFC form components managed as first-class form artifacts
- Blade layout files in the Laravel view tree
- Blade snippets in the Laravel view tree
- Orderable global style files
- Page tree metadata for parent/child page organization
- Dynamic content models backed by normal Laravel models, migrations, and database tables
- Runtime page routes generated from the Evolve library manifest

## Stack

- Laravel 13
- Livewire 4
- Flux
- Tailwind CSS 4
- Vite
- SQLite by default

## Setup

```bash
composer run setup
```

For local development:

```bash
composer run dev
```

If you use Laravel Valet, point the project at the repo and open:

```text
https://evolve.test
```

The workbench is protected by Laravel auth and verified email middleware:

```text
https://evolve.test/workbench
```

## Content Models

Content models are regular Laravel models that can be managed from the workbench. Creating one adds the Laravel pieces needed for that model:

- `app/Models/{Name}.php`
- a guarded migration
- a matching database table

The workbench then shows that model as a content section where its records can be edited.

## Forms

Forms are Livewire single-file components under `resources/views/forms`. Creating a form in the workbench generates a component with form markup, validation-ready PHP, and preview usage via the `forms::` Livewire namespace.

## Artifact Paths

Pages and forms use their slug as the canonical location: `/contact` maps to `resources/views/forms/contact.blade.php`, and `/about` maps to `resources/views/pages/about.blade.php`. Components, layouts, and styles use path metadata instead because they are not routed artifacts.

## Content Helpers

Evolve exposes small PHP helpers for Blade templates and Livewire SFC PHP:

```blade
@foreach (evolve_navigation() as $page)
    <a href="{{ $page['route'] }}">{{ $page['name'] }}</a>
@endforeach

@foreach (evolve_child_pages() as $page)
    <a href="{{ $page['route'] }}">{{ $page['name'] }}</a>
@endforeach

{{ evolve_snippet('labels/badge', ['label' => 'New']) }}
```

- `evolve_navigation($parentId = null, $maxDepth = null)` returns a nested page tree.
- `evolve_child_pages($pageId = null)` returns direct children for a page, defaulting to the current route.
- `evolve_sibling_pages($pageId = null)` returns pages with the same parent, defaulting to the current route.
- `evolve_metadata($key = null, $default = null, $pageId = null)` returns current page metadata and core page fields.
- `evolve_snippet($id, $data = [])` renders a Blade snippet from `resources/views/snippets`.

## Verification

Run the test suite:

```bash
composer test
```

Run the frontend build:

```bash
npm run build
```

Format PHP:

```bash
composer lint
```

## License

Evolve is open-sourced under the GNU Affero General Public License v3.0 or later. See [`LICENSE`](LICENSE).
