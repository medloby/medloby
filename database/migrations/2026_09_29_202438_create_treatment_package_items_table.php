<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_package_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('treatment_package_id')
                ->constrained('treatment_packages')
                ->cascadeOnDelete();

            $table->foreignId('treatment_id')
                ->constrained('treatments')
                ->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            $table->text('notes')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique([
                'treatment_package_id',
                'treatment_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_package_items');
    }
};