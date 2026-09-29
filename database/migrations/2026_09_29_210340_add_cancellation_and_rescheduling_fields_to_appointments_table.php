<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->foreignId('cancelled_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('rescheduled_from_appointment_id')
                ->nullable()
                ->constrained('appointments')
                ->nullOnDelete();

            $table->timestamp('rescheduled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by_user_id']);
            $table->dropForeign(['rescheduled_from_appointment_id']);

            $table->dropColumn([
                'cancellation_reason',
                'cancelled_at',
                'cancelled_by_user_id',
                'rescheduled_from_appointment_id',
                'rescheduled_at',
            ]);
        });
    }
};