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
        Schema::create('crm_tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('crm_lead_id')
                ->nullable()
                ->constrained('crm_leads')
                ->nullOnDelete();

            $table->foreignId('assigned_business_user_id')
                ->nullable()
                ->constrained('business_user')
                ->nullOnDelete();

            $table->foreignId('created_by_business_user_id')
                ->nullable()
                ->constrained('business_user')
                ->nullOnDelete();

            $table->string('title', 255);

            $table->text('description')
                ->nullable();

            $table->string('type', 50)
                ->default('other');

            $table->string('status', 50)
                ->default('pending');

            $table->string('priority', 20)
                ->default('normal');

            $table->dateTime('due_at')
                ->nullable();

            $table->dateTime('started_at')
                ->nullable();

            $table->dateTime('completed_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'business_id',
                'branch_id',
            ]);

            $table->index([
                'business_id',
                'status',
            ]);

            $table->index([
                'business_id',
                'assigned_business_user_id',
            ]);

            $table->index([
                'business_id',
                'crm_lead_id',
            ]);

            $table->index('due_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
    }
};