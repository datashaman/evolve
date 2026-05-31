<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use Illuminate\Database\Seeder;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->posts() as $post) {
            BlogPost::query()->updateOrCreate(
                ['slug' => $post['slug']],
                $post,
            );
        }
    }

    /**
     * @return array<int, array{icon: string, title: string, slug: string, summary: string, position: int, is_published: bool}>
     */
    private function posts(): array
    {
        return [
            [
                'icon' => '01',
                'title' => 'Designing with artifacts',
                'slug' => 'designing-with-artifacts',
                'summary' => <<<'TEXT'
A blog is easier to maintain when its repeated structure is represented as artifacts instead of copied markup. The page should decide what appears, while the layout and snippets decide how recurring pieces behave.

This keeps future changes small. A navigation change belongs in the layout, a card treatment belongs in the snippet, and broad visual decisions belong in the shared stylesheet.

Reusable artifacts are not ceremony; they are the smallest useful boundary for change.
TEXT,
                'position' => 1,
                'is_published' => true,
            ],
            [
                'icon' => '02',
                'title' => 'Shipping smaller pages',
                'slug' => 'shipping-smaller-pages',
                'summary' => <<<'TEXT'
Route files should be readable at a glance. They work best when they assemble data and reusable pieces, rather than becoming the only place where design and content rules live.

For small sites, this can be as simple as one layout, one card snippet, and a page that loops through a compact content model.

Small pages are easier to preview, review, and replace.
TEXT,
                'position' => 2,
                'is_published' => true,
            ],
            [
                'icon' => '03',
                'title' => 'Clearer content systems',
                'slug' => 'clearer-content-systems',
                'summary' => <<<'TEXT'
Content systems do not need to be complex to be useful. They need stable names, obvious ownership, and enough structure for teams to make changes without hunting through unrelated files.

A basic blog can demonstrate that discipline: posts have routes, cards have one shared template, and the page tree makes navigation explicit.

Clarity compounds when the file model matches how people talk about the site.
TEXT,
                'position' => 3,
                'is_published' => true,
            ],
            [
                'icon' => '04',
                'title' => 'The power of MCP tools',
                'slug' => 'the-power-of-mcp-tools',
                'summary' => <<<'TEXT'
MCP tools change where generation work happens. Instead of asking the server to invent, render, and remember every operation, the client can generate intent and call explicit tools that mutate durable state.

That split matters. The server becomes a pure state holder and workflow system: it validates inputs, applies permissions, records artifacts, and exposes the next safe operation. The expensive creative loop stays on the client side, where an agent can spend tokens, compare options, and decide which structured action to take.

This makes the product easier to reason about. A tool call has a schema, a dry-run mode, and a clear write boundary. The server does not need to understand every possible design conversation. It only needs to preserve the source of truth and enforce the rules that keep the workspace coherent.

The result is a better contract between human, agent, and application. The agent can explore freely, but the app remains authoritative about what exists, what can change, and how a change enters the system. MCP turns generation into a client-side capability and leaves the server focused on state, safety, and workflow.
TEXT,
                'position' => 4,
                'is_published' => true,
            ],
        ];
    }
}
