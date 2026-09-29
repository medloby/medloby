<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('treatment_category_id')
                ->constrained('treatment_categories')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('slug')->unique();

            $table->text('description')->nullable();

            $table->unsignedInteger('duration_minutes')->nullable();

            $table->text('preparation')->nullable();
            $table->text('aftercare')->nullable();

            $table->text('included_services')->nullable();
            $table->text('excluded_services')->nullable();

            $table->string('image')->nullable();

            $table->boolean('is_online_bookable')->default(false);
            $table->boolean('is_offer_enabled')->default(true);

            $table->boolean('is_active')->default(false);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index([
                'treatment_category_id',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatments');
    }
};