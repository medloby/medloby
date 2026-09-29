<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiting_lists', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('patient_profile_id')
                ->nullable()
                ->constrained('patient_profiles')
                ->nullOnDelete();

            $table->foreignId('doctor_id')
                ->nullable()
                ->constrained('doctors')
                ->nullOnDelete();

            $table->foreignId('treatment_id')
                ->nullable()
                ->constrained('treatments')
                ->nullOnDelete();

            $table->date('preferred_date')->nullable();
            $table->time('preferred_start_time')->nullable();
            $table->time('preferred_end_time')->nullable();

            $table->string('patient_name')->nullable();
            $table->string('patient_phone')->nullable();
            $table->string('patient_email')->nullable();

            $table->string('source')->default('medloby');

            $table->string('status')->default('waiting');

            $table->text('notes')->nullable();

            $table->timestamp('notified_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index([
                'branch_id',
                'preferred_date',
                'status',
            ]);

            $table->index([
                'doctor_id',
                'preferred_date',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiting_lists');
    }
};