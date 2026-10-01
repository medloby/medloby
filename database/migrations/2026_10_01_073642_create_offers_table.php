<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->restrictOnDelete();

            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();

            $table->foreignId('patient_profile_id')
                ->constrained('patient_profiles')
                ->restrictOnDelete();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('title', 255);

            $table->text('description')->nullable();

            $table->decimal('amount', 12, 2);

            $table->string('currency', 3)->default('TRY');

            $table->dateTime('valid_until')->nullable();

            $table->string('status', 30)->default('pending');

            $table->dateTime('responded_at')->nullable();

            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index([
                'business_id',
                'status',
            ]);

            $table->index([
                'patient_profile_id',
                'status',
            ]);

            $table->index([
                'conversation_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};