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
