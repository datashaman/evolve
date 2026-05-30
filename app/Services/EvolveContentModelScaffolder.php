<?php

namespace App\Services;

use App\Services\Concerns\GuardsWorkspacePaths;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EvolveContentModelScaffolder
{
    use GuardsWorkspacePaths;

    public function create(string $name): void
    {
        $classBase = Str::studly(Str::singular($name));
        $modelPath = app_path("Models/{$classBase}.php");
        $table = Str::plural(Str::snake($classBase));

        $this->assertPathInsideWorkspace(dirname($modelPath));
        $this->assertPathInsideWorkspace(database_path('migrations'));

        abort_if($classBase === 'User' || File::exists($modelPath), 422, 'Content model already exists.');

        $this->writeFile($modelPath, $this->modelSource($classBase));
        $this->writeMigration($table);
        $this->ensureContentTable($table);
    }

    protected function ensureContentTable(string $table): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function ($table): void {
            $table->id();
            $table->string('icon', 12);
            $table->string('title');
            $table->text('summary');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    protected function writeMigration(string $table): void
    {
        $timestamp = now()->format('Y_m_d_His');
        $path = database_path("migrations/{$timestamp}_create_{$table}_table.php");

        $this->writeFile($path, <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('{$table}')) {
            return;
        }

        Schema::create('{$table}', function (Blueprint \$table): void {
            \$table->id();
            \$table->string('icon', 12);
            \$table->string('title');
            \$table->text('summary');
            \$table->unsignedInteger('position')->default(0);
            \$table->boolean('is_published')->default(true);
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};
PHP);
    }

    protected function writeFile(string $path, string $content): void
    {
        $this->assertPathInsideWorkspace($path);
        File::ensureDirectoryExists(dirname($path));
        $this->assertPathInsideWorkspace(dirname($path));

        if (File::exists($path) || is_link($path)) {
            $this->assertPathInsideWorkspace($path);
        }

        File::put($path, $content);
    }

    protected function modelSource(string $name): string
    {
        return <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class {$name} extends Model
{
    protected \$fillable = [
        'icon',
        'title',
        'summary',
        'position',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    #[Scope]
    protected function ordered(Builder \$query): void
    {
        \$query->orderBy('position')->orderBy('id');
    }

    #[Scope]
    protected function published(Builder \$query): void
    {
        \$query->where('is_published', true);
    }
}
PHP;
    }
}
