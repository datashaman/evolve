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

    public function test_pages_parse_middleware_from_newline_separated_string_preserving_parameters(): void
    {
        $library = new EvolveLibrary;

        $library->write([
            'pages' => [
                [
                    'id' => 'throttled',
                    'name' => 'Throttled',
                    'path' => 'resources/views/pages/throttled.blade.php',
                    'route' => '/throttled',
                    'middleware' => "auth\nthrottle:60,1\nrole:admin,editor",
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Throttled</div>',
                ],
            ],
        ]);

        $this->assertSame(
            ['auth', 'throttle:60,1', 'role:admin,editor'],
            $library->all()['pages'][0]['middleware'],
        );
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

    public function test_starter_kit_auth_and_settings_pages_are_discovered_as_app_shell_inventory(): void
    {
        File::ensureDirectoryExists(resource_path('views/pages/auth'));
        File::ensureDirectoryExists(resource_path('views/pages/settings'));
        File::put(resource_path('views/pages/auth/login.blade.php'), $this->componentSource('<div>Login</div>'));
        File::put(resource_path('views/pages/settings/layout.blade.php'), '<div>{{ $slot }}</div>');
        File::put(resource_path('views/pages/settings/⚡profile.blade.php'), $this->componentSource('<div>Profile</div>'));

        $library = new EvolveLibrary;
        $data = $library->all();
        $pages = collect($data['pages']);
        $websitePages = collect($data['surfaces']['website']['pages']);
        $appShellPages = collect($data['surfaces']['app_shell']['pages']);
        $developerPages = collect($data['surfaces']['developer']['pages']);

        $login = $pages->firstWhere('id', 'auth/login');
        $layout = $pages->firstWhere('id', 'settings/layout');
        $profile = $pages->firstWhere('id', 'settings/profile');

        $this->assertNotNull($login);
        $this->assertNotNull($layout);
        $this->assertNotNull($profile);
        $this->assertSame('app_shell', $login['surface']);
        $this->assertSame('advanced', $login['visibility']);
        $this->assertSame('/login', $login['route']);
        $this->assertSame('login', $login['route_name']);
        $this->assertSame('developer', $layout['surface']);
        $this->assertSame('advanced', $layout['visibility']);
        $this->assertSame('', $layout['route']);
        $this->assertSame('', $layout['route_name']);
        $this->assertSame('/settings/profile', $profile['route']);
        $this->assertSame('profile.edit', $profile['route_name']);
        $this->assertSame('resources/views/pages/settings/⚡profile.blade.php', $profile['source_path']);
        $this->assertSame('<div>Profile</div>', $profile['blade']);
        $this->assertNull($websitePages->firstWhere('id', 'auth/login'));
        $this->assertNull($websitePages->firstWhere('id', 'settings/profile'));
        $this->assertNotNull($appShellPages->firstWhere('id', 'auth/login'));
        $this->assertNotNull($appShellPages->firstWhere('id', 'settings/profile'));
        $this->assertNull($appShellPages->firstWhere('id', 'settings/layout'));
        $this->assertNotNull($developerPages->firstWhere('id', 'settings/layout'));
    }

    public function test_starter_kit_auth_and_settings_pages_do_not_register_generated_routes(): void
    {
        File::ensureDirectoryExists(resource_path('views/pages/auth'));
        File::ensureDirectoryExists(resource_path('views/pages/settings'));
        File::put(resource_path('views/pages/auth/login.blade.php'), $this->componentSource('<div>Login</div>'));
        File::put(resource_path('views/pages/settings/⚡profile.blade.php'), $this->componentSource('<div>Profile</div>'));

        $library = new EvolveLibrary;
        $library->write([
            'pages' => $library->all()['pages'],
        ]);

        $routes = collect($library->artifactRoutes());

        $this->assertNull($routes->firstWhere('component', 'pages::auth.login'));
        $this->assertNull($routes->firstWhere('component', 'pages::settings.profile'));
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

    private function componentSource(string $blade): string
    {
        return "<?php\n\n".$this->componentPhp()."\n?>\n\n{$blade}\n";
    }
}
