<?php

namespace Tests\Feature;

use App\Mcp\Servers\EvolveServer;
use App\Mcp\Tools\CreateContentModel;
use App\Mcp\Tools\DeleteArtifact;
use App\Mcp\Tools\DeleteContentRow;
use App\Mcp\Tools\ListArtifacts;
use App\Mcp\Tools\ListContentModels;
use App\Mcp\Tools\ListContentRows;
use App\Mcp\Tools\ReadArtifact;
use App\Mcp\Tools\ReorderStyles;
use App\Mcp\Tools\RestoreArtifact;
use App\Mcp\Tools\UpsertArtifact;
use App\Mcp\Tools\UpsertContentRow;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class EvolveMcpServerTest extends TestCase
{
    use RefreshDatabase;

    private string $originalBasePath;

    private string $testBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalBasePath = app()->basePath();
        $this->testBasePath = storage_path('framework/testing/evolve-mcp-'.Str::random(8));

        File::ensureDirectoryExists($this->testBasePath.'/resources/evolve');
        File::ensureDirectoryExists($this->testBasePath.'/app/Models');
        File::put($this->testBasePath.'/app/Models/McpFixture.php', $this->fixtureModelSource());
        app()->setBasePath($this->testBasePath);

        if (! class_exists('App\\Models\\McpFixture', false)) {
            require_once $this->testBasePath.'/app/Models/McpFixture.php';
        }

        Schema::create('mcp_fixtures', function ($table): void {
            $table->id();
            $table->string('icon', 12);
            $table->string('title');
            $table->text('summary');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        app()->setBasePath($this->originalBasePath);
        Schema::dropIfExists('mcp_fixtures');
        File::deleteDirectory($this->testBasePath);

        parent::tearDown();
    }

    public function test_artifact_tools_list_read_and_dry_run_without_writing(): void
    {
        File::ensureDirectoryExists(resource_path('evolve'));

        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'component',
            'name' => 'Hero',
            'path' => 'resources/views/components/hero.blade.php',
            'php' => $this->componentPhp(),
            'blade' => '<div>Hero</div>',
            'dry_run' => false,
        ])->assertOk();

        EvolveServer::tool(ListArtifacts::class, ['kind' => 'component'])
            ->assertOk()
            ->assertSee('hero');

        EvolveServer::tool(ReadArtifact::class, ['kind' => 'component', 'id' => 'hero'])
            ->assertOk()
            ->assertSee('<div>Hero</div>');

        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'component',
            'id' => 'hero',
            'path' => 'resources/views/components/hero.blade.php',
            'php' => $this->componentPhp(),
            'blade' => '<div>Changed</div>',
        ])->assertOk()->assertSee('"dry_run":true');

        $this->assertStringContainsString('<div>Hero</div>', File::get(resource_path('views/components/hero.blade.php')));
    }

    public function test_artifact_delete_requires_confirmation(): void
    {
        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'component',
            'name' => 'Card',
            'path' => 'resources/views/components/card.blade.php',
            'php' => $this->componentPhp(),
            'blade' => '<div>Card</div>',
            'dry_run' => false,
        ])->assertOk();

        EvolveServer::tool(DeleteArtifact::class, [
            'kind' => 'component',
            'id' => 'card',
            'dry_run' => false,
        ])->assertHasErrors(['confirm_id']);

        EvolveServer::tool(DeleteArtifact::class, [
            'kind' => 'component',
            'id' => 'card',
            'confirm_id' => 'card',
            'dry_run' => false,
        ])->assertOk();

        $this->assertFalse(File::exists(resource_path('views/components/card.blade.php')));
    }

    public function test_style_reorder_dry_run_does_not_change_manifest(): void
    {
        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'style',
            'name' => 'Base',
            'path' => 'resources/css/base.css',
            'style' => 'body {}',
            'dry_run' => false,
        ])->assertOk();
        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'style',
            'name' => 'Theme',
            'path' => 'resources/css/theme.css',
            'style' => ':root {}',
            'dry_run' => false,
        ])->assertOk();

        $before = File::get(resource_path('evolve/manifest.json'));

        EvolveServer::tool(ReorderStyles::class, [
            'ids' => ['theme', 'base'],
        ])->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('dry_run', true)
                ->where('style_ids', ['theme', 'base'])
                ->where('styles.0.id', 'theme')
                ->where('styles.1.id', 'base')
            );

        $this->assertSame($before, File::get(resource_path('evolve/manifest.json')));
    }

    public function test_mcp_artifact_writes_cannot_touch_workbench_assets(): void
    {
        File::ensureDirectoryExists(resource_path('css'));
        File::put(resource_path('css/app.css'), '/* workbench css */');

        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'style',
            'name' => 'App',
            'path' => 'resources/css/app.css',
            'style' => 'body { color: red; }',
            'dry_run' => false,
        ])->assertHasErrors(['protected']);

        $this->assertSame('/* workbench css */', File::get(resource_path('css/app.css')));
    }

    public function test_mcp_restore_artifact_round_trips_starter_kit_originals(): void
    {
        File::ensureDirectoryExists(resource_path('views/components'));
        File::put(resource_path('views/components/auth-header.blade.php'), 'original header');

        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'component',
            'name' => 'Auth header',
            'path' => 'resources/views/components/auth-header.blade.php',
            'php' => $this->componentPhp(),
            'blade' => '<div>Edited header</div>',
            'dry_run' => false,
        ])->assertOk();

        $this->assertStringContainsString('Edited header', File::get(resource_path('views/components/auth-header.blade.php')));

        EvolveServer::tool(RestoreArtifact::class, [
            'kind' => 'component',
            'id' => 'auth-header',
            'dry_run' => true,
        ])->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('dry_run', true)
                ->where('restored', [])
                ->etc()
            );

        $this->assertStringContainsString('Edited header', File::get(resource_path('views/components/auth-header.blade.php')));

        EvolveServer::tool(RestoreArtifact::class, [
            'kind' => 'component',
            'id' => 'auth-header',
            'dry_run' => false,
        ])->assertHasErrors(['confirm_id']);

        EvolveServer::tool(RestoreArtifact::class, [
            'kind' => 'component',
            'id' => 'auth-header',
            'confirm_id' => 'auth-header',
            'dry_run' => false,
        ])->assertOk();

        $this->assertSame('original header', File::get(resource_path('views/components/auth-header.blade.php')));
    }

    public function test_mcp_restore_artifact_rejects_non_starter_kit_artifacts(): void
    {
        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'component',
            'name' => 'Hero',
            'path' => 'resources/views/components/hero.blade.php',
            'php' => $this->componentPhp(),
            'blade' => '<div>Hero</div>',
            'dry_run' => false,
        ])->assertOk();

        EvolveServer::tool(RestoreArtifact::class, [
            'kind' => 'component',
            'id' => 'hero',
            'dry_run' => true,
        ])->assertHasErrors(['starter-kit']);
    }

    public function test_mcp_page_artifacts_accept_tree_metadata(): void
    {
        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'page',
            'name' => 'Parent',
            'path' => 'resources/views/pages/parent.blade.php',
            'route' => '/parent',
            'order' => 1,
            'php' => $this->componentPhp(),
            'blade' => '<div>Parent</div>',
            'dry_run' => false,
        ])->assertOk();

        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'page',
            'name' => 'Child',
            'path' => 'resources/views/pages/parent/child.blade.php',
            'route' => '/parent/child',
            'parent_id' => 'parent',
            'order' => 1,
            'php' => $this->componentPhp(),
            'blade' => '<div>Child</div>',
            'dry_run' => false,
        ])->assertOk();

        EvolveServer::tool(ListArtifacts::class, ['kind' => 'page'])
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('artifacts.pages.0.id', 'parent')
                ->where('artifacts.pages.0.depth', 0)
                ->where('artifacts.pages.1.id', 'parent/child')
                ->where('artifacts.pages.1.parent_id', 'parent')
                ->where('artifacts.pages.1.depth', 1)
            );
    }

    public function test_mcp_artifact_tools_manage_snippets(): void
    {
        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'snippet',
            'name' => 'Badge',
            'path' => 'resources/views/snippets/marketing/badge.blade.php',
            'blade' => '<span>Badge</span>',
            'dry_run' => false,
        ])->assertOk();

        EvolveServer::tool(ListArtifacts::class, ['kind' => 'snippet'])
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('artifacts.snippets.0.id', 'marketing/badge')
                ->where('artifacts.snippets.0.component', 'snippets::marketing.badge')
                ->where('artifacts.snippets.0.usage', '<x-snippets::marketing.badge />')
            );

        EvolveServer::tool(ReadArtifact::class, ['kind' => 'snippet', 'id' => 'marketing/badge'])
            ->assertOk()
            ->assertSee('<span>Badge</span>');

        $this->assertSame('<span>Badge</span>', trim(File::get(resource_path('views/snippets/marketing/badge.blade.php'))));
    }

    public function test_mcp_artifact_tools_manage_route_backed_views(): void
    {
        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'view',
            'name' => 'Landing',
            'path' => 'resources/views/marketing/landing.blade.php',
            'route' => '/landing',
            'route_name' => 'landing',
            'middleware' => ['throttle:60,1'],
            'blade' => '<h1>Landing</h1>',
            'dry_run' => false,
        ])->assertOk();

        EvolveServer::tool(ListArtifacts::class, ['kind' => 'view'])
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('artifacts.views.0.id', 'marketing/landing')
                ->where('artifacts.views.0.route', '/landing')
                ->where('artifacts.views.0.route_name', 'landing')
                ->where('artifacts.views.0.middleware.0', 'throttle:60,1')
            );

        $this->assertSame('<h1>Landing</h1>', trim(File::get(resource_path('views/marketing/landing.blade.php'))));
    }

    public function test_mcp_metadata_only_updates_preserve_existing_view_content(): void
    {
        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'view',
            'name' => 'Landing',
            'path' => 'resources/views/marketing/landing.blade.php',
            'route' => '/landing',
            'route_name' => 'landing',
            'blade' => '<h1>Landing</h1>',
            'dry_run' => false,
        ])->assertOk();

        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'view',
            'id' => 'marketing/landing',
            'name' => 'Landing',
            'path' => 'resources/views/marketing/landing.blade.php',
            'route' => '/renamed-landing',
            'route_name' => 'renamed.landing',
        ])->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('dry_run', true)
                ->where('artifact.blade', File::get(resource_path('views/marketing/landing.blade.php')))
                ->etc()
            );

        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'view',
            'id' => 'marketing/landing',
            'name' => 'Landing',
            'path' => 'resources/views/marketing/landing.blade.php',
            'route' => '/renamed-landing',
            'route_name' => 'renamed.landing',
            'dry_run' => false,
        ])->assertOk();

        $this->assertSame('<h1>Landing</h1>', trim(File::get(resource_path('views/marketing/landing.blade.php'))));
    }

    public function test_mcp_metadata_only_updates_preserve_existing_sfc_content(): void
    {
        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'page',
            'name' => 'Landing',
            'path' => 'resources/views/pages/landing.blade.php',
            'route' => '/landing',
            'route_name' => 'landing',
            'php' => $this->componentPhp(),
            'blade' => '<div>Landing</div>',
            'style' => '.landing { color: red; }',
            'dry_run' => false,
        ])->assertOk();

        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'page',
            'id' => 'landing',
            'name' => 'Landing',
            'path' => 'resources/views/pages/landing.blade.php',
            'route' => '/home',
            'route_name' => 'pages.home',
        ])->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('dry_run', true)
                ->where('artifact.php', $this->componentPhp())
                ->where('artifact.blade', '<div>Landing</div>')
                ->where('artifact.style', '.landing { color: red; }')
                ->etc()
            );

        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'page',
            'id' => 'landing',
            'name' => 'Landing',
            'path' => 'resources/views/pages/landing.blade.php',
            'route' => '/home',
            'route_name' => 'pages.home',
            'dry_run' => false,
        ])->assertOk();

        $source = File::get(resource_path('views/pages/landing.blade.php'));

        $this->assertStringContainsString($this->componentPhp(), $source);
        $this->assertStringContainsString('<div>Landing</div>', $source);
        $this->assertStringContainsString('.landing { color: red; }', $source);
    }

    public function test_mcp_metadata_only_updates_preserve_layout_snippet_and_style_content(): void
    {
        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'layout',
            'name' => 'Marketing',
            'path' => 'resources/views/layouts/marketing.blade.php',
            'blade' => '<main>{{ $slot }}</main>',
            'style' => '.marketing { display: grid; }',
            'dry_run' => false,
        ])->assertOk();

        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'snippet',
            'name' => 'Badge',
            'path' => 'resources/views/snippets/badge.blade.php',
            'blade' => '<span>Badge</span>',
            'dry_run' => false,
        ])->assertOk();

        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'style',
            'name' => 'Theme',
            'path' => 'resources/css/theme.css',
            'style' => ':root { --accent: teal; }',
            'dry_run' => false,
        ])->assertOk();

        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'layout',
            'id' => 'marketing',
            'name' => 'Marketing Layout',
            'path' => 'resources/views/layouts/marketing.blade.php',
            'dry_run' => false,
        ])->assertOk();

        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'snippet',
            'id' => 'badge',
            'name' => 'Badge Snippet',
            'path' => 'resources/views/snippets/badge.blade.php',
            'dry_run' => false,
        ])->assertOk();

        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'style',
            'id' => 'theme',
            'name' => 'Theme Tokens',
            'path' => 'resources/css/theme.css',
            'dry_run' => false,
        ])->assertOk();

        $this->assertSame('<main>{{ $slot }}</main>', trim(File::get(resource_path('views/layouts/marketing.blade.php'))));
        $this->assertSame('.marketing { display: grid; }', trim(File::get(resource_path('css/layouts/marketing.css'))));
        $this->assertSame('<span>Badge</span>', trim(File::get(resource_path('views/snippets/badge.blade.php'))));
        $this->assertSame(':root { --accent: teal; }', trim(File::get(resource_path('css/theme.css'))));
    }

    public function test_mcp_upsert_distinguishes_omitted_content_from_explicit_empty_content(): void
    {
        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'view',
            'name' => 'Landing',
            'path' => 'resources/views/marketing/landing.blade.php',
            'route' => '/landing',
            'route_name' => 'landing',
            'blade' => '<h1>Landing</h1>',
            'dry_run' => false,
        ])->assertOk();

        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'view',
            'id' => 'marketing/landing',
            'name' => 'Landing',
            'path' => 'resources/views/marketing/landing.blade.php',
            'route' => '/landing',
            'route_name' => 'landing',
            'blade' => '',
            'dry_run' => false,
        ])->assertOk();

        $this->assertSame('', trim(File::get(resource_path('views/marketing/landing.blade.php'))));

        EvolveServer::tool(UpsertArtifact::class, [
            'kind' => 'component',
            'name' => 'Empty Defaults',
            'path' => 'resources/views/components/empty-defaults.blade.php',
            'dry_run' => false,
        ])->assertOk();

        $source = File::get(resource_path('views/components/empty-defaults.blade.php'));

        $this->assertStringContainsString('use Livewire\Component;', $source);
        $this->assertStringContainsString('<div></div>', $source);
    }

    public function test_content_tools_list_rows_and_upsert_granularly(): void
    {
        $fixture = $this->fixtureModelClass();
        $fixture::query()->create([
            'icon' => '01',
            'title' => 'Existing',
            'summary' => 'Keep me',
            'position' => 1,
            'is_published' => true,
        ]);

        EvolveServer::tool(ListContentModels::class)
            ->assertOk()
            ->assertSee('mcp_fixtures');

        EvolveServer::tool(ListContentRows::class, ['model' => 'mcp_fixtures'])
            ->assertOk()
            ->assertSee('Existing');

        EvolveServer::tool(UpsertContentRow::class, [
            'model' => 'mcp_fixtures',
            'title' => 'Draft row',
            'summary' => 'Preview only',
        ])->assertOk()->assertSee('"dry_run":true');

        $this->assertSame(1, $fixture::query()->count());

        EvolveServer::tool(UpsertContentRow::class, [
            'model' => 'mcp_fixtures',
            'title' => 'Created',
            'summary' => 'Written',
            'is_published' => true,
            'dry_run' => false,
        ])->assertOk()->assertSee('Created');

        $this->assertSame(2, $fixture::query()->count());
    }

    public function test_content_delete_requires_confirmation(): void
    {
        $fixture = $this->fixtureModelClass();
        $row = $fixture::query()->create([
            'icon' => '01',
            'title' => 'Delete me',
            'summary' => 'Temporary',
            'position' => 1,
            'is_published' => true,
        ]);

        EvolveServer::tool(DeleteContentRow::class, [
            'model' => 'mcp_fixtures',
            'id' => (string) $row->id,
            'dry_run' => false,
        ])->assertHasErrors(['confirm_id']);

        EvolveServer::tool(DeleteContentRow::class, [
            'model' => 'mcp_fixtures',
            'id' => (string) $row->id,
            'confirm_id' => (string) $row->id,
            'dry_run' => false,
        ])->assertOk();

        $this->assertDatabaseMissing('mcp_fixtures', ['id' => $row->id]);
    }

    public function test_content_model_creation_defaults_to_dry_run(): void
    {
        EvolveServer::tool(CreateContentModel::class, [
            'name' => 'Field Note',
        ])->assertOk()
            ->assertSee('"dry_run":true')
            ->assertSee('FieldNote')
            ->assertSee('field_notes');

        $this->assertFalse(File::exists(app_path('Models/FieldNote.php')));
    }

    private function fixtureModelClass(): string
    {
        return McpFixture::class;
    }

    private function fixtureModelSource(): string
    {
        return <<<'PHP'
<?php

namespace App\Models;

class McpFixture extends \Tests\Feature\McpFixture
{
    //
}
PHP;
    }

    private function componentPhp(): string
    {
        return <<<'PHP'
use Livewire\Component;

new class extends Component {
    //
};
PHP;
    }
}

class McpFixture extends Model
{
    protected $table = 'mcp_fixtures';

    protected $fillable = [
        'icon',
        'title',
        'summary',
        'position',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }
}
