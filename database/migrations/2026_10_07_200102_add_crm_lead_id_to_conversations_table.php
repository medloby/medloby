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
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('crm_lead_id')
                ->nullable()
                ->after('patient_profile_id')
                ->constrained('crm_leads')
                ->nullOnDelete();

            $table->index([
                'business_id',
                'branch_id',
                'crm_lead_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign([
                'crm_lead_id',
            ]);

            $table->dropIndex([
                'business_id',
                'branch_id',
                'crm_lead_id',
            ]);

            $table->dropColumn('crm_lead_id');
        });
    }
};