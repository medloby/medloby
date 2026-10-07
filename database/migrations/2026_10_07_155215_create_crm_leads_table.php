<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();

            $table->foreignId('patient_profile_id')
                ->nullable()
                ->constrained('patient_profiles')
                ->nullOnDelete();

            $table->foreignId('assigned_business_user_id')
                ->nullable()
                ->constrained('business_user')
                ->nullOnDelete();

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('country_code', 10)->nullable();

            $table->string('source')->default('manual');

            $table->string('status')->default('new');

            $table->string('priority')->default('normal');

            $table->text('notes')->nullable();

            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();

            $table->timestamp('converted_at')->nullable();
            $table->timestamp('lost_at')->nullable();

            $table->text('lost_reason')->nullable();

            $table->timestamps();

            $table->index([
                'business_id',
                'branch_id',
                'status',
            ]);

            $table->index([
                'business_id',
                'assigned_business_user_id',
                'status',
            ]);

            $table->index([
                'business_id',
                'source',
            ]);

            $table->index([
                'business_id',
                'next_follow_up_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};