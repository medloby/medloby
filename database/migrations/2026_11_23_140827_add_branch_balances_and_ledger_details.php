<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('request_key', 32);
            $table->string('direction', 12);
            $table->string('counterparty', 120)->nullable();
            $table->string('description');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->date('due_on')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'request_key']);
            $table->index(['branch_id', 'patient_profile_id', 'id']);
        });

        Schema::table('branch_ledger_entries', function (Blueprint $table) {
            $table->foreignId('branch_balance_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('expense_category', 80)->nullable();
            $table->string('payment_channel', 12)->nullable();
            $table->index(['branch_balance_id', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::table('branch_ledger_entries', function (Blueprint $table) {
            $table->dropIndex(['branch_balance_id', 'voided_at']);
            $table->dropConstrainedForeignId('branch_balance_id');
            $table->dropColumn(['expense_category', 'payment_channel']);
        });

        Schema::dropIfExists('branch_balances');
    }
};
