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
        Schema::create('crm_activities', function (Blueprint $table) {
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

            $table->foreignId('business_user_id')
                ->constrained('business_user')
                ->cascadeOnDelete();

            $table->string('type', 50);

            $table->string('title');

            $table->text('description')
                ->nullable();

            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->index([
                'business_id',
                'branch_id',
            ]);

            $table->index([
                'crm_lead_id',
                'occurred_at',
            ]);

            $table->index([
                'business_user_id',
                'occurred_at',
            ]);

            $table->index([
                'type',
                'occurred_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};