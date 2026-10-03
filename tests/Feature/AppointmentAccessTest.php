<?php

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAccessBusinessData(): array
{
    $businessA = Business::factory()->create([
        'name' => 'Test İşletme A',
    ]);

    $businessB = Business::factory()->create([
        'name' => 'Test İşletme B',
    ]);

    $userA = User::factory()->create([
        'name' => 'İşletme A Personeli',
    ]);

    $userB = User::factory()->create([
        'name' => 'İşletme B Personeli',
    ]);

    $membershipA = BusinessUser::create([
        'business_id' => $businessA->id,
        'user_id' => $userA->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);

    $membershipB = BusinessUser::create([
        'business_id' => $businessB->id,
        'user_id' => $userB->id,
        'role' => 'business_owner',
        'is_active' => true,
    ]);

    $branchA = Branch::create([
        'business_id' => $businessA->id,
        'name' => 'Test Şube A',
        'slug' => 'test-sube-a-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $branchB = Branch::create([
        'business_id' => $businessB->id,
        'name' => 'Test Şube B',
        'slug' => 'test-sube-b-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Besiktas',
        'status' => 'active',
    ]);

    return [
        'businessA' => $businessA,
        'businessB' => $businessB,
        'membershipA' => $membershipA,
        'membershipB' => $membershipB,
        'branchA' => $branchA,
        'branchB' => $branchB,
        'userA' => $userA,
        'userB' => $userB,
    ];
}

function createAccessAppointment(
    Business $business,
    Branch $branch
): Appointment {
    return Appointment::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => null,
        'doctor_id' => null,
        'treatment_id' => null,
        'starts_at' => now()->addDays(7)->setTime(10, 0),
        'ends_at' => now()->addDays(7)->setTime(11, 0),
        'status' => 'pending',
        'source' => 'clinic',
        'patient_name' => 'Erişim Test Hastası',
        'patient_phone' => '05550000000',
        'patient_email' => 'test@example.com',
        'notes' => 'Erişim testi',
    ]);
}

test('business owner can view appointment belonging to their business', function () {
    $data = createAccessBusinessData();

    $appointment = createAccessAppointment(
        $data['businessA'],
        $data['branchA']
    );

    $response = $this
        ->actingAs($data['userA'])
        ->getJson("/api/appointments/{$appointment->id}");

    $response->assertOk();

    $response->assertJsonPath(
        'data.id',
        $appointment->id
    );
});

test('business user cannot view appointment belonging to another business', function () {
    $data = createAccessBusinessData();

    $appointment = createAccessAppointment(
        $data['businessB'],
        $data['branchB']
    );

    $response = $this
        ->actingAs($data['userA'])
        ->getJson("/api/appointments/{$appointment->id}");

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu randevuyu görüntüleme yetkiniz yok.',
    ]);
});

test('inactive business membership cannot view business appointment', function () {
    $data = createAccessBusinessData();

    $appointment = createAccessAppointment(
        $data['businessA'],
        $data['branchA']
    );

    $data['membershipA']->update([
        'is_active' => false,
    ]);

    $response = $this
        ->actingAs($data['userA'])
        ->getJson("/api/appointments/{$appointment->id}");

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu randevuyu görüntüleme yetkiniz yok.',
    ]);
});

test('business user cannot access another business appointment by direct appointment id', function () {
    $data = createAccessBusinessData();

    $appointment = createAccessAppointment(
        $data['businessB'],
        $data['branchB']
    );

    $response = $this
        ->actingAs($data['userA'])
        ->getJson("/api/appointments/{$appointment->id}");

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu randevuyu görüntüleme yetkiniz yok.',
    ]);
});

test('business owner appointment list contains appointments from all branches of their business', function () {
    $data = createAccessBusinessData();

    $branchA2 = Branch::create([
        'business_id' => $data['businessA']->id,
        'name' => 'Test Şube A2',
        'slug' => 'test-sube-a2-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Sisli',
        'status' => 'active',
    ]);

    $appointmentA = createAccessAppointment(
        $data['businessA'],
        $data['branchA']
    );

    $appointmentA2 = createAccessAppointment(
        $data['businessA'],
        $branchA2
    );

    $appointmentB = createAccessAppointment(
        $data['businessB'],
        $data['branchB']
    );

    $response = $this
        ->actingAs($data['userA'])
        ->getJson('/api/appointments');

    $response->assertOk();

    $appointmentIds = collect(
        $response->json('data.data')
    )->pluck('id');

    expect($appointmentIds)
        ->toContain($appointmentA->id)
        ->toContain($appointmentA2->id)
        ->not->toContain($appointmentB->id);
});

