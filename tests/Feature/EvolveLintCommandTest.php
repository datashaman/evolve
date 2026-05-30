<?php

namespace Tests\Feature;

use App\Services\EvolveLibrary;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class EvolveLintCommandTest extends TestCase
{
    private string $originalBasePath;

    private string $testBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalBasePath = app()->basePath();
        $this->testBasePath = storage_path('framework/testing/evolve-lint-'.Str::random(8));

        File::ensureDirectoryExists($this->testBasePath.'/resources/evolve');
        app()->setBasePath($this->testBasePath);
    }

    protected function tearDown(): void
    {
        app()->setBasePath($this->originalBasePath);
        File::deleteDirectory($this->testBasePath);

        parent::tearDown();
    }

    public function test_clean_library_reports_no_findings(): void
    {
        (new EvolveLibrary)->write([
            'pages' => [
                [
                    'id' => 'about',
                    'name' => 'About',
                    'path' => 'resources/views/pages/about.blade.php',
                    'route' => '/about',
                    'route_name' => 'about-page',
                    'middleware' => ['auth'],
                    'php' => $this->componentPhp(),
                    'blade' => '<div>About</div>',
                ],
            ],
        ]);

        $payload = $this->runLintJson();

        $this->assertSame([], $payload['findings']);
    }

    public function test_duplicate_route_names_are_reported(): void
    {
        (new EvolveLibrary)->write([
            'pages' => [
                [
                    'id' => 'one',
                    'name' => 'One',
                    'path' => 'resources/views/pages/one.blade.php',
                    'route' => '/one',
                    'route_name' => 'shared',
                    'php' => $this->componentPhp(),
                    'blade' => '<div>One</div>',
                ],
                [
                    'id' => 'two',
                    'name' => 'Two',
                    'path' => 'resources/views/pages/two.blade.php',
                    'route' => '/two',
                    'route_name' => 'shared',
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Two</div>',
                ],
            ],
        ]);

        $payload = $this->runLintJson();
        $codes = array_column($payload['findings'], 'code');

        $this->assertContains('duplicate-route-name', $codes);
    }

    public function test_unknown_middleware_aliases_are_reported(): void
    {
        (new EvolveLibrary)->write([
            'pages' => [
                [
                    'id' => 'gated',
                    'name' => 'Gated',
                    'path' => 'resources/views/pages/gated.blade.php',
                    'route' => '/gated',
                    'middleware' => ['auht', 'throttle:60,1'],
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Gated</div>',
                ],
            ],
        ]);

        $payload = $this->runLintJson();
        $subjects = array_column(
            array_filter($payload['findings'], fn (array $f): bool => $f['code'] === 'unknown-middleware'),
            'subject',
        );

        $this->assertContains('auht', $subjects);
        $this->assertNotContains('throttle:60,1', $subjects, 'throttle is a known middleware and should not be flagged');
    }

    public function test_collisions_with_static_route_names_are_reported(): void
    {
        Route::get('/static-elsewhere', fn () => null)->name('shared-static-name');

        (new EvolveLibrary)->write([
            'pages' => [
                [
                    'id' => 'collider',
                    'name' => 'Collider',
                    'path' => 'resources/views/pages/collider.blade.php',
                    'route' => '/collider',
                    'route_name' => 'shared-static-name',
                    'php' => $this->componentPhp(),
                    'blade' => '<div>Collider</div>',
                ],
            ],
        ]);

        $payload = $this->runLintJson();
        $codes = array_column($payload['findings'], 'code');

        $this->assertContains('shadows-static-route', $codes);
    }

    public function test_exit_code_is_nonzero_when_findings_exist(): void
    {
        (new EvolveLibrary)->write([
            'pages' => [
                [
                    'id' => 'a',
                    'name' => 'A',
                    'path' => 'resources/views/pages/a.blade.php',
                    'route' => '/a',
                    'route_name' => 'shared',
                    'php' => $this->componentPhp(),
                    'blade' => '<div>A</div>',
                ],
                [
                    'id' => 'b',
                    'name' => 'B',
                    'path' => 'resources/views/pages/b.blade.php',
                    'route' => '/b',
                    'route_name' => 'shared',
                    'php' => $this->componentPhp(),
                    'blade' => '<div>B</div>',
                ],
            ],
        ]);

        $exit = Artisan::call('evolve:lint');

        $this->assertSame(1, $exit);
    }

    private function runLintJson(): array
    {
        Artisan::call('evolve:lint', ['--json' => true]);
        $output = Artisan::output();
        $payload = json_decode(trim($output), true);

        $this->assertIsArray($payload, "evolve:lint --json did not return parseable JSON. Output:\n".$output);
        $this->assertArrayHasKey('findings', $payload);

        return $payload;
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
