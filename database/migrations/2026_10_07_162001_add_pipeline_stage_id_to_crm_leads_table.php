<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->foreignId('pipeline_stage_id')
                ->nullable()
                ->after('status')
                ->constrained('crm_pipeline_stages')
                ->nullOnDelete();

            $table->index([
                'business_id',
                'pipeline_stage_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropForeign([
                'pipeline_stage_id',
            ]);

            $table->dropIndex([
                'business_id',
                'pipeline_stage_id',
            ]);

            $table->dropColumn('pipeline_stage_id');
        });
    }
};