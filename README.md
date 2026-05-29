# Evolve

Evolve is a Laravel and Livewire-native workbench for building sites from the same artifacts the application runs in production: layouts, styles, components, and pages.

The goal is not to maintain a separate CMS renderer. The workbench edits framework files and previews the real Laravel/Livewire runtime.

## Current Shape

- Authenticated workbench at `/workbench`
- Native Livewire single-file components for pages and components
- Blade layout files in the Laravel view tree
- Orderable global style files, including `tokens.css`
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
