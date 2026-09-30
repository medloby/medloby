<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AppointmentTestSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            /*
             * ---------------------------------------------------------
             * 1. TEST İŞLETMESİ
             * ---------------------------------------------------------
             */
            $businessId = DB::table('businesses')->insertGetId([
                'name' => 'Medloby Test Kliniği',
                'slug' => 'medloby-test-klinigi',
                'type' => 'clinic',
                'description' => 'Medloby randevu motoru test işletmesi.',
                'email' => 'test@medloby.local',
                'phone' => '05000000000',
                'country_code' => 'TR',
                'city' => 'Istanbul',
                'district' => 'Kadikoy',
                'address' => 'Test adresi',
                'postal_code' => '34710',
                'status' => 'active',
                'is_verified' => true,
                'verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
             * ---------------------------------------------------------
             * 2. TEST ŞUBESİ
             * ---------------------------------------------------------
             */
            $branchId = DB::table('branches')->insertGetId([
                'business_id' => $businessId,
                'name' => 'İstanbul Merkez Şubesi',
                'slug' => 'istanbul-merkez',
                'phone' => '05000000001',
                'email' => 'istanbul@medloby.local',
                'country_code' => 'TR',
                'city' => 'Istanbul',
                'district' => 'Kadikoy',
                'address' => 'Test şube adresi',
                'postal_code' => '34710',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
             * ---------------------------------------------------------
             * 3. DOKTORUN PERSON KAYDI
             * ---------------------------------------------------------
             */
            $personId = DB::table('people')->insertGetId([
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'first_name' => 'Ahmet',
                'last_name' => 'Yılmaz',
                'title' => 'Dr.',
                'job_title' => 'Uzman Doktor',
                'specialty' => 'Estetik ve Medikal Tedaviler',
                'email' => 'dr.ahmet@medloby.local',
                'phone' => '05000000002',
                'status' => 'active',
                'start_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
             * ---------------------------------------------------------
             * 4. DOKTOR
             * ---------------------------------------------------------
             */
            $doctorId = DB::table('doctors')->insertGetId([
                'person_id' => $personId,
                'license_number' => 'TEST-DR-001',
                'specialty' => 'Estetik ve Medikal Tedaviler',
                'bio' => 'Medloby test doktoru.',
                'status' => 'active',
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
             * ---------------------------------------------------------
             * 5. DOKTOR → ŞUBE
             * ---------------------------------------------------------
             */
            DB::table('doctor_branch')->insert([
                'doctor_id' => $doctorId,
                'branch_id' => $branchId,
                'status' => 'active',
                'start_date' => now()->subDay()->toDateString(),
                'end_date' => null,
                'notes' => 'Test doktoru şube bağlantısı.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
             * ---------------------------------------------------------
             * 6. TEDAVİ KATEGORİSİ
             * ---------------------------------------------------------
             */
            $categoryId = DB::table('treatment_categories')->insertGetId([
                'parent_id' => null,
                'name' => 'Estetik Tedaviler',
                'slug' => 'estetik-tedaviler-test',
                'description' => 'Medloby test tedavi kategorisi.',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
             * ---------------------------------------------------------
             * 7. TEDAVİ
             * ---------------------------------------------------------
             *
             * Genel süre: 180 dakika
             * Doktor özel süresi: 120 dakika
             *
             * Böylece süre önceliğini de test edeceğiz.
             */
            $treatmentId = DB::table('treatments')->insertGetId([
                'treatment_category_id' => $categoryId,
                'name' => 'Medloby Test Tedavisi',
                'slug' => 'medloby-test-tedavisi',
                'description' => 'Randevu motoru test tedavisi.',
                'duration_minutes' => 180,
                'preparation' => null,
                'aftercare' => null,
                'included_services' => null,
                'excluded_services' => null,
                'image' => null,
                'is_online_bookable' => true,
                'is_offer_enabled' => true,
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
             * ---------------------------------------------------------
             * 8. ŞUBE → TEDAVİ
             * ---------------------------------------------------------
             *
             * Şube özel süresi: 150 dakika
             */
            DB::table('branch_treatment')->insert([
                'branch_id' => $branchId,
                'treatment_id' => $treatmentId,
                'is_active' => true,
                'is_online_bookable' => true,
                'is_offer_enabled' => true,
                'duration_minutes' => 150,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
             * ---------------------------------------------------------
             * 9. DOKTOR → TEDAVİ
             * ---------------------------------------------------------
             *
             * Doktor özel süresi: 120 dakika.
             *
             * Motorun süre önceliği:
             *
             * 120 dk doktor
             * 150 dk şube
             * 180 dk genel tedavi
             *
             * Sonuç: 120 dakika.
             */
            DB::table('doctor_treatment')->insert([
                'doctor_id' => $doctorId,
                'treatment_id' => $treatmentId,
                'is_active' => true,
                'duration_minutes' => 120,
                'notes' => 'Test doktoru özel tedavi süresi.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
             * ---------------------------------------------------------
             * 10. DOKTOR ÇALIŞMA SAATLERİ
             * ---------------------------------------------------------
             *
             * Pazartesi - Cuma
             * 09:00 - 17:00
             */
            $workingHours = [];

            for ($day = 1; $day <= 5; $day++) {
                $workingHours[] = [
                    'doctor_id' => $doctorId,
                    'branch_id' => $branchId,
                    'day_of_week' => $day,
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('doctor_working_hours')->insert($workingHours);

            /*
             * ---------------------------------------------------------
             * 11. TEST HASTASI KULLANICI HESABI
             * ---------------------------------------------------------
             */
            $userId = DB::table('users')->insertGetId([
                'name' => 'Mehmet Test',
                'email' => 'hasta.test@medloby.local',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
             * ---------------------------------------------------------
             * 12. TEST HASTA PROFİLİ
             * ---------------------------------------------------------
             */
            $patientId = DB::table('patient_profiles')->insertGetId([
                'user_id' => $userId,
                'first_name' => 'Mehmet',
                'last_name' => 'Test',
                'phone' => '05000000003',
                'birth_date' => '1990-01-01',
                'gender' => 'male',
                'country_code' => 'TR',
                'city' => 'Istanbul',
                'preferred_language' => 'tr',
                'preferred_currency' => 'TRY',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
             * ---------------------------------------------------------
             * 13. TEST ÇIKTISI
             * ---------------------------------------------------------
             */
            $this->command->info('');
            $this->command->info('==============================================');
            $this->command->info(' MEDLOBY APPOINTMENT TEST DATA HAZIR');
            $this->command->info('==============================================');
            $this->command->info("Business ID : {$businessId}");
            $this->command->info("Branch ID   : {$branchId}");
            $this->command->info("Doctor ID   : {$doctorId}");
            $this->command->info("Treatment ID: {$treatmentId}");
            $this->command->info("Patient ID  : {$patientId}");
            $this->command->info('Doktor çalışma saatleri: Pazartesi-Cuma 09:00-17:00');
            $this->command->info('Doktor özel tedavi süresi: 120 dakika');
            $this->command->info('==============================================');
            $this->command->info('');
        });
    }
}