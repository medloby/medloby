<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_contracts', function (Blueprint $table) {
            $table->id();

            $table->string('contract_type', 50);

            $table->string('title');

            $table->string('version', 30);

            $table->longText('content');

            $table->string('status', 30)
                ->default('draft');

            $table->boolean('is_required')
                ->default(true);

            $table->timestamp('effective_at')
                ->nullable();

            $table->timestamp('published_at')
                ->nullable();

            $table->timestamp('expires_at')
                ->nullable();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique([
                'contract_type',
                'version',
            ]);

            $table->index([
                'contract_type',
                'status',
            ]);

            $table->index([
                'effective_at',
                'expires_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_contracts');
    }
};