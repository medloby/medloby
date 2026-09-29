<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_packages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');

            $table->text('description')->nullable();

            $table->string('package_type')->default('treatment');

            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 3)->default('TRY');

            $table->text('included_services')->nullable();
            $table->text('excluded_services')->nullable();

            $table->unsignedInteger('duration_days')->nullable();

            $table->boolean('includes_hotel')->default(false);
            $table->boolean('includes_transfer')->default(false);

            $table->boolean('is_offer_enabled')->default(true);
            $table->boolean('is_active')->default(true);

            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();

            $table->timestamps();

            $table->unique([
                'business_id',
                'slug',
            ]);

            $table->index([
                'business_id',
                'branch_id',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_packages');
    }
};