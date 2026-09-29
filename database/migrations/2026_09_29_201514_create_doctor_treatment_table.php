<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_treatment', function (Blueprint $table) {
            $table->id();

            $table->foreignId('doctor_id')
                ->constrained('doctors')
                ->cascadeOnDelete();

            $table->foreignId('treatment_id')
                ->constrained('treatments')
                ->cascadeOnDelete();

            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('duration_minutes')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique([
                'doctor_id',
                'treatment_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_treatment');
    }
};