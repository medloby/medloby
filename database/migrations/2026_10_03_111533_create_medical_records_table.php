<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_profile_id')
                ->constrained('patient_profiles')
                ->cascadeOnDelete();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();

            $table->foreignId('appointment_id')
                ->nullable()
                ->constrained('appointments')
                ->nullOnDelete();

            $table->foreignId('doctor_id')
                ->nullable()
                ->constrained('doctors')
                ->nullOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('record_type', 50)
                ->default('clinical_note');

            $table->string('title');

            $table->text('complaint')->nullable();

            $table->text('examination')->nullable();

            $table->text('diagnosis')->nullable();

            $table->text('treatment')->nullable();

            $table->text('notes')->nullable();

            $table->string('status', 30)
                ->default('active');

            $table->timestamp('recorded_at')
                ->useCurrent();

            $table->timestamps();

            $table->index([
                'patient_profile_id',
                'recorded_at',
            ]);

            $table->index([
                'business_id',
                'branch_id',
            ]);

            $table->index([
                'doctor_id',
                'recorded_at',
            ]);

            $table->index([
                'appointment_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};