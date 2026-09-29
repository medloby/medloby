<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_branch', function (Blueprint $table) {
            $table->id();

            $table->foreignId('doctor_id')
                ->constrained('doctors')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->string('status')->default('active');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['doctor_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_branch');
    }
};