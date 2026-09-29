<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('first_name');
            $table->string('last_name');

            $table->string('phone')->nullable();

            $table->date('birth_date')->nullable();

            $table->string('gender')->nullable();

            $table->string('country_code', 2)->nullable();
            $table->string('city')->nullable();

            $table->string('preferred_language', 10)->default('tr');
            $table->string('preferred_currency', 3)->default('TRY');

            $table->string('profile_photo')->nullable();

            $table->string('status')->default('active');

            $table->timestamps();

            $table->index([
                'country_code',
                'city',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_profiles');
    }
};