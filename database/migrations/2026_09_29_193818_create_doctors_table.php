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
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('person_id')
                ->unique()
                ->constrained('people')
                ->cascadeOnDelete();

            $table->string('license_number')->nullable();
            $table->string('specialty')->nullable();
            $table->text('bio')->nullable();

            $table->string('profile_photo')->nullable();

            $table->string('status')->default('active');

            $table->boolean('is_public')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};