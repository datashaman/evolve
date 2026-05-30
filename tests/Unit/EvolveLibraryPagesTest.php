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
                'component' => 'pages::resources.show',
            ],
        ], $library->pageRoutes());
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
