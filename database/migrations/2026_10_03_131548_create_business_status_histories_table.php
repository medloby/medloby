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
        Schema::create('business_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('changed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('previous_status', 30)
                ->nullable();

            $table->string('new_status', 30);

            $table->text('reason')
                ->nullable();

            $table->timestamp('changed_at');

            $table->timestamps();

            $table->index([
                'business_id',
                'changed_at',
            ]);

            $table->index([
                'changed_by_user_id',
                'changed_at',
            ]);

            $table->index([
                'previous_status',
                'new_status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_status_histories');
    }
};