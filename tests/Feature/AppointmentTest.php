<?php



use App\Models\Branch;

use App\Models\Appointment;

use App\Models\Business;

use App\Models\BusinessUser;

use App\Models\BusinessUserPermission;

use App\Models\Doctor;

use App\Models\DoctorLeave;

use App\Models\DoctorWorkingHour;

use App\Models\Person;

use App\Models\Permission;

use App\Models\Treatment;

use App\Models\TreatmentCategory;

use App\Models\CalendarBlock;

use App\Models\User;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Illuminate\Support\Facades\DB;



uses(RefreshDatabase::class);



function createAppointmentTestData(bool $grantCreatePermission = true): array

{

    $user = User::factory()->create();

    $business = Business::factory()->create();



    BusinessUser::create([

        'business_id' => $business->id,

        'user_id' => $user->id,

        'role' => 'business_owner',

        'is_active' => true,

    ]);



    $branch = Branch::create([

        'business_id' => $business->id,

        'name' => 'Test Şube',

        'slug' => 'test-sube-' . uniqid(),

        'country_code' => 'TR',

        'city' => 'Istanbul',

        'district' => 'Kadikoy',

        'status' => 'active',

    ]);



    $person = Person::create([

        'business_id' => $business->id,

        'branch_id' => $branch->id,

        'first_name' => 'Test',

        'last_name' => 'Doktor',

        'title' => 'Dr.',

        'job_title' => 'Doktor',

        'specialty' => 'Test Uzmanlığı',

        'status' => 'active',

    ]);



    $doctor = Doctor::create([

        'person_id' => $person->id,

        'license_number' => 'TEST-' . uniqid(),

        'specialty' => 'Test Uzmanlığı',

        'status' => 'active',

        'is_public' => true,

    ]);



    $category = TreatmentCategory::create([

        'name' => 'Test Kategorisi',

        'slug' => 'test-kategorisi-' . uniqid(),

        'sort_order' => 1,

        'is_active' => true,

    ]);



    $treatment = Treatment::create([

        'treatment_category_id' => $category->id,

        'name' => 'Test Tedavisi',

        'slug' => 'test-tedavisi-' . uniqid(),

        'duration_minutes' => 60,

        'is_online_bookable' => true,

        'is_offer_enabled' => true,

        'is_active' => true,

        'sort_order' => 1,

    ]);



    DB::table('doctor_branch')->insert([

        'doctor_id' => $doctor->id,

        'branch_id' => $branch->id,

        'status' => 'active',

        'start_date' => now()->toDateString(),

        'end_date' => null,

        'notes' => null,

        'created_at' => now(),

        'updated_at' => now(),

    ]);



    DB::table('branch_treatment')->insert([

        'branch_id' => $branch->id,

        'treatment_id' => $treatment->id,

        'duration_minutes' => 60,

        'is_online_bookable' => true,

        'is_active' => true,

        'created_at' => now(),

        'updated_at' => now(),

    ]);



    DB::table('doctor_treatment')->insert([

        'doctor_id' => $doctor->id,

        'treatment_id' => $treatment->id,

        'duration_minutes' => 60,

        'is_active' => true,

        'notes' => null,

        'created_at' => now(),

        'updated_at' => now(),

    ]);



    $startsAt = now()->addDays(7)->setTime(10, 0, 0);



    DoctorWorkingHour::create([

        'doctor_id' => $doctor->id,

        'branch_id' => $branch->id,

        'day_of_week' => $startsAt->dayOfWeek,

        'start_time' => '09:00',

        'end_time' => '18:00',

        'is_active' => true,

    ]);



    if ($grantCreatePermission) {

        grantAppointmentPermission($user, $business, 'appointments.create');

    }



    return [

        'user' => $user,

        'business' => $business,

        'branch' => $branch,

        'doctor' => $doctor,

        'treatment' => $treatment,

        'startsAt' => $startsAt,

    ];

}




