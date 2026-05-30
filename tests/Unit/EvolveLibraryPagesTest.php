<?php

namespace Tests\Unit;

use App\Services\EvolveLibrary;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class EvolveLibraryPagesTest extends TestCase
{
    private string $originalBasePath;

    private string $testBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalBasePath = app()->basePath();
        $this->testBasePath = storage_path('framework/testing/evolve-library-'.Str::random(8));

        File::ensureDirectoryExists($this->testBasePath.'/resources/evolve');
        app()->setBasePath($this->testBasePath);
    }

    protected function tearDown(): void
    {
        app()->setBasePath($this->originalBasePath);
        File::deleteDirectory($this->testBasePath);

        parent::tearDown();
    }

    public function test_pages_are_written_to_files_derived_from_their_paths(): void
    {
        $library = new EvolveLibrary;

        $library->write([
            'pages' => [
                [
                    'id' => 'new-12345678',
                    'name' => 'New page',
                    'path' => 'resources/views/pages/new.blade.php',
                    'php' => <<<'PHP'
use Livewire\Component;

new class extends Component {
    //
};
PHP,
                    'blade' => '<div>New page</div>',
                    'usage' => '<livewire:pages::new />',
                ],
            ],
        ]);

        $this->assertTrue(File::exists(resource_path('views/pages/new.blade.php')));
        $this->assertFalse(File::exists(resource_path('views/pages/new-12345678.blade.php')));

        $page = $library->all()['pages'][0];

        $this->assertSame('page', $page['kind']);
        $this->assertSame('new', $page['id']);
        $this->assertArrayNotHasKey('slug', $page);
        $this->assertSame('resources/views/pages/new.blade.php', $page['path']);
        $this->assertSame('/new', $page['route']);
        $this->assertSame('resources/views/pages/new.blade.php', $page['source_path']);
        $this->assertSame('pages::new', $page['component']);
        $this->assertSame('<livewire:pages::new />', $page['usage']);
    }

    public function test_root_page_keeps_its_existing_id(): void
    {
        $library = new EvolveLibrary;

        $library->write([
            'pages' => [
                [
                    'id' => 'home',
                    'name' => 'Home',
                    'path' => 'resources/views/pages/home.blade.php',
                    'route' => '/',
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Home</div>',
                    'usage' => '<livewire:pages::home />',
                ],
            ],
        ]);

        $page = $library->all()['pages'][0];

        $this->assertTrue(File::exists(resource_path('views/pages/home.blade.php')));
        $this->assertSame('home', $page['id']);
        $this->assertArrayNotHasKey('slug', $page);
        $this->assertSame('resources/views/pages/home.blade.php', $page['path']);
        $this->assertSame('/', $page['route']);
        $this->assertSame('pages::home', $page['component']);
    }

    public function test_pages_can_define_dynamic_routes_separate_from_their_file_path(): void
    {
        $library = new EvolveLibrary;

        $library->write([
            'pages' => [
                [
                    'id' => 'new-12345678',
                    'name' => 'Resource detail',
                    'path' => 'resources/views/pages/resources/show.blade.php',
                    'route' => '/resources/{resource}',
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Resource detail</div>',
                    'usage' => '<livewire:pages::resources.show />',
                ],
            ],
        ]);

        $page = $library->all()['pages'][0];

        $this->assertTrue(File::exists(resource_path('views/pages/resources/show.blade.php')));
        $this->assertSame('resources/show', $page['id']);
        $this->assertArrayNotHasKey('slug', $page);
        $this->assertSame('resources/views/pages/resources/show.blade.php', $page['path']);
        $this->assertSame('/resources/{resource}', $page['route']);
        $this->assertSame('resources/views/pages/resources/show.blade.php', $page['source_path']);
        $this->assertSame('pages::resources.show', $page['component']);
        $this->assertSame([
            [
                'route' => '/resources/{resource}',
                'route_name' => 'resources.resource',
                'middleware' => [],
                'component' => 'pages::resources.show',
            ],
        ], $library->pageRoutes());
    }

    public function test_pages_persist_route_name_and_middleware(): void
    {
        $library = new EvolveLibrary;

        $library->write([
            'pages' => [
                [
                    'id' => 'profile',
                    'name' => 'Profile',
                    'path' => 'resources/views/pages/profile.blade.php',
                    'route' => '/profile',
                    'route_name' => 'profile.show',
                    'middleware' => ['auth', 'verified'],
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Profile</div>',
                ],
            ],
        ]);

        $page = $library->all()['pages'][0];

        $this->assertSame('profile.show', $page['route_name']);
        $this->assertSame(['auth', 'verified'], $page['middleware']);
        $this->assertSame([
            [
                'route' => '/profile',
                'route_name' => 'profile.show',
                'middleware' => ['auth', 'verified'],
                'component' => 'pages::profile',
            ],
        ], $library->pageRoutes());
    }

    public function test_pages_keep_tree_metadata_and_are_sorted_by_parent_order(): void
    {
        $library = new EvolveLibrary;

        $library->write([
            'pages' => [
                [
                    'name' => 'Child B',
                    'path' => 'resources/views/pages/parent/child-b.blade.php',
                    'route' => '/parent/child-b',
                    'parent_id' => 'parent',
                    'order' => 2,
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Child B</div>',
                ],
                [
                    'name' => 'Parent',
                    'path' => 'resources/views/pages/parent.blade.php',
                    'route' => '/parent',
                    'order' => 1,
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Parent</div>',
                ],
                [
                    'name' => 'Child A',
                    'path' => 'resources/views/pages/parent/child-a.blade.php',
                    'route' => '/parent/child-a',
                    'parent_id' => 'parent',
                    'order' => 1,
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Child A</div>',
                ],
            ],
        ]);

        $pages = $library->all()['pages'];

        $this->assertSame(['parent', 'parent/child-a', 'parent/child-b'], array_column($pages, 'id'));
        $this->assertSame('', $pages[0]['parent_id']);
        $this->assertSame('parent', $pages[1]['parent_id']);
        $this->assertSame(0, $pages[0]['depth']);
        $this->assertSame(1, $pages[1]['depth']);
        $this->assertSame(1, $pages[1]['order']);
        $this->assertSame(2, $pages[2]['order']);
    }

    public function test_invalid_or_cyclic_page_parents_are_cleared(): void
    {
        $library = new EvolveLibrary;

        $library->write([
            'pages' => [
                [
                    'name' => 'A',
                    'path' => 'resources/views/pages/a.blade.php',
                    'route' => '/a',
                    'parent_id' => 'b',
                    'php' => $this->componentPhp(),
                    'blade' => '<div>A</div>',
                ],
                [
                    'name' => 'B',
                    'path' => 'resources/views/pages/b.blade.php',
                    'route' => '/b',
                    'parent_id' => 'a',
                    'php' => $this->componentPhp(),
                    'blade' => '<div>B</div>',
                ],
                [
                    'name' => 'Orphan',
                    'path' => 'resources/views/pages/orphan.blade.php',
                    'route' => '/orphan',
                    'parent_id' => 'missing',
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Orphan</div>',
                ],
            ],
        ]);

        $parents = collect($library->all()['pages'])->pluck('parent_id', 'id')->all();

        $this->assertSame('', $parents['a']);
        $this->assertSame('', $parents['b']);
        $this->assertSame('', $parents['orphan']);
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
