<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('static_contents')) {
            return;
        }

        Schema::create('static_contents', function (Blueprint $table): void {
            $table->id();
            $table->string('icon', 12);
            $table->string('title');
            $table->text('summary');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('static_contents');
    }
};