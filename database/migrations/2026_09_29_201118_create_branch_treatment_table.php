<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_treatment', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('treatment_id')
                ->constrained('treatments')
                ->cascadeOnDelete();

            $table->boolean('is_active')->default(true);

            $table->boolean('is_online_bookable')->default(false);
            $table->boolean('is_offer_enabled')->default(true);

            $table->unsignedInteger('duration_minutes')->nullable();

            $table->timestamps();

            $table->unique([
                'branch_id',
                'treatment_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_treatment');
    }
};