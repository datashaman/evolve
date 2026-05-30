<?php

namespace Tests\Unit;

use App\Services\EvolveLibrary;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class EvolveContentTreeTest extends TestCase
{
    private string $originalBasePath;

    private string $testBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalBasePath = app()->basePath();
        $this->testBasePath = storage_path('framework/testing/evolve-tree-'.Str::random(8));

        File::ensureDirectoryExists($this->testBasePath.'/resources/evolve');
        app()->setBasePath($this->testBasePath);

        $this->seedLibrary();
    }

    protected function tearDown(): void
    {
        app()->setBasePath($this->originalBasePath);
        File::deleteDirectory($this->testBasePath);

        parent::tearDown();
    }

    public function test_navigation_returns_nested_page_tree(): void
    {
        $navigation = evolve_navigation();

        $this->assertSame(['about', 'docs'], array_column($navigation, 'id'));
        $this->assertSame(['docs/intro', 'docs/api'], array_column($navigation[1]['children'], 'id'));
        $this->assertSame([], $navigation[1]['children'][0]['children']);
    }

    public function test_child_and_sibling_helpers_return_related_pages(): void
    {
        $this->assertSame(['docs/intro', 'docs/api'], array_column(evolve_child_pages('docs'), 'id'));
        $this->assertSame(['docs/intro', 'docs/api'], array_column(evolve_sibling_pages('docs/intro'), 'id'));
    }

    public function test_metadata_helper_returns_current_or_named_page_metadata(): void
    {
        $this->app['request']->server->set('REQUEST_URI', '/docs/getting-started');
        $this->app['request']->server->set('PATH_INFO', '/docs/getting-started');

        $this->assertSame('Docs intro', evolve_metadata('title'));
        $this->assertSame('/docs/{section}', evolve_metadata('route'));
        $this->assertSame('Docs', evolve_metadata('title', null, 'docs'));
        $this->assertSame('fallback', evolve_metadata('missing', 'fallback', 'docs'));
    }

    public function test_snippet_helper_renders_snippet_blade_with_data(): void
    {
        $this->assertSame('<span>Beta</span>', (string) evolve_snippet('labels/badge', ['label' => 'Beta']));
    }

    protected function seedLibrary(): void
    {
        (new EvolveLibrary)->write([
            'pages' => [
                [
                    'name' => 'Docs',
                    'path' => 'resources/views/pages/docs.blade.php',
                    'route' => '/docs',
                    'order' => 2,
                    'metadata' => ['title' => 'Docs'],
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Docs</div>',
                ],
                [
                    'name' => 'Intro',
                    'path' => 'resources/views/pages/docs/intro.blade.php',
                    'route' => '/docs/{section}',
                    'parent_id' => 'docs',
                    'order' => 1,
                    'metadata' => ['title' => 'Docs intro'],
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Intro</div>',
                ],
                [
                    'name' => 'API',
                    'path' => 'resources/views/pages/docs/api.blade.php',
                    'route' => '/docs/api',
                    'parent_id' => 'docs',
                    'order' => 2,
                    'metadata' => ['title' => 'API'],
                    'php' => $this->componentPhp(),
                    'blade' => '<div>API</div>',
                ],
                [
                    'name' => 'About',
                    'path' => 'resources/views/pages/about.blade.php',
                    'route' => '/about',
                    'order' => 1,
                    'metadata' => ['title' => 'About'],
                    'php' => $this->componentPhp(),
                    'blade' => '<div>About</div>',
                ],
            ],
            'snippets' => [
                [
                    'name' => 'Badge',
                    'path' => 'resources/views/snippets/labels/badge.blade.php',
                    'blade' => '<span>{{ $label }}</span>',
                    'usage' => '<x-snippets::labels.badge />',
                ],
            ],
        ]);
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
