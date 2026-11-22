<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_follow_ups', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable();
            $table->index(['status', 'reminded_at', 'scheduled_at'], 'crm_follow_ups_reminders_index');
        });
    }

    public function down(): void
    {
        Schema::table('crm_follow_ups', function (Blueprint $table) {
            $table->dropIndex('crm_follow_ups_reminders_index');
            $table->dropColumn('reminded_at');
        });
    }
};
