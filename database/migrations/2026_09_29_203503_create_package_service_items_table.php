<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_service_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('treatment_package_id')
                ->constrained('treatment_packages')
                ->cascadeOnDelete();

            $table->foreignId('package_service_id')
                ->constrained('package_services')
                ->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            $table->text('notes')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique([
                'treatment_package_id',
                'package_service_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_service_items');
    }
};