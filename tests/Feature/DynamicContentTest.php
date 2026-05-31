<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EvolveContentModelScaffolder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DynamicContentTest extends TestCase
{
    use RefreshDatabase;

    private string $originalBasePath;

    private string $testBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->originalBasePath = app()->basePath();
        $this->testBasePath = storage_path('framework/testing/evolve-content-'.Str::random(8));

        File::ensureDirectoryExists($this->testBasePath.'/app/Models');
        File::ensureDirectoryExists($this->testBasePath.'/resources/evolve');
        File::ensureDirectoryExists($this->testBasePath.'/resources/views');
        File::put($this->testBasePath.'/resources/views/welcome.blade.php', '<div>Welcome</div>');
        File::put($this->testBasePath.'/resources/evolve/manifest.json', json_encode([
            'styles' => [],
            'components' => [],
            'forms' => [],
            'layouts' => [],
            'pages' => [],
        ], JSON_PRETTY_PRINT)."\n");
        File::put($this->testBasePath.'/app/Models/DynamicContentFixture.php', $this->fixtureModelSource());
        app()->setBasePath($this->testBasePath);

        if (! class_exists('App\\Models\\DynamicContentFixture', false)) {
            require_once $this->testBasePath.'/app/Models/DynamicContentFixture.php';
        }

        Schema::create('dynamic_content_fixtures', function ($table): void {
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
        Schema::dropIfExists('dynamic_content_fixtures');
        File::deleteDirectory($this->testBasePath);

        parent::tearDown();
    }

    public function test_home_route_renders_without_demo_artifacts(): void
    {
        $this->get(route('home'))
            ->assertOk();
    }

    public function test_workbench_content_api_updates_discovered_content_models(): void
    {
        $this->actingAs(User::factory()->create([
            'email_verified_at' => now(),
        ]));

        $response = $this->putJson('/api/content', [
            'data' => [
                'dynamic_content_fixtures' => [
                    [
                        'icon' => '01',
                        'title' => 'Edited row',
                        'summary' => 'Edited through the workbench API.',
                        'position' => 1,
                        'is_published' => true,
                    ],
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.dynamic_content_fixtures.0.title', 'Edited row')
            ->assertJsonPath('data.dynamic_content_fixtures.0.client_id', '');

        $this->assertDatabaseHas('dynamic_content_fixtures', [
            'title' => 'Edited row',
            'summary' => 'Edited through the workbench API.',
        ]);

        $id = $response->json('data.dynamic_content_fixtures.0.id');

        $this->putJson('/api/content', [
            'data' => [
                'dynamic_content_fixtures' => [
                    [
                        'id' => $id,
                        'icon' => '02',
                        'title' => 'Edited again',
                        'summary' => 'Updated without creating a duplicate.',
                        'position' => 1,
                        'is_published' => true,
                    ],
                ],
            ],
        ])->assertOk();

        $fixture = $this->fixtureModelClass();

        $this->assertSame(1, $fixture::query()->count());
        $this->assertDatabaseHas('dynamic_content_fixtures', [
            'id' => $id,
            'title' => 'Edited again',
        ]);
    }

    public function test_workbench_content_api_deletes_rows_missing_from_payload(): void
    {
        $this->actingAs(User::factory()->create([
            'email_verified_at' => now(),
        ]));

        $fixture = $this->fixtureModelClass();
        $kept = $fixture::query()->create([
            'icon' => '01',
            'title' => 'Keep this',
            'summary' => 'Still present.',
            'position' => 1,
            'is_published' => true,
        ]);

        $deleted = $fixture::query()->create([
            'icon' => '02',
            'title' => 'Delete this',
            'summary' => 'Removed from the table.',
            'position' => 2,
            'is_published' => true,
        ]);

        $this->putJson('/api/content', [
            'data' => [
                'dynamic_content_fixtures' => [
                    [
                        'id' => (string) $kept->id,
                        'icon' => '01',
                        'title' => 'Keep this',
                        'summary' => 'Still present.',
                        'position' => 1,
                        'is_published' => true,
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('dynamic_content_fixtures', ['id' => $kept->id]);
        $this->assertDatabaseMissing('dynamic_content_fixtures', ['id' => $deleted->id]);
    }

    public function test_workbench_content_model_endpoint_invokes_scaffolder(): void
    {
        $this->actingAs(User::factory()->create([
            'email_verified_at' => now(),
        ]));

        $this->mock(EvolveContentModelScaffolder::class)
            ->shouldReceive('create')
            ->once()
            ->with('Case Study');

        $this->postJson('/api/content/models', [
            'name' => 'Case Study',
        ])->assertOk();
    }

    public function test_workbench_content_api_does_not_touch_library_manifest(): void
    {
        $this->actingAs(User::factory()->create([
            'email_verified_at' => now(),
        ]));

        $manifestPath = resource_path('evolve/manifest.json');
        $before = File::get($manifestPath);

        $this->putJson('/api/content', [
            'data' => [
                'dynamic_content_fixtures' => [
                    [
                        'icon' => '01',
                        'title' => 'Database only',
                        'summary' => 'This should not rewrite artifact metadata.',
                        'position' => 1,
                        'is_published' => true,
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertSame($before, File::get($manifestPath));
    }

    private function fixtureModelClass(): string
    {
        return DynamicContentFixture::class;
    }

    private function fixtureModelSource(): string
    {
        return <<<'PHP'
<?php

namespace App\Models;

class DynamicContentFixture extends \Tests\Feature\DynamicContentFixture
{
    //
}
PHP;
    }
}

class DynamicContentFixture extends Model
{
    protected $table = 'dynamic_content_fixtures';

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
