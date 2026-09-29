<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('treatment_categories')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();

            $table->text('description')->nullable();

            $table->string('image')->nullable();
            $table->string('icon')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(false);

            $table->timestamps();

            $table->index(['parent_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_categories');
    }
};