<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Doctor;
use App\Models\DoctorWorkingHour;
use App\Models\Person;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

test('a patient and a clinic cannot reserve overlapping time for a shared doctor or patient', function (bool $samePatient) {
    $directory = sys_get_temp_dir().'/medloby-concurrent-booking-'.Str::uuid();
    File::makeDirectory($directory, 0700);

    $postgres = DB::getDefaultConnection() === 'pgsql' ? DB::connection('pgsql') : null;
    $connectionName = $postgres ? 'appointment_test' : 'sqlite';
    $databaseConfig = ['database.default' => $connectionName];
    $schema = null;

    try {
        if ($postgres) {
            $schemaName = 'medloby_appointment_test_'.bin2hex(random_bytes(8));
            $postgres->statement('create schema "'.$schemaName.'"');
            $schema = $schemaName;
            $databaseConfig['database.connections.appointment_test'] = [
                ...$postgres->getConfig(), 'name' => $connectionName, 'search_path' => $schema,
            ];
        } else {
            File::put($directory.'/appointments.sqlite', '');
            $databaseConfig += [
                'database.connections.sqlite.url' => null,
                'database.connections.sqlite.database' => $directory.'/appointments.sqlite',
            ];
        }
        config($databaseConfig);
        DB::purge($connectionName);
        if ($schema !== null) {
            expect(DB::scalar('select current_schema()'))->toBe($schema);
        }
        $this->artisan('migrate')->assertSuccessful();

        $business = Business::factory()->create();
        $owner = User::factory()->create();
        $business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
        $branch = Branch::factory()->for($business)->create();
        $otherBranch = Branch::factory()->for($business)->create();
        $person = Person::factory()->for($business)->for($branch)->create();
        $doctor = Doctor::factory()->for($person)->create(['is_public' => true]);
        $patientDoctor = $samePatient
            ? Doctor::factory()->for(Person::factory()->for($business)->for($otherBranch))->create(['is_public' => true])
            : $doctor;
        $treatment = Treatment::create([
            'treatment_category_id' => TreatmentCategory::factory()->create()->id,
            'name' => 'Randevu Muayenesi', 'slug' => 'randevu-muayenesi', 'is_active' => true, 'duration_minutes' => 30,
        ]);
        foreach ([[$branch, $doctor], [$otherBranch, $patientDoctor]] as [$assignedBranch, $assignedDoctor]) {
            $assignedDoctor->treatments()->syncWithoutDetaching([$treatment->id => ['is_active' => true]]);
            $assignedBranch->treatments()->attach($treatment->id, ['is_active' => true, 'is_online_bookable' => true]);
            $assignedDoctor->branches()->attach($assignedBranch->id, ['status' => 'active']);
            DoctorWorkingHour::create([
                'doctor_id' => $assignedDoctor->id, 'branch_id' => $assignedBranch->id,
                'day_of_week' => 5, 'start_time' => '09:00', 'end_time' => '12:00', 'is_active' => true,
            ]);
        }
        $otherBranch->appointmentSetting()->create(['is_active' => true, 'online_booking_enabled' => true]);
        $patient = User::factory()->create();
        $profile = $patient->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hasta']);
        if ($samePatient) {
            $profile->conversations()->create(['business_id' => $business->id, 'branch_id' => $branch->id]);
        }

        $slot = ['treatment_id' => $treatment->id, 'starts_at' => '2026-12-04 09:00:00'];
        $clinicPatient = $samePatient ? ['patient_profile_id' => $profile->id] : ['patient_name' => 'Klinik Hastası'];
        $results = runAppointmentBookingRace(
            $directory,
            $databaseConfig,
            $owner->id,
            "/api/branches/{$branch->id}/clinic-calendar/appointments",
            $patient->id,
            "/api/patient/appointments/booking-branches/{$otherBranch->id}",
            $slot + ['doctor_id' => $doctor->id] + $clinicPatient,
            $slot + ['doctor_id' => $patientDoctor->id],
        );

        expect($results['clinic'][0])->toBe(201)
            ->and($results['patient'][0])->toBe(422)
            ->and($results['patient'][1]['message'])->toBe('Seçilen tarih ve saat için randevu müsait değil.');
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseHas('appointments', [
            'id' => $results['clinic'][1]['data']['id'], 'doctor_id' => $doctor->id, 'branch_id' => $branch->id,
            'patient_profile_id' => $samePatient ? $profile->id : null,
        ]);
        $this->assertDatabaseCount('appointment_status_histories', 1);
    } finally {
        DB::purge($connectionName);
        if ($schema !== null) {
            $postgres->statement('drop schema "'.$schema.'" cascade');
        }
        File::deleteDirectory($directory);
    }
})->with(['same doctor' => [false], 'same patient with different doctors' => [true]]);

function runAppointmentBookingRace(
    string $directory,
    array $databaseConfig,
    int $ownerId,
    string $clinicPath,
    int $patientId,
    string $patientPath,
    array $clinicPayload,
    array $patientPayload,
): array {
    $configureWorker = static function () use ($databaseConfig): void {
        config($databaseConfig + [
            'app.debug' => false,
            'cache.default' => 'array',
            'logging.default' => 'null',
            'mail.default' => 'array',
            'queue.default' => 'sync',
            'session.driver' => 'array',
        ]);
        DB::purge($databaseConfig['database.default']);
    };

    $waitForFile = static function (string $path, float $seconds): bool {
        $deadline = microtime(true) + $seconds;
        while (! is_file($path) && microtime(true) < $deadline) {
            usleep(10_000);
        }

        return is_file($path);
    };

    $postAs = static function (int $userId, string $path, array $payload): array {
        Carbon::setTestNow(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
        Auth::guard('web')->setUser(User::findOrFail($userId));
        $request = Request::create($path, 'POST', server: [
            'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
        ], content: json_encode($payload, JSON_THROW_ON_ERROR));
        $response = app(Kernel::class)->handle($request);

        return [$response->getStatusCode(), json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)];
    };

    return Concurrency::driver('process')->run([
        'clinic' => static function () use ($configureWorker, $directory, $waitForFile, $postAs, $ownerId, $clinicPath, $clinicPayload): array {
            $configureWorker();
            Appointment::created(static function () use ($directory, $waitForFile): void {
                File::put($directory.'/clinic-inserted', '');
                if (! $waitForFile($directory.'/patient-booking', 5)) {
                    throw new RuntimeException('The patient booking transaction did not start.');
                }
                // Give the second transaction time to try writing before this insert commits.
                $waitForFile($directory.'/patient-finished', 1);
            });

            return $postAs($ownerId, $clinicPath, $clinicPayload);
        },
        'patient' => static function () use ($configureWorker, $directory, $waitForFile, $postAs, $patientId, $patientPath, $patientPayload): array {
            $configureWorker();
            if (! $waitForFile($directory.'/clinic-inserted', 5)) {
                throw new RuntimeException('The clinic did not insert its appointment.');
            }
            DB::connection()->beforeExecuting(static function (string $sql, array $bindings, Connection $connection) use ($directory): void {
                if ($connection->transactionLevel() > 0 && ! is_file($directory.'/patient-booking')) {
                    File::put($directory.'/patient-booking', '');
                }
            });

            try {
                return $postAs($patientId, $patientPath, $patientPayload);
            } finally {
                File::put($directory.'/patient-finished', '');
            }
        },
    ], timeout: 15);
}
