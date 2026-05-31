<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('evolve_feedback')) {
            return;
        }

        Schema::create('evolve_feedback', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('type')->default('other');
            $table->text('message');
            $table->string('source')->default('mcp');
            $table->string('author')->nullable();
            $table->text('url')->nullable();
            $table->string('artifact_kind')->nullable();
            $table->string('artifact_id')->nullable();
            $table->json('context')->nullable();
            $table->string('status')->default('new')->index();
            $table->string('priority')->nullable()->index();
            $table->json('labels')->nullable();
            $table->string('assignee')->nullable()->index();
            $table->text('triage_notes')->nullable();
            $table->timestamp('triaged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evolve_feedback');
    }
};
