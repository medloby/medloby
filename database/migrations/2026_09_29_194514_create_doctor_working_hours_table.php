<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_working_hours', function (Blueprint $table) {
            $table->id();

            $table->foreignId('doctor_id')
                ->constrained('doctors')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('day_of_week');

            $table->time('start_time');
            $table->time('end_time');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index([
                'doctor_id',
                'branch_id',
                'day_of_week',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_working_hours');
    }
};