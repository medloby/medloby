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
        Schema::table('branch_ledger_entries', function (Blueprint $table) {
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->restrictOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained('offers')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branch_ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appointment_id');
            $table->dropConstrainedForeignId('offer_id');
        });
    }
};
