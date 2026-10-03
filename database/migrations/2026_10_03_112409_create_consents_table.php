<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
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

            $table->foreignId('treatment_id')
                ->nullable()
                ->constrained('treatments')
                ->nullOnDelete();

            $table->foreignId('medical_record_id')
                ->nullable()
                ->constrained('medical_records')
                ->nullOnDelete();

            $table->foreignId('appointment_id')
                ->nullable()
                ->constrained('appointments')
                ->nullOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('consent_type', 50);

            $table->string('title');

            $table->text('content');

            $table->string('version', 30)
                ->default('1.0');

            $table->string('status', 30)
                ->default('pending');

            $table->timestamp('requested_at')
                ->nullable();

            $table->timestamp('responded_at')
                ->nullable();

            $table->timestamp('expires_at')
                ->nullable();

            $table->string('response_method', 30)
                ->nullable();

            $table->string('ip_address', 45)
                ->nullable();

            $table->text('user_agent')
                ->nullable();

            $table->text('response_note')
                ->nullable();

            $table->timestamps();

            $table->index([
                'patient_profile_id',
                'status',
            ]);

            $table->index([
                'business_id',
                'branch_id',
            ]);

            $table->index([
                'consent_type',
                'version',
            ]);

            $table->index([
                'medical_record_id',
            ]);

            $table->index([
                'appointment_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};