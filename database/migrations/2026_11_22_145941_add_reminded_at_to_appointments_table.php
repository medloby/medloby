<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable();
            $table->index(['status', 'reminded_at', 'starts_at'], 'appointments_reminders_index');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_reminders_index');
            $table->dropColumn('reminded_at');
        });
    }
};
