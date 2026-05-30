<?php

namespace Tests\Unit;

use App\Services\EvolveLibrary;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
