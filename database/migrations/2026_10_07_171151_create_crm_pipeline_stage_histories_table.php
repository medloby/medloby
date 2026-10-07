<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_pipeline_stage_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('crm_lead_id')
                ->constrained('crm_leads')
                ->cascadeOnDelete();

            $table->foreignId('from_stage_id')
                ->nullable()
                ->constrained('crm_pipeline_stages')
                ->nullOnDelete();

            $table->foreignId('to_stage_id')
                ->constrained('crm_pipeline_stages')
                ->cascadeOnDelete();

            $table->foreignId('changed_by_business_user_id')
                ->nullable()
                ->constrained('business_user')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamp('changed_at');

            $table->timestamps();

            $table->index([
                'crm_lead_id',
                'changed_at',
            ]);

            $table->index([
                'to_stage_id',
                'changed_at',
            ]);

            $table->index([
                'changed_by_business_user_id',
                'changed_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_pipeline_stage_histories');
    }
};