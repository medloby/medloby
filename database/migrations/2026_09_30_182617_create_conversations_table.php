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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('patient_profile_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('subject')->nullable();

            $table->string('status')->default('open');

            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->index([
                'business_id',
                'patient_profile_id',
                'status',
            ]);

            $table->index('last_message_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};