function createAppointmentBranchData(array $data): array
{
    $branch = Branch::create([
        'business_id' => $data['business']->id,
        'name' => 'Ek Test Şube',
        'slug' => 'ek-test-sube-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Besiktas',
        'status' => 'active',
    ]);

    $person = Person::create([
        'business_id' => $data['business']->id,
        'branch_id' => $branch->id,
        'first_name' => 'Ek',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $doctor = Doctor::create([
        'person_id' => $person->id,
        'license_number' => 'TEST-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    DB::table('doctor_branch')->insert([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'status' => 'active',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('branch_treatment')->insert([
        'branch_id' => $branch->id,
        'treatment_id' => $data['treatment']->id,
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('doctor_treatment')->insert([
        'doctor_id' => $doctor->id,
        'treatment_id' => $data['treatment']->id,
        'duration_minutes' => 60,
        'is_active' => true,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $startsAt = now()->addDays(8)->setTime(10, 0, 0);

    DoctorWorkingHour::create([
        'doctor_id' => $doctor->id,
        'branch_id' => $branch->id,
        'day_of_week' => $startsAt->dayOfWeek,
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);

    return [
        'branch' => $branch,
        'doctor' => $doctor,
        'startsAt' => $startsAt,
    ];
}

function grantAppointmentPermission(User $user, Business $business, string $permissionName): void

{

    $permission = Permission::firstOrCreate(

        ['name' => $permissionName],

        [

            'display_name' => $permissionName,

            'module' => 'appointments',

            'description' => null,

            'is_active' => true,

        ]

    );



    BusinessUserPermission::updateOrCreate(

        [

            'business_id' => $business->id,

            'user_id' => $user->id,

            'permission_id' => $permission->id,

        ],

        [

            'granted_by_user_id' => $user->id,

            'is_allowed' => true,

        ]

    );

}



function denyAppointmentPermission(User $user, Business $business, string $permissionName): void

{

    $permission = Permission::firstOrCreate(

        ['name' => $permissionName],

        [

            'display_name' => $permissionName,

            'module' => 'appointments',

            'description' => null,

            'is_active' => true,

        ]

    );



    BusinessUserPermission::updateOrCreate(

        [

            'business_id' => $business->id,

            'user_id' => $user->id,

            'permission_id' => $permission->id,

        ],

        [

            'granted_by_user_id' => $user->id,

            'is_allowed' => false,

        ]

    );

}



function createPendingAppointment($testCase, array $data): int

{

    $response = $testCase

        ->actingAs($data['user'])

        ->postJson('/api/appointments', [

            'business_id' => $data['business']->id,

            'branch_id' => $data['branch']->id,

            'doctor_id' => $data['doctor']->id,

            'treatment_id' => $data['treatment']->id,

            'starts_at' => $data['startsAt']->toDateTimeString(),

            'status' => 'pending',

            'source' => 'clinic',

            'patient_name' => 'Test Hasta',

        ]);



    $response->assertCreated();



    return $response->json('data.id');

}



test('authenticated business user can create an appointment', function () {

    $data = createAppointmentTestData();



    $response = $this

        ->actingAs($data['user'])

        ->postJson('/api/appointments', [

            'business_id' => $data['business']->id,

            'branch_id' => $data['branch']->id,

            'doctor_id' => $data['doctor']->id,

            'treatment_id' => $data['treatment']->id,

            'starts_at' => $data['startsAt']->toDateTimeString(),

            'status' => 'pending',

            'source' => 'clinic',

            'patient_name' => 'Test Hasta',

            'patient_phone' => '05550000000',

            'patient_email' => 'test\@example.com',

            'notes' => 'Test randevusu',

        ]);



    $response->assertCreated();



    $this->assertDatabaseHas('appointments', [

        'business_id' => $data['business']->id,

        'branch_id' => $data['branch']->id,

        'doctor_id' => $data['doctor']->id,

        'treatment_id' => $data['treatment']->id,

        'status' => 'pending',

        'source' => 'clinic',

        'patient_name' => 'Test Hasta',

    ]);

});



test('business user without create appointment permission cannot create an appointment', function () {

    $data = createAppointmentTestData(false);



    $response = $this

        ->actingAs($data['user'])

        ->postJson('/api/appointments', [

            'business_id' => $data['business']->id,

            'branch_id' => $data['branch']->id,

            'doctor_id' => $data['doctor']->id,

            'treatment_id' => $data['treatment']->id,

            'starts_at' => $data['startsAt']->toDateTimeString(),

            'status' => 'pending',

            'source' => 'clinic',

            'patient_name' => 'Yetkisiz Hasta',

        ]);



    $response->assertForbidden();

    $response->assertJson([

        'success' => false,

        'message' => 'Randevu oluşturma yetkiniz bulunmuyor.',

    ]);



    $this->assertDatabaseCount('appointments', 0);

});



test('doctor cannot have two appointments at the same time', function () {

    $data = createAppointmentTestData();



    $firstResponse = $this

        ->actingAs($data['user'])

        ->postJson('/api/appointments', [

            'business_id' => $data['business']->id,

            'branch_id' => $data['branch']->id,

            'doctor_id' => $data['doctor']->id,

            'treatment_id' => $data['treatment']->id,

            'starts_at' => $data['startsAt']->toDateTimeString(),

            'status' => 'pending',

            'source' => 'clinic',

            'patient_name' => 'İlk Hasta',

        ]);



    $firstResponse->assertCreated();



    $secondResponse = $this

        ->actingAs($data['user'])

        ->postJson('/api/appointments', [

            'business_id' => $data['business']->id,

            'branch_id' => $data['branch']->id,

            'doctor_id' => $data['doctor']->id,

            'treatment_id' => $data['treatment']->id,

            'starts_at' => $data['startsAt']->toDateTimeString(),

            'status' => 'pending',

            'source' => 'clinic',

            'patient_name' => 'İkinci Hasta',

        ]);



    $secondResponse->assertStatus(422);

    $this->assertDatabaseCount('appointments', 1);

});



test('business user can confirm a pending appointment', function () {

    $data = createAppointmentTestData();

    grantAppointmentPermission($data['user'], $data['business'], 'appointments.confirm');



    $appointmentId = createPendingAppointment($this, $data);



    $response = $this

        ->actingAs($data['user'])

        ->postJson("/api/appointments/{$appointmentId}/confirm");



    $response->assertOk();

    $response->assertJsonPath('data.status', 'confirmed');



    $this->assertDatabaseHas('appointments', [

        'id' => $appointmentId,

        'status' => 'confirmed',

    ]);

});



test('business user without confirm permission cannot confirm a pending appointment', function () {

    $data = createAppointmentTestData();

    grantAppointmentPermission($data['user'], $data['business'], 'appointments.confirm');



    $appointmentId = createPendingAppointment($this, $data);

    denyAppointmentPermission($data['user'], $data['business'], 'appointments.confirm');



    $response = $this

        ->actingAs($data['user'])

        ->postJson("/api/appointments/{$appointmentId}/confirm");



    $response->assertForbidden();

    $response->assertJson([

        'success' => false,

        'message' => 'Bu randevu işlemi için yetkiniz bulunmuyor.',

    ]);



    $this->assertDatabaseHas('appointments', [

        'id' => $appointmentId,

        'status' => 'pending',

    ]);

});



test('business user can cancel a pending appointment', function () {

    $data = createAppointmentTestData();

    grantAppointmentPermission($data['user'], $data['business'], 'appointments.cancel');



    $appointmentId = createPendingAppointment($this, $data);



    $response = $this

        ->actingAs($data['user'])

        ->postJson("/api/appointments/{$appointmentId}/cancel", [

            'cancellation_reason' => 'Test iptal nedeni',

        ]);



    $response->assertOk();

    $response->assertJsonPath('data.status', 'cancelled');



    $this->assertDatabaseHas('appointments', [

        'id' => $appointmentId,

        'status' => 'cancelled',

    ]);

});



test('business user without cancel permission cannot cancel a pending appointment', function () {

    $data = createAppointmentTestData();

    grantAppointmentPermission($data['user'], $data['business'], 'appointments.cancel');



    $appointmentId = createPendingAppointment($this, $data);

    denyAppointmentPermission($data['user'], $data['business'], 'appointments.cancel');



    $response = $this

        ->actingAs($data['user'])

        ->postJson("/api/appointments/{$appointmentId}/cancel", [

            'cancellation_reason' => 'Yetkisiz iptal denemesi',

        ]);



    $response->assertForbidden();

    $response->assertJson([

        'success' => false,

        'message' => 'Bu randevu işlemi için yetkiniz bulunmuyor.',

    ]);



    $this->assertDatabaseHas('appointments', [

        'id' => $appointmentId,

        'status' => 'pending',

    ]);

});



test('business user can complete a confirmed appointment', function () {

    $data = createAppointmentTestData();

    grantAppointmentPermission($data['user'], $data['business'], 'appointments.confirm');

    grantAppointmentPermission($data['user'], $data['business'], 'appointments.complete');



    $appointmentId = createPendingAppointment($this, $data);



    $confirmResponse = $this

        ->actingAs($data['user'])

        ->postJson("/api/appointments/{$appointmentId}/confirm");

    $confirmResponse->assertOk();



    $completeResponse = $this

        ->actingAs($data['user'])

        ->postJson("/api/appointments/{$appointmentId}/complete");



    $completeResponse->assertOk();

    $completeResponse->assertJsonPath('data.status', 'completed');



    $this->assertDatabaseHas('appointments', [

        'id' => $appointmentId,

        'status' => 'completed',

    ]);

});



test('business user without complete permission cannot complete a confirmed appointment', function () {

    $data = createAppointmentTestData();

    grantAppointmentPermission($data['user'], $data['business'], 'appointments.confirm');

    grantAppointmentPermission($data['user'], $data['business'], 'appointments.complete');



    $appointmentId = createPendingAppointment($this, $data);



    $confirmResponse = $this

        ->actingAs($data['user'])

        ->postJson("/api/appointments/{$appointmentId}/confirm");

    $confirmResponse->assertOk();



    denyAppointmentPermission($data['user'], $data['business'], 'appointments.complete');



    $response = $this

        ->actingAs($data['user'])

        ->postJson("/api/appointments/{$appointmentId}/complete");



    $response->assertForbidden();

    $response->assertJson([

        'success' => false,

        'message' => 'Bu randevu işlemi için yetkiniz bulunmuyor.',

    ]);



    $this->assertDatabaseHas('appointments', [

        'id' => $appointmentId,

        'status' => 'confirmed',

    ]);

});



test('business user can mark a confirmed appointment as no show', function () {

    $data = createAppointmentTestData();

    grantAppointmentPermission($data['user'], $data['business'], 'appointments.confirm');

    grantAppointmentPermission($data['user'], $data['business'], 'appointments.mark_no_show');



    $appointmentId = createPendingAppointment($this, $data);



    $confirmResponse = $this

        ->actingAs($data['user'])

        ->postJson("/api/appointments/{$appointmentId}/confirm");

    $confirmResponse->assertOk();



    $response = $this

        ->actingAs($data['user'])

        ->postJson("/api/appointments/{$appointmentId}/no-show");



    $response->assertOk();

    $response->assertJsonPath('data.status', 'no_show');



    $this->assertDatabaseHas('appointments', [

        'id' => $appointmentId,

        'status' => 'no_show',

    ]);

});



test('business user without no show permission cannot mark a confirmed appointment as no show', function () {

    $data = createAppointmentTestData();

    grantAppointmentPermission($data['user'], $data['business'], 'appointments.confirm');

    grantAppointmentPermission($data['user'], $data['business'], 'appointments.mark_no_show');



    $appointmentId = createPendingAppointment($this, $data);



    $confirmResponse = $this

        ->actingAs($data['user'])

        ->postJson("/api/appointments/{$appointmentId}/confirm");

    $confirmResponse->assertOk();



    denyAppointmentPermission($data['user'], $data['business'], 'appointments.mark_no_show');



    $response = $this

        ->actingAs($data['user'])

        ->postJson("/api/appointments/{$appointmentId}/no-show");



    $response->assertForbidden();

    $response->assertJson([

        'success' => false,

        'message' => 'Bu randevu işlemi için yetkiniz bulunmuyor.',

    ]);



    $this->assertDatabaseHas('appointments', [

        'id' => $appointmentId,

        'status' => 'confirmed',

    ]);

});



test('business user can reschedule an appointment', function () {

    $data = createAppointmentTestData();

    grantAppointmentPermission($data['user'], $data['business'], 'appointments.reschedule');



    $newStartsAt = $data['startsAt']->copy()->addDays(1);



    DoctorWorkingHour::create([

        'doctor_id' => $data['doctor']->id,

        'branch_id' => $data['branch']->id,

        'day_of_week' => $newStartsAt->dayOfWeek,

        'start_time' => '09:00',

        'end_time' => '18:00',

        'is_active' => true,

    ]);



    $appointmentId = createPendingAppointment($this, $data);



    $response = $this

        ->actingAs($data['user'])

        ->postJson("/api/appointments/{$appointmentId}/reschedule", [

            'starts_at' => $newStartsAt->toDateTimeString(),

            'reason' => 'Test yeniden planlama',

        ]);



    $response->assertOk();

    $response->assertJsonPath('data.status', 'pending');



    $newAppointmentId = $response->json('data.id');



    expect($newAppointmentId)->not->toBe($appointmentId);



    $this->assertDatabaseHas('appointments', [

        'id' => $appointmentId,

        'status' => 'rescheduled',

    ]);



    $this->assertDatabaseHas('appointments', [

        'id' => $newAppointmentId,

        'status' => 'pending',

    ]);

});



test('business user without reschedule permission cannot reschedule an appointment', function () {

    $data = createAppointmentTestData();

    grantAppointmentPermission($data['user'], $data['business'], 'appointments.reschedule');



    $newStartsAt = $data['startsAt']->copy()->addDays(1);



    DoctorWorkingHour::create([

        'doctor_id' => $data['doctor']->id,

        'branch_id' => $data['branch']->id,

        'day_of_week' => $newStartsAt->dayOfWeek,

        'start_time' => '09:00',

        'end_time' => '18:00',

        'is_active' => true,

    ]);



    $appointmentId = createPendingAppointment($this, $data);

    denyAppointmentPermission($data['user'], $data['business'], 'appointments.reschedule');



    $response = $this

        ->actingAs($data['user'])

        ->postJson("/api/appointments/{$appointmentId}/reschedule", [

            'starts_at' => $newStartsAt->toDateTimeString(),

            'reason' => 'Yetkisiz yeniden planlama',

        ]);



    $response->assertForbidden();

    $response->assertJson([

        'success' => false,

        'message' => 'Bu randevu işlemi için yetkiniz bulunmuyor.',

    ]);



    $this->assertDatabaseHas('appointments', [

        'id' => $appointmentId,

        'status' => 'pending',

    ]);



    $this->assertDatabaseCount('appointments', 1);

});




test('business owner can create appointment in every branch of their business', function () {
    $data = createAppointmentTestData();
    $branchData = createAppointmentBranchData($data);

    $response = $this
        ->actingAs($data['user'])
        ->postJson('/api/appointments', [
            'business_id' => $data['business']->id,
            'branch_id' => $branchData['branch']->id,
            'doctor_id' => $branchData['doctor']->id,
            'treatment_id' => $data['treatment']->id,
            'starts_at' => $branchData['startsAt']->toDateTimeString(),
            'status' => 'pending',
            'source' => 'clinic',
            'patient_name' => 'İkinci Şube Hastası',
        ]);

    $response->assertCreated();

    $this->assertDatabaseHas('appointments', [
        'business_id' => $data['business']->id,
        'branch_id' => $branchData['branch']->id,
        'doctor_id' => $branchData['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'status' => 'pending',
    ]);
});

test('staff cannot create appointment in an unassigned branch', function () {
    $data = createAppointmentTestData();
    $staff = User::factory()->create([
        'name' => 'Yetkisiz Şube Personeli',
    ]);

    $membership = BusinessUser::create([
        'business_id' => $data['business']->id,
        'user_id' => $staff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $membership->branches()->attach($data['branch']->id, [
        'is_active' => true,
    ]);

    $branchData = createAppointmentBranchData($data);

    $response = $this
        ->actingAs($staff)
        ->postJson('/api/appointments', [
            'business_id' => $data['business']->id,
            'branch_id' => $branchData['branch']->id,
            'doctor_id' => $branchData['doctor']->id,
            'treatment_id' => $data['treatment']->id,
            'starts_at' => $branchData['startsAt']->toDateTimeString(),
            'status' => 'pending',
            'source' => 'clinic',
            'patient_name' => 'Yetkisiz Hasta',
        ]);

    $response->assertForbidden();

    $response->assertJson([
        'success' => false,
        'message' => 'Bu şube için randevu oluşturma yetkiniz yok.',
    ]);

    $this->assertDatabaseCount('appointments', 0);
});

test('staff cannot create appointment in inactive assigned branch', function () {
    $data = createAppointmentTestData();
    $staff = User::factory()->create([
        'name' => 'Pasif Şube Personeli',
    ]);

    $membership = BusinessUser::create([
        'business_id' => $data['business']->id,
        'user_id' => $staff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $membership->branches()->attach($data['branch']->id, [
        'is_active' => false,
    ]);

    $response = $this
        ->actingAs($staff)
        ->postJson('/api/appointments', [
            'business_id' => $data['business']->id,
            'branch_id' => $data['branch']->id,
            'doctor_id' => $data['doctor']->id,
            'treatment_id' => $data['treatment']->id,
            'starts_at' => $data['startsAt']->toDateTimeString(),
            'status' => 'pending',
            'source' => 'clinic',
            'patient_name' => 'Pasif Şube Hastası',
        ]);

    $response->assertForbidden();

    $response->assertJson([
        'success' => false,
        'message' => 'Bu şube için randevu oluşturma yetkiniz yok.',
    ]);

    $this->assertDatabaseCount('appointments', 0);
});

test('business user cannot create appointment in another business branch', function () {
    $data = createAppointmentTestData();

    $otherBusiness = Business::factory()->create([
        'name' => 'Diğer İşletme',
    ]);

    $otherBranch = Branch::create([
        'business_id' => $otherBusiness->id,
        'name' => 'Diğer İşletme Şubesi',
        'slug' => 'diger-isletme-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Besiktas',
        'status' => 'active',
    ]);

    $response = $this
        ->actingAs($data['user'])
        ->postJson('/api/appointments', [
            'business_id' => $data['business']->id,
            'branch_id' => $otherBranch->id,
            'doctor_id' => $data['doctor']->id,
            'treatment_id' => $data['treatment']->id,
            'starts_at' => $data['startsAt']->toDateTimeString(),
            'status' => 'pending',
            'source' => 'clinic',
            'patient_name' => 'Başka İşletme Hastası',
        ]);

    $response->assertForbidden();

    $response->assertJson([
        'success' => false,
        'message' => 'Bu şube için randevu oluşturma yetkiniz yok.',
    ]);

    $this->assertDatabaseCount('appointments', 0);
});

test('branch and treatment have a working many-to-many relationship', function () {

    $data = createAppointmentTestData();



    $branch = $data['branch'];

    $treatment = $data['treatment'];



    $branch->load('treatments');



    expect(

        $branch->treatments->contains(

            fn ($item) => $item->id === $treatment->id

        )

    )->toBeTrue();



    $treatment->load('branches');



    expect(

        $treatment->branches->contains(

            fn ($item) => $item->id === $branch->id

        )

    )->toBeTrue();

});

test('staff cannot confirm appointment from unassigned branch', function () {
    $data = createAppointmentTestData();

    $staff = User::factory()->create([
        'name' => 'Confirm Yetkisiz Personel',
    ]);

    $businessUser = BusinessUser::create([
        'business_id' => $data['business']->id,
        'user_id' => $staff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    DB::table('business_user_branch')->insert([
        'business_user_id' => $businessUser->id,
        'branch_id' => $data['branch']->id,
        'is_active' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    grantAppointmentPermission(
        $staff,
        $data['business'],
        'appointments.confirm'
    );

    $appointment = Appointment::create([
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'patient_profile_id' => null,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'starts_at' => $data['startsAt'],
        'ends_at' => $data['startsAt']->copy()->addHour(),
        'status' => 'pending',
        'source' => 'clinic',
        'patient_name' => 'Confirm Şube Testi',
    ]);

    $response = $this
        ->actingAs($staff)
        ->postJson("/api/appointments/{$appointment->id}/confirm");

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu randevu üzerinde işlem yapma yetkiniz yok.',
    ]);

    $this->assertDatabaseHas('appointments', [
        'id' => $appointment->id,
        'status' => 'pending',
    ]);
});

test('staff cannot cancel appointment from unassigned branch', function () {
    $data = createAppointmentTestData();

    $staff = User::factory()->create([
        'name' => 'Cancel Yetkisiz Personel',
    ]);

    $businessUser = BusinessUser::create([
        'business_id' => $data['business']->id,
        'user_id' => $staff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    DB::table('business_user_branch')->insert([
        'business_user_id' => $businessUser->id,
        'branch_id' => $data['branch']->id,
        'is_active' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    grantAppointmentPermission(
        $staff,
        $data['business'],
        'appointments.cancel'
    );

    $appointment = Appointment::create([
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'patient_profile_id' => null,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'starts_at' => $data['startsAt'],
        'ends_at' => $data['startsAt']->copy()->addHour(),
        'status' => 'pending',
        'source' => 'clinic',
        'patient_name' => 'Cancel Şube Testi',
    ]);

    $response = $this
        ->actingAs($staff)
        ->postJson("/api/appointments/{$appointment->id}/cancel", [
            'cancellation_reason' => 'Yetkisiz şube testi',
        ]);

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu randevu üzerinde işlem yapma yetkiniz yok.',
    ]);

    $this->assertDatabaseHas('appointments', [
        'id' => $appointment->id,
        'status' => 'pending',
    ]);
});

test('staff cannot complete appointment from unassigned branch', function () {
    $data = createAppointmentTestData();

    $staff = User::factory()->create([
        'name' => 'Complete Yetkisiz Personel',
    ]);

    $businessUser = BusinessUser::create([
        'business_id' => $data['business']->id,
        'user_id' => $staff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    DB::table('business_user_branch')->insert([
        'business_user_id' => $businessUser->id,
        'branch_id' => $data['branch']->id,
        'is_active' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    grantAppointmentPermission(
        $staff,
        $data['business'],
        'appointments.complete'
    );

    $appointment = Appointment::create([
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'patient_profile_id' => null,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'starts_at' => $data['startsAt'],
        'ends_at' => $data['startsAt']->copy()->addHour(),
        'status' => 'confirmed',
        'source' => 'clinic',
        'patient_name' => 'Complete Şube Testi',
    ]);

    $response = $this
        ->actingAs($staff)
        ->postJson("/api/appointments/{$appointment->id}/complete");

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu randevu üzerinde işlem yapma yetkiniz yok.',
    ]);

    $this->assertDatabaseHas('appointments', [
        'id' => $appointment->id,
        'status' => 'confirmed',
    ]);
});

test('staff cannot mark appointment as no show from unassigned branch', function () {
    $data = createAppointmentTestData();

    $staff = User::factory()->create([
        'name' => 'No Show Yetkisiz Personel',
    ]);

    $businessUser = BusinessUser::create([
        'business_id' => $data['business']->id,
        'user_id' => $staff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    DB::table('business_user_branch')->insert([
        'business_user_id' => $businessUser->id,
        'branch_id' => $data['branch']->id,
        'is_active' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    grantAppointmentPermission(
        $staff,
        $data['business'],
        'appointments.no_show'
    );

    $appointment = Appointment::create([
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'patient_profile_id' => null,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'starts_at' => $data['startsAt'],
        'ends_at' => $data['startsAt']->copy()->addHour(),
        'status' => 'confirmed',
        'source' => 'clinic',
        'patient_name' => 'No Show Şube Testi',
    ]);

    $response = $this
        ->actingAs($staff)
        ->postJson("/api/appointments/{$appointment->id}/no-show");

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu randevu üzerinde işlem yapma yetkiniz yok.',
    ]);

    $this->assertDatabaseHas('appointments', [
        'id' => $appointment->id,
        'status' => 'confirmed',
    ]);
});

test('staff cannot reschedule appointment from unassigned branch', function () {
    $data = createAppointmentTestData();

    $staff = User::factory()->create([
        'name' => 'Reschedule Yetkisiz Personel',
    ]);

    $businessUser = BusinessUser::create([
        'business_id' => $data['business']->id,
        'user_id' => $staff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    DB::table('business_user_branch')->insert([
        'business_user_id' => $businessUser->id,
        'branch_id' => $data['branch']->id,
        'is_active' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    grantAppointmentPermission(
        $staff,
        $data['business'],
        'appointments.reschedule'
    );

    $appointment = Appointment::create([
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'patient_profile_id' => null,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'starts_at' => $data['startsAt'],
        'ends_at' => $data['startsAt']->copy()->addHour(),
        'status' => 'pending',
        'source' => 'clinic',
        'patient_name' => 'Reschedule Şube Testi',
    ]);

    $newStartsAt = $data['startsAt']->copy()->addDays(1);

    $response = $this
        ->actingAs($staff)
        ->postJson("/api/appointments/{$appointment->id}/reschedule", [
            'starts_at' => $newStartsAt->toDateTimeString(),
            'reason' => 'Yetkisiz şube testi',
        ]);

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu randevu üzerinde işlem yapma yetkiniz yok.',
    ]);

    $this->assertDatabaseHas('appointments', [
        'id' => $appointment->id,
        'status' => 'pending',
    ]);
});

test('staff cannot complete appointment from inactive branch assignment', function () {
    $data = createAppointmentTestData();

    $staff = User::factory()->create([
        'name' => 'Pasif Atama Personeli',
    ]);

    $businessUser = BusinessUser::create([
        'business_id' => $data['business']->id,
        'user_id' => $staff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    DB::table('business_user_branch')->insert([
        'business_user_id' => $businessUser->id,
        'branch_id' => $data['branch']->id,
        'is_active' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    grantAppointmentPermission(
        $staff,
        $data['business'],
        'appointments.complete'
    );

    $appointment = Appointment::create([
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'patient_profile_id' => null,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'starts_at' => $data['startsAt'],
        'ends_at' => $data['startsAt']->copy()->addHour(),
        'status' => 'confirmed',
        'source' => 'clinic',
        'patient_name' => 'Pasif Atama Testi',
    ]);

    $response = $this
        ->actingAs($staff)
        ->postJson("/api/appointments/{$appointment->id}/complete");

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu randevu üzerinde işlem yapma yetkiniz yok.',
    ]);

    $this->assertDatabaseHas('appointments', [
        'id' => $appointment->id,
        'status' => 'confirmed',
    ]);
});

test('business user can filter appointments by branch doctor treatment status and date range', function () {
    $data = createAppointmentTestData();

    grantAppointmentPermission(
        $data['user'],
        $data['business'],
        'appointments.create'
    );

    $appointment = Appointment::create([
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'patient_profile_id' => null,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'starts_at' => $data['startsAt'],
        'ends_at' => $data['startsAt']->copy()->addMinutes(60),
        'status' => 'pending',
        'source' => 'clinic',
        'patient_name' => 'Takvim Test Hastası',
        'patient_phone' => '05550000000',
        'patient_email' => 'calendar@example.com',
        'notes' => 'Takvim filtre testi.',
    ]);

    $appointment->statusHistories()->create([
        'changed_by_user_id' => $data['user']->id,
        'old_status' => null,
        'new_status' => 'pending',
        'reason' => 'Takvim filtre testi.',
        'changed_at' => now(),
    ]);

    $response = $this->actingAs($data['user'])
        ->getJson('/api/appointments?' . http_build_query([
            'business_id' => $data['business']->id,
            'branch_id' => $data['branch']->id,
            'doctor_id' => $data['doctor']->id,
            'treatment_id' => $data['treatment']->id,
            'status' => 'pending',
            'from' => $data['startsAt']->toDateString(),
            'to' => $data['startsAt']->toDateString(),
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(
        collect($response->json('data.data'))
            ->pluck('id')
            ->contains($appointment->id)
    )->toBeTrue();
});

test('business user cannot see appointments from another business through calendar filters', function () {
    $data = createAppointmentTestData();

    $otherBusiness = Business::factory()->create();

    $otherBranch = Branch::create([
        'business_id' => $otherBusiness->id,
        'name' => 'Diğer İşletme Şubesi',
        'slug' => 'diger-isletme-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Besiktas',
        'status' => 'active',
    ]);

    $otherAppointment = Appointment::create([
        'business_id' => $otherBusiness->id,
        'branch_id' => $otherBranch->id,
        'patient_profile_id' => null,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'starts_at' => $data['startsAt'],
        'ends_at' => $data['startsAt']->copy()->addMinutes(60),
        'status' => 'pending',
        'source' => 'clinic',
        'patient_name' => 'Başka İşletme Hastası',
        'patient_phone' => '05551111111',
        'patient_email' => 'other@example.com',
        'notes' => null,
    ]);

    $response = $this->actingAs($data['user'])
        ->getJson('/api/appointments?' . http_build_query([
            'business_id' => $otherBusiness->id,
            'branch_id' => $otherBranch->id,
            'doctor_id' => $data['doctor']->id,
            'treatment_id' => $data['treatment']->id,
            'from' => $data['startsAt']->toDateString(),
            'to' => $data['startsAt']->toDateString(),
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(
        collect($response->json('data.data'))
            ->pluck('id')
            ->contains($otherAppointment->id)
    )->toBeFalse();
});

test('staff can only see appointments from assigned active branches', function () {
    $data = createAppointmentTestData();

    $businessUser = BusinessUser::where('user_id', $data['user']->id)
        ->where('business_id', $data['business']->id)
        ->firstOrFail();

    $businessUser->update([
        'role' => 'staff',
    ]);

    DB::table('business_user_branch')->insert([
        'business_user_id' => $businessUser->id,
        'branch_id' => $data['branch']->id,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $unassignedBranch = Branch::create([
        'business_id' => $data['business']->id,
        'name' => 'Yetkisiz Test Şubesi',
        'slug' => 'yetkisiz-test-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Besiktas',
        'status' => 'active',
    ]);

    $assignedAppointment = Appointment::create([
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'starts_at' => $data['startsAt'],
        'ends_at' => $data['startsAt']->copy()->addMinutes(60),
        'status' => 'pending',
        'source' => 'clinic',
        'patient_name' => 'Atanmış Şube Hastası',
        'patient_phone' => '05550000001',
        'patient_email' => 'assigned@example.com',
    ]);

    $unassignedAppointment = Appointment::create([
        'business_id' => $data['business']->id,
        'branch_id' => $unassignedBranch->id,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'starts_at' => $data['startsAt']->copy()->addHours(2),
        'ends_at' => $data['startsAt']->copy()->addHours(3),
        'status' => 'pending',
        'source' => 'clinic',
        'patient_name' => 'Yetkisiz Şube Hastası',
        'patient_phone' => '05550000002',
        'patient_email' => 'unassigned@example.com',
    ]);

    $response = $this->actingAs($data['user'])
        ->getJson('/api/appointments?' . http_build_query([
            'business_id' => $data['business']->id,
            'from' => $data['startsAt']->toDateString(),
            'to' => $data['startsAt']->toDateString(),
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('success', true);

    $ids = collect($response->json('data.data'))
        ->pluck('id');

    expect($ids)
        ->toContain($assignedAppointment->id)
        ->not->toContain($unassignedAppointment->id);
});

test('calendar appointments return complete appointment information', function () {
    $data = createAppointmentTestData();

    $appointment = Appointment::create([
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'starts_at' => $data['startsAt'],
        'ends_at' => $data['startsAt']->copy()->addMinutes(60),
        'status' => 'confirmed',
        'source' => 'clinic',
        'patient_name' => 'Takvim Hastası',
        'patient_phone' => '05550000000',
        'patient_email' => 'calendar@example.com',
        'notes' => 'Takvim test notu.',
    ]);

    $response = $this->actingAs($data['user'])
        ->getJson('/api/appointments?' . http_build_query([
            'branch_id' => $data['branch']->id,
            'from' => $data['startsAt']->toDateString(),
            'to' => $data['startsAt']->toDateString(),
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('success', true);

    $appointmentData = collect(
        $response->json('data.data')
    )->firstWhere('id', $appointment->id);

    expect($appointmentData)->not->toBeNull();

    expect($appointmentData)
        ->toHaveKeys([
            'id',
            'business_id',
            'branch_id',
            'doctor_id',
            'treatment_id',
            'starts_at',
            'ends_at',
            'status',
            'source',
            'patient_name',
            'patient_phone',
            'patient_email',
            'notes',
            'business',
            'branch',
            'patient_profile',
            'doctor',
            'treatment',
            'status_histories',
        ]);

    expect($appointmentData['status'])
        ->toBe('confirmed');

    expect($appointmentData['patient_name'])
        ->toBe('Takvim Hastası');

    expect($appointmentData['notes'])
        ->toBe('Takvim test notu.');
});

test('business user can only see their own appointment filter options', function () {
    $data = createAppointmentTestData();

    $otherBusiness = Business::factory()->create([
        'name' => 'Diğer İşletme',
    ]);

    $otherBranch = Branch::create([
        'business_id' => $otherBusiness->id,
        'name' => 'Diğer İşletme Şubesi',
        'slug' => 'diger-isletme-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Besiktas',
        'status' => 'active',
    ]);

    $response = $this->actingAs($data['user'])
        ->getJson('/api/appointments/options?' . http_build_query([
            'business_id' => $data['business']->id,
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('success', true);

    $branches = collect(
        $response->json('data.branches')
    );

    $doctors = collect(
        $response->json('data.doctors')
    );

    $treatments = collect(
        $response->json('data.treatments')
    );

    expect($branches->pluck('id'))
        ->toContain($data['branch']->id)
        ->not->toContain($otherBranch->id);

    expect($doctors->pluck('id'))
        ->toContain($data['doctor']->id);

    expect($treatments->pluck('id'))
        ->toContain($data['treatment']->id);

    expect($response->json('data.statuses'))
        ->toBe([
            'pending',
            'confirmed',
            'cancelled',
            'completed',
            'rescheduled',
            'no_show',
        ]);
});

test('staff can only see appointment filter options for assigned active branches', function () {
    $data = createAppointmentTestData();

    $businessUser = BusinessUser::where('user_id', $data['user']->id)
        ->where('business_id', $data['business']->id)
        ->firstOrFail();

    $businessUser->update([
        'role' => 'staff',
    ]);

    DB::table('business_user_branch')->insert([
        'business_user_id' => $businessUser->id,
        'branch_id' => $data['branch']->id,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherBranch = Branch::create([
        'business_id' => $data['business']->id,
        'name' => 'Staff Yetkisiz Şube',
        'slug' => 'staff-yetkisiz-sube-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Besiktas',
        'status' => 'active',
    ]);

    $otherPerson = Person::create([
        'business_id' => $data['business']->id,
        'branch_id' => $otherBranch->id,
        'first_name' => 'Yetkisiz',
        'last_name' => 'Doktor',
        'title' => 'Dr.',
        'job_title' => 'Doktor',
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
    ]);

    $otherDoctor = Doctor::create([
        'person_id' => $otherPerson->id,
        'license_number' => 'TEST-' . uniqid(),
        'specialty' => 'Test Uzmanlığı',
        'status' => 'active',
        'is_public' => true,
    ]);

    DB::table('doctor_branch')->insert([
        'doctor_id' => $otherDoctor->id,
        'branch_id' => $otherBranch->id,
        'status' => 'active',
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherTreatmentCategory = TreatmentCategory::create([
        'name' => 'Yetkisiz Kategori',
        'slug' => 'yetkisiz-kategori-' . uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $otherTreatment = Treatment::create([
        'treatment_category_id' => $otherTreatmentCategory->id,
        'name' => 'Yetkisiz Tedavi',
        'slug' => 'yetkisiz-tedavi-' . uniqid(),
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_offer_enabled' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    DB::table('branch_treatment')->insert([
        'branch_id' => $otherBranch->id,
        'treatment_id' => $otherTreatment->id,
        'duration_minutes' => 60,
        'is_online_bookable' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($data['user'])
        ->getJson('/api/appointments/options?' . http_build_query([
            'business_id' => $data['business']->id,
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('success', true);

    $branches = collect(
        $response->json('data.branches')
    );

    $doctors = collect(
        $response->json('data.doctors')
    );

    $treatments = collect(
        $response->json('data.treatments')
    );

    expect($branches->pluck('id'))
        ->toContain($data['branch']->id)
        ->not->toContain($otherBranch->id);

    expect($doctors->pluck('id'))
        ->toContain($data['doctor']->id)
        ->not->toContain($otherDoctor->id);

    expect($treatments->pluck('id'))
        ->toContain($data['treatment']->id)
        ->not->toContain($otherTreatment->id);
});

test('appointment cannot be created outside doctor working hours', function () {
    $data = createAppointmentTestData();

    grantAppointmentPermission(
        $data['user'],
        $data['business'],
        'appointments.create'
    );

    $outsideWorkingHours = $data['startsAt']
        ->copy()
        ->setTime(19, 0, 0);

    $response = $this->actingAs($data['user'])
        ->postJson('/api/appointments', [
            'business_id' => $data['business']->id,
            'branch_id' => $data['branch']->id,
            'patient_profile_id' => null,
            'doctor_id' => $data['doctor']->id,
            'treatment_id' => $data['treatment']->id,
            'starts_at' => $outsideWorkingHours->toDateTimeString(),
            'status' => 'pending',
            'source' => 'medloby',
            'patient_name' => 'Çalışma Saati Testi',
            'patient_phone' => '05550000000',
            'patient_email' => 'working-hours@example.com',
        ]);

    expect($response->status())
        ->toBeIn([422, 400]);

    $this->assertDatabaseMissing('appointments', [
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'patient_email' => 'working-hours@example.com',
    ]);
});

test('appointment cannot be created while doctor is on approved leave', function () {
    $data = createAppointmentTestData();

    grantAppointmentPermission(
        $data['user'],
        $data['business'],
        'appointments.create'
    );

    $leaveDate = $data['startsAt']->copy()->addDay();

    DoctorLeave::create([
        'doctor_id' => $data['doctor']->id,
        'branch_id' => $data['branch']->id,
        'start_date' => $leaveDate->toDateString(),
        'end_date' => $leaveDate->toDateString(),
        'is_approved' => true,
    ]);

    $appointmentStart = $leaveDate->copy()->setTime(10, 0, 0);

    $response = $this->actingAs($data['user'])
        ->postJson('/api/appointments', [
            'business_id' => $data['business']->id,
            'branch_id' => $data['branch']->id,
            'patient_profile_id' => null,
            'doctor_id' => $data['doctor']->id,
            'treatment_id' => $data['treatment']->id,
            'starts_at' => $appointmentStart->toDateTimeString(),
            'status' => 'pending',
            'source' => 'medloby',
            'patient_name' => 'İzin Testi',
            'patient_phone' => '05550000000',
            'patient_email' => 'leave-test@example.com',
        ]);

    expect($response->status())
        ->toBeIn([422, 400]);

    $this->assertDatabaseMissing('appointments', [
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'patient_email' => 'leave-test@example.com',
    ]);
});

test('appointment cannot be created during an active calendar block', function () {
    $data = createAppointmentTestData();

    grantAppointmentPermission(
        $data['user'],
        $data['business'],
        'appointments.create'
    );

    $blockStart = $data['startsAt']->copy()->setTime(14, 0, 0);
    $blockEnd = $blockStart->copy()->addHours(2);

    CalendarBlock::create([
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'doctor_id' => $data['doctor']->id,
        'title' => 'Özel Takvim Bloğu',
        'starts_at' => $blockStart,
        'ends_at' => $blockEnd,
        'is_active' => true,
    ]);

    $response = $this->actingAs($data['user'])
        ->postJson('/api/appointments', [
            'business_id' => $data['business']->id,
            'branch_id' => $data['branch']->id,
            'patient_profile_id' => null,
            'doctor_id' => $data['doctor']->id,
            'treatment_id' => $data['treatment']->id,
            'starts_at' => $blockStart->toDateTimeString(),
            'status' => 'pending',
            'source' => 'medloby',
            'patient_name' => 'Takvim Blok Testi',
            'patient_phone' => '05550000000',
            'patient_email' => 'calendar-block@example.com',
        ]);

    expect($response->status())
        ->toBeIn([422, 400]);

    $this->assertDatabaseMissing('appointments', [
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'patient_email' => 'calendar-block@example.com',
    ]);
});

test('appointment cannot be created when branch does not offer treatment', function () {
    $data = createAppointmentTestData();

    grantAppointmentPermission(
        $data['user'],
        $data['business'],
        'appointments.create'
    );

    DB::table('branch_treatment')
        ->where('branch_id', $data['branch']->id)
        ->where('treatment_id', $data['treatment']->id)
        ->update([
            'is_active' => false,
        ]);

    $appointmentStart = $data['startsAt']->copy();

    $response = $this->actingAs($data['user'])
        ->postJson('/api/appointments', [
            'business_id' => $data['business']->id,
            'branch_id' => $data['branch']->id,
            'patient_profile_id' => null,
            'doctor_id' => $data['doctor']->id,
            'treatment_id' => $data['treatment']->id,
            'starts_at' => $appointmentStart->toDateTimeString(),
            'status' => 'pending',
            'source' => 'medloby',
            'patient_name' => 'Şube Tedavi Testi',
            'patient_phone' => '05550000000',
            'patient_email' => 'branch-treatment@example.com',
        ]);

    expect($response->status())
        ->toBeIn([400, 422]);

    $this->assertDatabaseMissing('appointments', [
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'patient_email' => 'branch-treatment@example.com',
    ]);
});

test('appointment cannot be created when doctor does not perform treatment', function () {
    $data = createAppointmentTestData();

    grantAppointmentPermission(
        $data['user'],
        $data['business'],
        'appointments.create'
    );

    DB::table('doctor_treatment')
        ->where('doctor_id', $data['doctor']->id)
        ->where('treatment_id', $data['treatment']->id)
        ->update([
            'is_active' => false,
        ]);

    $appointmentStart = $data['startsAt']->copy();

    $response = $this->actingAs($data['user'])
        ->postJson('/api/appointments', [
            'business_id' => $data['business']->id,
            'branch_id' => $data['branch']->id,
            'patient_profile_id' => null,
            'doctor_id' => $data['doctor']->id,
            'treatment_id' => $data['treatment']->id,
            'starts_at' => $appointmentStart->toDateTimeString(),
            'status' => 'pending',
            'source' => 'medloby',
            'patient_name' => 'Doktor Tedavi Testi',
            'patient_phone' => '05550000000',
            'patient_email' => 'doctor-treatment@example.com',
        ]);

    expect($response->status())
        ->toBeIn([400, 422]);

    $this->assertDatabaseMissing('appointments', [
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'patient_email' => 'doctor-treatment@example.com',
    ]);
});

test('online appointment cannot be created when treatment is not online bookable', function () {
    $data = createAppointmentTestData();

    grantAppointmentPermission(
        $data['user'],
        $data['business'],
        'appointments.create'
    );

    DB::table('branch_treatment')
        ->where('branch_id', $data['branch']->id)
        ->where('treatment_id', $data['treatment']->id)
        ->update([
            'is_online_bookable' => false,
        ]);

    $response = $this->actingAs($data['user'])
        ->postJson('/api/appointments', [
            'business_id' => $data['business']->id,
            'branch_id' => $data['branch']->id,
            'patient_profile_id' => null,
            'doctor_id' => $data['doctor']->id,
            'treatment_id' => $data['treatment']->id,
            'starts_at' => $data['startsAt']->toDateTimeString(),
            'status' => 'pending',
            'source' => 'medloby',
            'require_online_bookable' => true,
            'patient_name' => 'Online Randevu Testi',
            'patient_phone' => '05550000000',
            'patient_email' => 'online-booking@example.com',
        ]);

    expect($response->status())
        ->toBeIn([400, 422]);

    $this->assertDatabaseMissing('appointments', [
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'doctor_id' => $data['doctor']->id,
        'treatment_id' => $data['treatment']->id,
        'patient_email' => 'online-booking@example.com',
    ]);
});