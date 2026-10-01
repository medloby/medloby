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
        Schema::table('offers', function (Blueprint $table) {
            $table->foreignId('treatment_id')
                ->nullable()
                ->after('patient_profile_id')
                ->constrained('treatments')
                ->nullOnDelete();

            $table->index([
                'treatment_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropForeign([
                'treatment_id',
            ]);

            $table->dropIndex([
                'treatment_id',
                'status',
            ]);

            $table->dropColumn('treatment_id');
        });
    }
};