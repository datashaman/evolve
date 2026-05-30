<?php

namespace Tests\Unit;

use App\Http\Controllers\EvolvePreviewController;
use App\Services\EvolveLibrary;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Tests\TestCase;

class EvolveLibraryViewsTest extends TestCase
{
    private string $originalBasePath;

    private string $testBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalBasePath = app()->basePath();
        $this->testBasePath = storage_path('framework/testing/evolve-views-'.Str::random(8));

        File::ensureDirectoryExists($this->testBasePath.'/resources/views');
        File::ensureDirectoryExists($this->testBasePath.'/resources/evolve');
        app()->setBasePath($this->testBasePath);
    }

    protected function tearDown(): void
    {
        app()->setBasePath($this->originalBasePath);
        File::deleteDirectory($this->testBasePath);

        parent::tearDown();
    }

    public function test_views_are_discovered_from_disk_excluding_kind_dirs(): void
    {
        File::put(resource_path('views/dashboard.blade.php'), '<h1>Dashboard</h1>');
        File::put(resource_path('views/welcome.blade.php'), '<h1>Welcome</h1>');
        File::ensureDirectoryExists(resource_path('views/partials'));
        File::put(resource_path('views/partials/head.blade.php'), '<title>App</title>');
        File::ensureDirectoryExists(resource_path('views/components'));
        File::put(resource_path('views/components/hidden.blade.php'), '<div>Hidden</div>');
        File::put(resource_path('views/workbench.blade.php'), '<div>workbench shell</div>');
        File::ensureDirectoryExists(resource_path('views/evolve'));
        File::put(resource_path('views/evolve/preview.blade.php'), '<div>preview shell</div>');

        $views = (new EvolveLibrary)->all()['views'];
        $ids = collect($views)->pluck('id')->all();

        sort($ids);
        $this->assertSame(['dashboard', 'partials/head', 'welcome'], $ids);
    }

    public function test_views_carry_starter_kit_flag_and_default_usage(): void
    {
        File::put(resource_path('views/dashboard.blade.php'), '<h1>Dashboard</h1>');
        File::ensureDirectoryExists(resource_path('views/partials'));
        File::put(resource_path('views/partials/head.blade.php'), '<title>App</title>');

        $views = collect((new EvolveLibrary)->all()['views']);

        $dashboard = $views->firstWhere('id', 'dashboard');
        $head = $views->firstWhere('id', 'partials/head');

        $this->assertTrue($dashboard['is_starter_kit']);
        $this->assertSame("@include('dashboard')", $dashboard['usage']);
        $this->assertSame('/dashboard', $dashboard['route']);
        $this->assertSame('dashboard', $dashboard['route_name']);
        $this->assertSame(['auth', 'verified'], $dashboard['middleware']);
        $this->assertSame('<h1>Dashboard</h1>', $dashboard['blade']);

        $this->assertTrue($head['is_starter_kit']);
        $this->assertSame('', $head['route']);
        $this->assertSame("@include('partials.head')", $head['usage']);
    }

    public function test_route_backed_views_expose_public_routes_for_preview(): void
    {
        File::put(resource_path('views/dashboard.blade.php'), '<h1>Dashboard</h1>');
        File::put(resource_path('views/welcome.blade.php'), '<h1>Welcome</h1>');

        $views = collect((new EvolveLibrary)->all()['views']);

        $dashboard = $views->firstWhere('id', 'dashboard');
        $welcome = $views->firstWhere('id', 'welcome');

        $this->assertSame('/dashboard', $dashboard['route']);
        $this->assertSame('dashboard', $dashboard['route_name']);
        $this->assertSame(['auth', 'verified'], $dashboard['middleware']);
        $this->assertSame('/', $welcome['route']);
        $this->assertSame('home', $welcome['route_name']);
        $this->assertSame([], $welcome['middleware']);
    }

    public function test_view_writes_snapshot_starter_kit_originals_and_can_be_restored(): void
    {
        File::put(resource_path('views/dashboard.blade.php'), '<h1>Original</h1>');

        $library = new EvolveLibrary;

        $library->writeArtifact('view', 'dashboard', [
            'id' => 'dashboard',
            'name' => 'Dashboard',
            'path' => 'resources/views/dashboard.blade.php',
            'blade' => '<h1>Edited</h1>',
        ]);

        $this->assertSame("<h1>Edited</h1>\n", File::get(resource_path('views/dashboard.blade.php')));
        $this->assertSame('<h1>Original</h1>', File::get(resource_path('evolve/originals/views/dashboard.blade.php')));

        $restored = $library->restoreArtifactOriginal('view', 'dashboard');

        $this->assertNotEmpty($restored);
        $this->assertSame('<h1>Original</h1>', File::get(resource_path('views/dashboard.blade.php')));
    }

    public function test_workbench_internal_views_remain_locked(): void
    {
        File::put(resource_path('views/workbench.blade.php'), '<div>workbench shell</div>');

        $library = new EvolveLibrary;

        try {
            $library->writeArtifact('view', 'workbench', [
                'id' => 'workbench',
                'path' => 'resources/views/workbench.blade.php',
                'blade' => '<div>nope</div>',
            ]);
            $this->fail('Workbench-internal view write should have been blocked.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('protected', $e->getMessage());
        }

        $this->assertSame('<div>workbench shell</div>', File::get(resource_path('views/workbench.blade.php')));
    }

    public function test_view_role_classifies_pages_partials_and_kit_internals(): void
    {
        File::put(resource_path('views/dashboard.blade.php'), '<h1>Dashboard</h1>');
        File::ensureDirectoryExists(resource_path('views/partials'));
        File::put(resource_path('views/partials/head.blade.php'), '<title>App</title>');
        File::ensureDirectoryExists(resource_path('views/flux/icon'));
        File::put(resource_path('views/flux/icon/book-open-text.blade.php'), '<svg></svg>');

        $library = new EvolveLibrary;
        $views = collect($library->all()['views']);

        $this->assertSame('page', $views->firstWhere('id', 'dashboard')['view_role']);
        $this->assertFalse($views->firstWhere('id', 'dashboard')['is_hidden']);
        $this->assertSame('partial', $views->firstWhere('id', 'partials/head')['view_role']);
        $this->assertFalse($views->firstWhere('id', 'partials/head')['is_hidden']);
        $this->assertSame('kit_internal', $views->firstWhere('id', 'flux/icon/book-open-text')['view_role']);
        $this->assertTrue($views->firstWhere('id', 'flux/icon/book-open-text')['is_hidden']);
    }

    public function test_non_partial_view_preview_renders_without_preview_shell(): void
    {
        File::ensureDirectoryExists(resource_path('views/evolve'));
        File::put(resource_path('views/evolve/preview.blade.php'), '<body></body>');
        File::put(resource_path('views/dashboard.blade.php'), '<h1>Dashboard</h1>');
        File::put(resource_path('evolve/manifest.json'), json_encode([
            'views' => [[
                'id' => 'dashboard',
                'usage' => '<h1>Dashboard</h1>',
            ]],
        ], JSON_PRETTY_PRINT));

        $response = app(EvolvePreviewController::class)->show(new EvolveLibrary, 'view', 'dashboard');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('<h1>Dashboard</h1>', $response->getContent());
    }

    public function test_partial_view_preview_keeps_framed_canvas(): void
    {
        File::ensureDirectoryExists(resource_path('views/evolve'));
        File::put(resource_path('views/evolve/preview.blade.php'), '<body></body>');
        File::ensureDirectoryExists(resource_path('views/partials'));
        File::put(resource_path('views/partials/head.blade.php'), '<title>App</title>');
        File::put(resource_path('evolve/manifest.json'), json_encode([
            'views' => [[
                'id' => 'partials/head',
                'usage' => '<title>App</title>',
            ]],
        ], JSON_PRETTY_PRINT));

        $response = app(EvolvePreviewController::class)->show(new EvolveLibrary, 'view', 'partials/head');

        $this->assertInstanceOf(View::class, $response);
        $this->assertArrayNotHasKey('full_bleed', $response->getData());
    }

    public function test_non_starter_kit_views_can_be_created_without_snapshot(): void
    {
        $library = new EvolveLibrary;

        $library->writeArtifact('view', 'admin/banner', [
            'id' => 'admin/banner',
            'name' => 'Banner',
            'path' => 'resources/views/admin/banner.blade.php',
            'blade' => '<div>banner</div>',
        ]);

        $this->assertTrue(File::exists(resource_path('views/admin/banner.blade.php')));
        $this->assertFalse(File::exists(resource_path('evolve/originals/views/admin/banner.blade.php')));

        $view = collect($library->all()['views'])->firstWhere('id', 'admin/banner');
        $this->assertFalse($view['is_starter_kit']);
        $this->assertFalse($view['has_original']);
    }
}
