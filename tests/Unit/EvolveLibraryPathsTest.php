<?php

namespace Tests\Unit;

use App\Services\EvolveLibrary;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EvolveLibraryPathsTest extends TestCase
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

    public function test_components_are_written_to_paths_derived_from_path_metadata(): void
    {
        $library = new EvolveLibrary;

        $library->write([
            'components' => [
                [
                    'id' => 'old-card',
                    'name' => 'Card',
                    'path' => 'resources/views/components/old-card.blade.php',
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Old</div>',
                    'usage' => '<livewire:old-card />',
                ],
            ],
        ]);

        $library->write([
            'components' => [
                [
                    'id' => 'old-card',
                    'name' => 'Card',
                    'path' => 'resources/views/components/cards/feature.blade.php',
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Feature</div>',
                    'usage' => '<livewire:cards.feature />',
                ],
            ],
        ]);

        $component = $library->all()['components'][0];

        $this->assertFalse(File::exists(resource_path('views/components/old-card.blade.php')));
        $this->assertTrue(File::exists(resource_path('views/components/cards/feature.blade.php')));
        $this->assertSame('cards/feature', $component['id']);
        $this->assertSame('resources/views/components/cards/feature.blade.php', $component['path']);
        $this->assertSame('resources/views/components/cards/feature.blade.php', $component['source_path']);
        $this->assertSame('cards.feature', $component['component']);
    }

    public function test_layouts_are_written_to_paths_derived_from_path_metadata(): void
    {
        $library = new EvolveLibrary;

        $library->write([
            'layouts' => [
                [
                    'id' => 'old-layout',
                    'name' => 'Landing',
                    'path' => 'resources/views/layouts/marketing/landing.blade.php',
                    'blade' => '{{ $slot }}',
                    'style' => '.layout {}',
                    'usage' => '<x-layouts::marketing.landing></x-layouts::marketing.landing>',
                ],
            ],
        ]);

        $layout = $library->all()['layouts'][0];

        $this->assertTrue(File::exists(resource_path('views/layouts/marketing/landing.blade.php')));
        $this->assertSame('marketing/landing', $layout['id']);
        $this->assertSame('resources/views/layouts/marketing/landing.blade.php', $layout['path']);
        $this->assertSame('layouts::marketing.landing', $layout['component']);
    }

    public function test_styles_are_written_to_paths_derived_from_path_metadata(): void
    {
        $library = new EvolveLibrary;

        $library->write([
            'styles' => [
                [
                    'id' => 'old-style',
                    'name' => 'Theme',
                    'path' => 'resources/css/themes/site.css',
                    'style' => ':root { --brand: #111; }',
                ],
            ],
        ]);

        $style = $library->all()['styles'][0];

        $this->assertTrue(File::exists(resource_path('css/themes/site.css')));
        $this->assertSame('themes/site', $style['id']);
        $this->assertSame('resources/css/themes/site.css', $style['path']);
        $this->assertSame('resources/css/themes/site.css', $style['source_path']);
    }

    public function test_snippets_are_written_to_paths_derived_from_path_metadata(): void
    {
        $library = new EvolveLibrary;

        $library->write([
            'snippets' => [
                [
                    'id' => 'old-badge',
                    'name' => 'Badge',
                    'path' => 'resources/views/snippets/marketing/badge.blade.php',
                    'blade' => '<span>Badge</span>',
                    'usage' => '<x-snippets::marketing.badge />',
                ],
            ],
        ]);

        $snippet = $library->all()['snippets'][0];

        $this->assertTrue(File::exists(resource_path('views/snippets/marketing/badge.blade.php')));
        $this->assertSame('marketing/badge', $snippet['id']);
        $this->assertSame('resources/views/snippets/marketing/badge.blade.php', $snippet['path']);
        $this->assertSame('resources/views/snippets/marketing/badge.blade.php', $snippet['source_path']);
        $this->assertSame('snippets::marketing.badge', $snippet['component']);
        $this->assertSame('<span>Badge</span>', $snippet['blade']);
    }

    public function test_artifacts_cannot_overwrite_workbench_assets(): void
    {
        File::ensureDirectoryExists(resource_path('css'));
        File::put(resource_path('css/app.css'), '/* workbench css */');

        $library = new EvolveLibrary;

        $this->assertWorkbenchWriteIsBlocked(fn () => $library->write([
            'styles' => [
                [
                    'name' => 'App',
                    'path' => 'resources/css/app.css',
                    'style' => 'body { color: red; }',
                ],
            ],
        ]));

        $this->assertSame('/* workbench css */', File::get(resource_path('css/app.css')));
        $this->assertFalse(File::exists(resource_path('evolve/manifest.json')));
    }

    public function test_artifact_writes_cannot_follow_symlinks_outside_the_workspace(): void
    {
        $outsidePath = $this->originalBasePath.'/storage/framework/testing/evolve-outside-'.Str::random(8).'.css';
        File::put($outsidePath, 'outside');
        File::ensureDirectoryExists(resource_path('css'));
        symlink($outsidePath, resource_path('css/escape.css'));

        $library = new EvolveLibrary;

        try {
            $this->assertWorkbenchWriteIsBlocked(fn () => $library->write([
                'styles' => [
                    [
                        'name' => 'Escape',
                        'path' => 'resources/css/escape.css',
                        'style' => 'inside',
                    ],
                ],
            ]), 'current working folder');

            $this->assertSame('outside', File::get($outsidePath));
            $this->assertFalse(File::exists(resource_path('evolve/manifest.json')));
        } finally {
            File::delete(resource_path('css/escape.css'));
            File::delete($outsidePath);
        }
    }

    public function test_starter_kit_writes_succeed_after_snapshotting_originals(): void
    {
        File::ensureDirectoryExists(resource_path('views/layouts/app'));
        File::ensureDirectoryExists(resource_path('views/pages/auth'));
        File::ensureDirectoryExists(resource_path('views/components'));
        File::put(resource_path('views/layouts/app/sidebar.blade.php'), 'starter sidebar');
        File::put(resource_path('views/pages/auth/login.blade.php'), 'starter login');
        File::put(resource_path('views/components/app-logo.blade.php'), 'starter logo');

        $library = new EvolveLibrary;

        $library->write([
            'layouts' => [
                [
                    'name' => 'Sidebar',
                    'path' => 'resources/views/layouts/app/sidebar.blade.php',
                    'blade' => '<div>Edited sidebar</div>',
                ],
            ],
            'pages' => [
                [
                    'name' => 'Login',
                    'path' => 'resources/views/pages/auth/login.blade.php',
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Edited login</div>',
                ],
            ],
            'components' => [
                [
                    'name' => 'App logo',
                    'path' => 'resources/views/components/app-logo.blade.php',
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Edited logo</div>',
                ],
            ],
        ]);

        $this->assertStringContainsString('Edited sidebar', File::get(resource_path('views/layouts/app/sidebar.blade.php')));
        $this->assertStringContainsString('Edited login', File::get(resource_path('views/pages/auth/login.blade.php')));
        $this->assertStringContainsString('Edited logo', File::get(resource_path('views/components/app-logo.blade.php')));

        $this->assertSame('starter sidebar', File::get(resource_path('evolve/originals/layouts/app/sidebar.blade.php')));
        $this->assertSame('starter login', File::get(resource_path('evolve/originals/pages/auth/login.blade.php')));
        $this->assertSame('starter logo', File::get(resource_path('evolve/originals/components/app-logo.blade.php')));
    }

    public function test_plain_blade_artifacts_do_not_gain_empty_php_blocks(): void
    {
        File::ensureDirectoryExists(resource_path('views/components'));
        File::put(resource_path('views/components/app-logo.blade.php'), '<div>Logo</div>');

        $library = new EvolveLibrary;
        $artifact = collect($library->all()['components'])->firstWhere('id', 'app-logo');
        $artifact['blade'] = '<div>Edited logo</div>';

        $library->write([
            'components' => [$artifact],
        ]);

        $this->assertSame("<div>Edited logo</div>\n", File::get(resource_path('views/components/app-logo.blade.php')));
    }

    public function test_app_css_is_still_workbench_internal_and_locked(): void
    {
        File::ensureDirectoryExists(resource_path('css'));
        File::put(resource_path('css/app.css'), '/* workbench css */');

        $library = new EvolveLibrary;

        $this->assertWorkbenchWriteIsBlocked(fn () => $library->write([
            'styles' => [
                [
                    'name' => 'App',
                    'path' => 'resources/css/app.css',
                    'style' => 'body { color: red; }',
                ],
            ],
        ]));

        $this->assertSame('/* workbench css */', File::get(resource_path('css/app.css')));
        $this->assertFalse(File::exists(resource_path('evolve/manifest.json')));
    }

    public function test_starter_kit_artifacts_can_be_restored_to_originals(): void
    {
        File::ensureDirectoryExists(resource_path('views/components'));
        File::put(resource_path('views/components/auth-header.blade.php'), 'original header');

        $library = new EvolveLibrary;

        $library->write([
            'components' => [
                [
                    'name' => 'Auth header',
                    'path' => 'resources/views/components/auth-header.blade.php',
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Edited header</div>',
                ],
            ],
        ]);

        $this->assertStringContainsString('Edited header', File::get(resource_path('views/components/auth-header.blade.php')));
        $this->assertTrue($library->hasStarterKitOriginal('component', 'auth-header'));

        $restored = $library->restoreArtifactOriginal('component', 'auth-header');

        $this->assertSame('original header', File::get(resource_path('views/components/auth-header.blade.php')));
        $this->assertNotEmpty($restored);
    }

    public function test_restore_rejects_artifacts_with_no_snapshot(): void
    {
        $library = new EvolveLibrary;

        try {
            $library->restoreArtifactOriginal('component', 'auth-header');
            $this->fail('Restore should fail when no snapshot exists.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('No starter-kit original', $exception->getMessage());
        }
    }

    public function test_restore_rejects_non_starter_kit_artifacts(): void
    {
        $library = new EvolveLibrary;

        try {
            $library->restoreArtifactOriginal('component', 'hero');
            $this->fail('Restore should fail for non-starter-kit artifacts.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('not a starter-kit artifact', $exception->getMessage());
        }
    }

    public function test_single_artifact_updates_preserve_other_artifacts(): void
    {
        $library = new EvolveLibrary;

        $library->write([
            'components' => [
                [
                    'id' => 'hero',
                    'name' => 'Hero',
                    'path' => 'resources/views/components/hero.blade.php',
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Hero</div>',
                    'usage' => '<livewire:hero />',
                ],
                [
                    'id' => 'card',
                    'name' => 'Card',
                    'path' => 'resources/views/components/card.blade.php',
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Card</div>',
                    'usage' => '<livewire:card />',
                ],
            ],
        ]);

        $library->writeArtifact('component', 'hero', [
            'id' => 'hero',
            'name' => 'Hero',
            'path' => 'resources/views/components/sections/hero.blade.php',
            'php' => $this->componentPhp(),
            'blade' => '<div>Updated hero</div>',
            'usage' => '<livewire:sections.hero />',
        ]);

        $components = $library->all()['components'];

        $this->assertFalse(File::exists(resource_path('views/components/hero.blade.php')));
        $this->assertTrue(File::exists(resource_path('views/components/sections/hero.blade.php')));
        $this->assertTrue(File::exists(resource_path('views/components/card.blade.php')));
        $this->assertSame(['sections/hero', 'card'], array_column($components, 'id'));
        $this->assertSame('<div>Card</div>', $components[1]['blade']);
    }

    public function test_style_order_can_be_updated_without_changing_style_content(): void
    {
        $library = new EvolveLibrary;

        $library->write([
            'styles' => [
                ['id' => 'base', 'name' => 'Base', 'path' => 'resources/css/base.css', 'style' => 'body { color: black; }'],
                ['id' => 'theme', 'name' => 'Theme', 'path' => 'resources/css/theme.css', 'style' => ':root { --brand: blue; }'],
            ],
        ]);

        $library->orderStyles(['theme', 'base']);

        $styles = $library->all()['styles'];

        $this->assertSame(['theme', 'base'], array_column($styles, 'id'));
        $this->assertSame('body { color: black; }', trim(File::get(resource_path('css/base.css'))));
        $this->assertSame(':root { --brand: blue; }', trim(File::get(resource_path('css/theme.css'))));
    }

    public function test_artifacts_can_be_deleted(): void
    {
        $library = new EvolveLibrary;

        $library->write([
            'forms' => [
                [
                    'id' => 'contact',
                    'name' => 'Contact',
                    'slug' => '/contact',
                    'php' => $this->componentPhp(),
                    'blade' => '<form>Contact</form>',
                ],
            ],
        ]);

        $this->assertTrue(File::exists(resource_path('views/forms/contact.blade.php')));

        $library->deleteArtifact('form', 'contact');

        $this->assertSame([], $library->all()['forms']);
        $this->assertFalse(File::exists(resource_path('views/forms/contact.blade.php')));
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

    private function assertWorkbenchWriteIsBlocked(callable $callback, string $message = 'protected'): void
    {
        try {
            $callback();
            $this->fail('Workbench write was not blocked.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }
}
