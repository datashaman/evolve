<?php

namespace Tests\Unit;

use App\Services\EvolveLibrary;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class EvolveLibraryFormsTest extends TestCase
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

    public function test_forms_are_written_as_livewire_single_file_components(): void
    {
        $library = new EvolveLibrary;

        $library->write([
            'forms' => [
                [
                    'id' => 'new-12345678',
                    'name' => 'Contact form',
                    'slug' => '/contact',
                    'php' => <<<'PHP'
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Validate('required|email')]
    public string $email = '';

    public function save(): void
    {
        $this->validate();
    }
};
PHP,
                    'blade' => <<<'BLADE'
<form wire:submit="save">
    <input type="email" wire:model="email">
</form>
BLADE,
                    'style' => '& { display: grid; }',
                    'usage' => '<livewire:forms::contact />',
                ],
            ],
        ]);

        $path = resource_path('views/forms/contact.blade.php');

        $this->assertTrue(File::exists($path));
        $this->assertFalse(File::exists(resource_path('views/forms/new-12345678.blade.php')));
        $this->assertStringContainsString('new class extends Component', File::get($path));
        $this->assertStringContainsString('<form wire:submit="save">', File::get($path));
        $this->assertStringContainsString('<style>', File::get($path));
        $this->assertStringContainsString('& {', File::get($path));

        $form = $library->all()['forms'][0];

        $this->assertSame('form', $form['kind']);
        $this->assertSame('contact', $form['id']);
        $this->assertSame('/contact', $form['slug']);
        $this->assertSame('resources/views/forms/contact.blade.php', $form['path']);
        $this->assertSame('forms::contact', $form['component']);
        $this->assertSame('<livewire:forms::contact />', $form['usage']);
    }
}
