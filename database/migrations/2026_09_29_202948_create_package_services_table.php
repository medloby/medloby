<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_services', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->nullable()
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');

            $table->string('service_type');

            $table->text('description')->nullable();

            $table->string('unit')->nullable();

            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique([
                'business_id',
                'slug',
            ]);

            $table->index([
                'business_id',
                'service_type',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_services');
    }
};