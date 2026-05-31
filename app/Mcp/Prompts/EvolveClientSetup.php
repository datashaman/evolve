<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;

#[Title('Evolve Client Setup')]
#[Description('Onboard an MCP client or agent to the Evolve workbench workflow.')]
class EvolveClientSetup extends Prompt
{
    public function handle(Request $request): Response
    {
        return Response::text(<<<'MARKDOWN'
            You are connected to the Evolve MCP server.

            Evolve is a Laravel and Livewire workbench that edits real framework files through structured artifacts. Treat the manifest and library-backed MCP tools as the source of truth for workbench changes.

            First steps:
            - Use `list-artifacts` and `read-artifact` before changing workbench files.
            - Use `upsert-artifact`, `delete-artifact`, `restore-artifact`, and `reorder-styles` for artifact changes so path guards, protected-file checks, starter-kit snapshots, and manifest metadata stay consistent.
            - Use content tools for database-backed content models and rows.
            - Use `send-feedback` and `triage-feedback` only for developer/agent process feedback, not website content.

            Safety conventions:
            - Mutating tools default to `dry_run: true`; inspect the structured response before setting `dry_run: false`.
            - Destructive or restorative operations require `confirm_id` when `dry_run` is false.
            - Do not weaken protected-file behavior or write outside the workspace.
            - Prefer artifact boundaries: styles for shared CSS, layouts for page chrome, components for reusable Livewire units, snippets for static fragments, pages/forms for route orchestration.

            Direct file edits are acceptable for application code outside the artifact model, tests, migrations, and documentation. For artifact files, use MCP tools or the Evolve library/API unless the task explicitly requires lower-level application changes.

            Read the `evolve://guides/workflow` MCP resource for the fuller workflow reference.
            MARKDOWN);
    }
}
