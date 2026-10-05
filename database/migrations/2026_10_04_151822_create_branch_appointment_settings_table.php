<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_appointment_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->unique()
                ->constrained('branches')
                ->cascadeOnDelete();

            /*
             * Randevu başlangıçlarının hangi aralıklarla üretileceği.
             *
             * Örnek:
             * 15  = 15 dakikada bir
             * 30  = 30 dakikada bir
             * 45  = 45 dakikada bir
             * 60  = 60 dakikada bir
             */
            $table->unsignedSmallInteger('slot_interval_minutes')
                ->default(30);

            /*
             * Randevunun en az kaç dakika önceden alınabileceği.
             *
             * Örnek:
             * 120 = randevu saatinden en az 2 saat önce.
             */
            $table->unsignedInteger('minimum_booking_notice_minutes')
                ->default(0);

            /*
             * Bugünden itibaren maksimum kaç gün sonrasına
             * randevu alınabileceği.
             */
            $table->unsignedSmallInteger('maximum_booking_days')
                ->default(90);

            /*
             * Aynı gün randevu alınmasına izin veriliyor mu?
             */
            $table->boolean('same_day_booking_enabled')
                ->default(true);

            /*
             * Medloby üzerinden online randevu açık mı?
             */
            $table->boolean('online_booking_enabled')
                ->default(true);

            /*
             * Hasta randevusunu iptal edebilir mi?
             */
            $table->boolean('cancellation_enabled')
                ->default(true);

            /*
             * Randevu saatinden kaç dakika öncesine kadar
             * hasta iptal edebilir?
             */
            $table->unsignedInteger('cancellation_before_minutes')
                ->default(120);

            /*
             * Hasta randevuyu değiştirebilir mi?
             */
            $table->boolean('rescheduling_enabled')
                ->default(true);

            /*
             * Randevu saatinden kaç dakika öncesine kadar
             * değişiklik yapılabilir?
             */
            $table->unsignedInteger('rescheduling_before_minutes')
                ->default(120);

            /*
             * Randevu oluşturulduğunda varsayılan durum.
             */
            $table->string('default_appointment_status')
                ->default('pending');

            /*
             * Tedaviden önce ekstra tampon süre.
             *
             * Örneğin 15 dakika temizlik/hazırlık.
             */
            $table->unsignedSmallInteger('buffer_before_minutes')
                ->default(0);

            /*
             * Tedaviden sonra ekstra tampon süre.
             *
             * Örneğin 15 dakika temizlik/hazırlık.
             */
            $table->unsignedSmallInteger('buffer_after_minutes')
                ->default(0);

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            $table->index([
                'branch_id',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_appointment_settings');
    }
};