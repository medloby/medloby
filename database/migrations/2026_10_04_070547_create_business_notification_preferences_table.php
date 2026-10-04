<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_notification_preferences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->string('notification_type', 100);

            $table->boolean('in_app_enabled')
                ->default(true);

            $table->boolean('email_enabled')
                ->default(false);

            $table->timestamps();

            $table->unique([
                'business_id',
                'notification_type',
            ]);

            $table->index([
                'business_id',
                'in_app_enabled',
            ]);

            $table->index([
                'business_id',
                'email_enabled',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_notification_preferences');
    }
};