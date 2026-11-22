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
        Schema::create('offer_travel_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('hotel')->nullable();
            $table->json('transfers');
            $table->unsignedInteger('revision');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_travel_plans');
    }
};
