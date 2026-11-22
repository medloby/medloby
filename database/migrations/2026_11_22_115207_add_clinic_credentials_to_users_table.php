<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('login_code', 50)->nullable()->unique();
            $table->foreignId('staff_business_id')->nullable()->constrained('businesses')->cascadeOnDelete();
            $table->boolean('clinic_onboarding_pending')->default(false);
            $table->timestamp('temporary_password_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereNotNull('login_code')->exists()) {
            throw new RuntimeException('Restore the pre-migration backup before rolling back clinic account credentials.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_business_id');
            $table->dropUnique(['login_code']);
            $table->dropColumn(['login_code', 'clinic_onboarding_pending', 'temporary_password_expires_at']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
