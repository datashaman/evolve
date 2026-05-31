<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Title('Evolve Workflow Guide')]
#[Description('Reference guidance for MCP clients and agents working with Evolve.')]
#[Uri('evolve://guides/workflow')]
#[MimeType('text/markdown')]
class EvolveWorkflowGuide extends Resource
{
    public function handle(Request $request): Response
    {
        return Response::text(<<<'MARKDOWN'
            # Evolve MCP Workflow Guide

            ## Workbench Model

            Evolve manages real Laravel and Livewire files through a structured artifact model. The manifest identifies workbench artifacts, and the Evolve library maps artifact operations to filesystem changes.

            Use artifact boundaries deliberately:
            - `style` artifacts contain shared CSS, design tokens, and visual foundations.
            - `layout` artifacts contain repeated page chrome and structural wrappers.
            - `component` artifacts contain reusable Livewire single-file components.
            - `form` artifacts contain Livewire single-file form components.
            - `snippet` artifacts contain Blade-only reusable fragments.
            - `page` artifacts contain route-specific orchestration and content.
            - `view` artifacts cover plain Blade files not owned by another typed artifact kind.

            ## Tool Sequence

            1. Inspect existing state with `list-artifacts`, `read-artifact`, `list-content-models`, or `list-content-rows`.
            2. Plan the smallest artifact/content operation that preserves reuse and page structure.
            3. Run mutating tools in dry-run mode first.
            4. Inspect the structured response.
            5. Re-run with `dry_run: false` only after the planned result is correct.
            6. Run focused tests and build checks appropriate to the change.

            ## Safety Rules

            Mutating tools default to dry-run. Destructive and restorative operations require `confirm_id` when dry-run is disabled. Keep writes inside the workspace and do not weaken protected-file handling.

            Protected starter-kit and workbench shell files exist because the workbench shares framework files with the application. When in doubt, use MCP tools or the Evolve library/API instead of direct artifact file edits.

            ## Feedback Channel

            `send-feedback` and `triage-feedback` are a separate developer/agent process channel. Use them to record process problems, tool guidance gaps, and improvement opportunities. Do not use feedback rows as website content or artifact metadata.

            ## Direct Edits

            Direct file edits are appropriate for application code outside the artifact model, tests, migrations, documentation, MCP server implementation, and service code. Direct artifact file edits should be a last resort because they can bypass manifest consistency, starter-kit snapshot/restore behavior, and path safety checks.
            MARKDOWN);
    }
}
