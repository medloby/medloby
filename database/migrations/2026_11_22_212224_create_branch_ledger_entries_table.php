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
        Schema::create('branch_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('request_key', 32);
            $table->date('entry_date');
            $table->string('kind', 10);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('description');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'request_key']);
            $table->index(['branch_id', 'entry_date', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_ledger_entries');
    }
};
