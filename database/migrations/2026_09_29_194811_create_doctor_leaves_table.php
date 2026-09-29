<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_leaves', function (Blueprint $table) {
            $table->id();

            $table->foreignId('doctor_id')
                ->constrained('doctors')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();

            $table->date('start_date');
            $table->date('end_date');

            $table->string('leave_type')->default('leave');

            $table->text('reason')->nullable();

            $table->boolean('is_approved')->default(true);

            $table->timestamps();

            $table->index([
                'doctor_id',
                'branch_id',
                'start_date',
                'end_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_leaves');
    }
};