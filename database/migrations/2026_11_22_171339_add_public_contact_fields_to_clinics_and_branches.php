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
        foreach (['businesses', 'branches'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->json('public_contact_fields')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['branches', 'businesses'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('public_contact_fields');
            });
        }
    }
};
