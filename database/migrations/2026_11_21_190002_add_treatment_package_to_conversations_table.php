<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('treatment_package_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['patient_profile_id', 'treatment_package_id', 'status'], 'conversations_package_inquiries_index');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_package_inquiries_index');
            $table->dropConstrainedForeignId('treatment_package_id');
        });
    }
};
