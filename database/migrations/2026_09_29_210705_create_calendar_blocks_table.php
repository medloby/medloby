<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_blocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('doctor_id')
                ->nullable()
                ->constrained('doctors')
                ->cascadeOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->string('block_type')->default('manual');

            $table->string('title')->nullable();
            $table->text('reason')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index([
                'branch_id',
                'starts_at',
                'ends_at',
            ]);

            $table->index([
                'doctor_id',
                'starts_at',
                'ends_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_blocks');
    }
};