test('staff can view appointment from assigned branch', function () {
    $data = createAccessBusinessData();

    $staff = User::factory()->create([
        'name' => 'Şube Personeli',
    ]);

    $membership = BusinessUser::create([
        'business_id' => $data['businessA']->id,
        'user_id' => $staff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $membership->branches()->attach(
        $data['branchA']->id,
        [
            'is_active' => true,
        ]
    );

    $appointment = createAccessAppointment(
        $data['businessA'],
        $data['branchA']
    );

    $response = $this
        ->actingAs($staff)
        ->getJson("/api/appointments/{$appointment->id}");

    $response->assertOk();

    $response->assertJsonPath(
        'data.id',
        $appointment->id
    );
});

test('staff cannot view appointment from unassigned branch', function () {
    $data = createAccessBusinessData();

    $staff = User::factory()->create([
        'name' => 'Sadece A Şubesi Personeli',
    ]);

    $membership = BusinessUser::create([
        'business_id' => $data['businessA']->id,
        'user_id' => $staff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $branchA2 = Branch::create([
        'business_id' => $data['businessA']->id,
        'name' => 'Test Şube A2',
        'slug' => 'test-sube-a2-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Sisli',
        'status' => 'active',
    ]);

    $membership->branches()->attach(
        $data['branchA']->id,
        [
            'is_active' => true,
        ]
    );

    $appointment = createAccessAppointment(
        $data['businessA'],
        $branchA2
    );

    $response = $this
        ->actingAs($staff)
        ->getJson("/api/appointments/{$appointment->id}");

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu randevuyu görüntüleme yetkiniz yok.',
    ]);
});

test('staff cannot view appointment from inactive assigned branch', function () {
    $data = createAccessBusinessData();

    $staff = User::factory()->create([
        'name' => 'Pasif Şube Yetkili Personeli',
    ]);

    $membership = BusinessUser::create([
        'business_id' => $data['businessA']->id,
        'user_id' => $staff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $membership->branches()->attach(
        $data['branchA']->id,
        [
            'is_active' => false,
        ]
    );

    $appointment = createAccessAppointment(
        $data['businessA'],
        $data['branchA']
    );

    $response = $this
        ->actingAs($staff)
        ->getJson("/api/appointments/{$appointment->id}");

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu randevuyu görüntüleme yetkiniz yok.',
    ]);
});

test('staff appointment list contains only appointments from assigned branches', function () {
    $data = createAccessBusinessData();

    $staff = User::factory()->create([
        'name' => 'Liste Personeli',
    ]);

    $membership = BusinessUser::create([
        'business_id' => $data['businessA']->id,
        'user_id' => $staff->id,
        'role' => 'staff',
        'is_active' => true,
    ]);

    $branchA2 = Branch::create([
        'business_id' => $data['businessA']->id,
        'name' => 'Test Şube A2',
        'slug' => 'test-sube-a2-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Sisli',
        'status' => 'active',
    ]);

    $membership->branches()->attach(
        $data['branchA']->id,
        [
            'is_active' => true,
        ]
    );

    $assignedAppointment = createAccessAppointment(
        $data['businessA'],
        $data['branchA']
    );

    $unassignedAppointment = createAccessAppointment(
        $data['businessA'],
        $branchA2
    );

    $otherBusinessAppointment = createAccessAppointment(
        $data['businessB'],
        $data['branchB']
    );

    $response = $this
        ->actingAs($staff)
        ->getJson('/api/appointments');

    $response->assertOk();

    $response->assertJsonFragment([
        'id' => $assignedAppointment->id,
    ]);

    $response->assertJsonMissing([
        'id' => $unassignedAppointment->id,
    ]);

    $response->assertJsonMissing([
        'id' => $otherBusinessAppointment->id,
    ]);
});

test('manager can view appointment from assigned branch', function () {
    $data = createAccessBusinessData();

    $manager = User::factory()->create([
        'name' => 'Şube Müdürü',
    ]);

    $membership = BusinessUser::create([
        'business_id' => $data['businessA']->id,
        'user_id' => $manager->id,
        'role' => 'manager',
        'is_active' => true,
    ]);

    $membership->branches()->attach(
        $data['branchA']->id,
        [
            'is_active' => true,
        ]
    );

    $appointment = createAccessAppointment(
        $data['businessA'],
        $data['branchA']
    );

    $response = $this
        ->actingAs($manager)
        ->getJson("/api/appointments/{$appointment->id}");

    $response->assertOk();

    $response->assertJsonPath(
        'data.id',
        $appointment->id
    );
});

test('manager cannot view appointment from unassigned branch', function () {
    $data = createAccessBusinessData();

    $manager = User::factory()->create([
        'name' => 'Sadece A Şubesi Müdürü',
    ]);

    $membership = BusinessUser::create([
        'business_id' => $data['businessA']->id,
        'user_id' => $manager->id,
        'role' => 'manager',
        'is_active' => true,
    ]);

    $branchA2 = Branch::create([
        'business_id' => $data['businessA']->id,
        'name' => 'Test Şube A2',
        'slug' => 'test-sube-a2-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Sisli',
        'status' => 'active',
    ]);

    $membership->branches()->attach(
        $data['branchA']->id,
        [
            'is_active' => true,
        ]
    );

    $appointment = createAccessAppointment(
        $data['businessA'],
        $branchA2
    );

    $response = $this
        ->actingAs($manager)
        ->getJson("/api/appointments/{$appointment->id}");

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu randevuyu görüntüleme yetkiniz yok.',
    ]);
});