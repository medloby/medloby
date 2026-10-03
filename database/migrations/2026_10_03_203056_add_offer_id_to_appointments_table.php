<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('offer_id')
                ->nullable()
                ->after('treatment_id')
                ->constrained('offers')
                ->nullOnDelete();

            $table->unique('offer_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique([
                'offer_id',
            ]);

            $table->dropForeign([
                'offer_id',
            ]);

            $table->dropColumn('offer_id');
        });
    }
};