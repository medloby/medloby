<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('reviewed_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('decision', 30)
                ->default('pending');

            $table->timestamp('reviewed_at')
                ->nullable();

            $table->text('notes')
                ->nullable();

            $table->text('rejection_reason')
                ->nullable();

            $table->string('previous_status', 30)
                ->nullable();

            $table->string('new_status', 30)
                ->nullable();

            $table->timestamps();

            $table->index([
                'business_id',
                'decision',
            ]);

            $table->index([
                'reviewed_by_user_id',
                'reviewed_at',
            ]);

            $table->index([
                'decision',
                'reviewed_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_reviews');
    }
};