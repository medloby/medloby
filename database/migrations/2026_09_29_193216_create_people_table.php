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
        Schema::create('people', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();

            $table->string('first_name');
            $table->string('last_name');

            $table->string('title')->nullable();
            $table->string('job_title')->nullable();
            $table->string('specialty')->nullable();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->string('status')->default('active');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['business_id', 'branch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};