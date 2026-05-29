# Evolve

Evolve is a Laravel and Livewire-native workbench for building sites from the same artifacts the application runs in production: layouts, styles, components, pages, and database-backed content models.

The goal is not to maintain a separate CMS renderer. The workbench edits framework files and previews the real Laravel/Livewire runtime.

## Current Shape

- Authenticated workbench at `/workbench`
- Native Livewire single-file components for pages and components
- Blade layout files in the Laravel view tree
- Orderable global style files, including `tokens.css`
- Dynamic content models backed by normal Laravel models, migrations, and database tables
- Runtime page routes generated from the Evolve library manifest
- Growth sync workflow for project/spec traceability

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

## Source Layout

```text
app/Http/Controllers/EvolveContentController.php
app/Http/Controllers/EvolveLibraryController.php
app/Http/Controllers/EvolvePreviewController.php
app/Services/EvolveLibrary.php
app/Services/EvolveContentModelScaffolder.php
resources/evolve/manifest.json
resources/views/workbench.blade.php
resources/views/pages/
resources/views/components/
resources/views/layouts/
```

The workbench treats these as native application artifacts. A page, layout, component, or style should be understandable from the filesystem without a hidden generated representation.

## Content Models

Content models are regular Laravel models. Creating a content model from the workbench scaffolds:

- `app/Models/{Name}.php`
- a guarded migration
- a matching database table

The current content index assumes the model table has the workbench content fields:

- `icon`
- `title`
- `summary`
- `position`
- `is_published`
- timestamps

Model creation is a model-level action. Row creation happens only inside the selected model table.

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

## Growth Sync

This repo includes `.github/workflows/growth-sync.yml`.

Required GitHub configuration:

- Repository variable: `GROWTH_URL=https://growth.datashaman.com/`
- Repository secret: `GROWTH_MCP_TOKEN`
- Access from this repo to `datashaman/growth/actions/growth-sync@main`

The Growth project is bound to:

```text
datashaman/evolve
```
