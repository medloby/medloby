<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('appointment_id')
                ->constrained('appointments')
                ->cascadeOnDelete();

            $table->foreignId('changed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('old_status')->nullable();
            $table->string('new_status');

            $table->text('reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('changed_at')->useCurrent();

            $table->timestamps();

            $table->index([
                'appointment_id',
                'changed_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_status_histories');
    }
};