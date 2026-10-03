<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_contract_acceptances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('platform_contract_id')
                ->constrained('platform_contracts')
                ->restrictOnDelete();

            $table->string('contract_version', 30);

            $table->timestamp('accepted_at');

            $table->string('ip_address', 45)
                ->nullable();

            $table->text('user_agent')
                ->nullable();

            $table->string('acceptance_method', 30)
                ->default('checkbox');

            $table->boolean('is_accepted')
                ->default(true);

            $table->timestamps();

            $table->index([
                'business_id',
                'platform_contract_id',
            ]);

            $table->index([
                'user_id',
                'accepted_at',
            ]);

            $table->index([
                'contract_version',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_contract_acceptances');
    }
};