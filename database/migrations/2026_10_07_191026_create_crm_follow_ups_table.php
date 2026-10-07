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
        Schema::create('crm_follow_ups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('crm_lead_id')
                ->constrained('crm_leads')
                ->cascadeOnDelete();

            $table->foreignId('assigned_business_user_id')
                ->nullable()
                ->constrained('business_user')
                ->nullOnDelete();

            $table->string('type', 50);

            $table->string('title');

            $table->text('notes')
                ->nullable();

            $table->timestamp('scheduled_at');

            $table->timestamp('completed_at')
                ->nullable();

            $table->timestamp('cancelled_at')
                ->nullable();

            $table->string('status', 50)
                ->default('pending');

            $table->timestamps();

            $table->index([
                'business_id',
                'branch_id',
            ]);

            $table->index([
                'crm_lead_id',
                'scheduled_at',
            ]);

            $table->index([
                'assigned_business_user_id',
                'scheduled_at',
            ]);

            $table->index([
                'status',
                'scheduled_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_follow_ups');
    }
};