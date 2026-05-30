<?php

namespace Tests\Unit;

use App\Services\EvolveContentModelScaffolder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EvolveContentModelScaffolderTest extends TestCase
{
    private string $originalBasePath;

    private string $testBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalBasePath = app()->basePath();
        $this->testBasePath = storage_path('framework/testing/evolve-scaffolder-'.Str::random(8));

        File::ensureDirectoryExists($this->testBasePath.'/app/Models');
        File::ensureDirectoryExists($this->testBasePath.'/database');
        app()->setBasePath($this->testBasePath);
    }

    protected function tearDown(): void
    {
        app()->setBasePath($this->originalBasePath);
        File::deleteDirectory($this->testBasePath);

        parent::tearDown();
    }

    public function test_content_model_scaffolding_cannot_write_migrations_outside_the_workspace(): void
    {
        $outsidePath = $this->originalBasePath.'/storage/framework/testing/evolve-outside-migrations-'.Str::random(8);
        File::ensureDirectoryExists($outsidePath);
        symlink($outsidePath, database_path('migrations'));

        try {
            (new EvolveContentModelScaffolder)->create('Field Note');
            $this->fail('Content model scaffolding was not blocked.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('current working folder', $exception->getMessage());
        } finally {
            File::delete(database_path('migrations'));
            File::deleteDirectory($outsidePath);
        }

        $this->assertFalse(File::exists(app_path('Models/FieldNote.php')));
    }
}
