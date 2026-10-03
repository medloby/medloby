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

    BusinessUser::create([
        'business_id' => $businessA->id,
        'user_id' => $userA->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    BusinessUser::create([
        'business_id' => $businessB->id,
        'user_id' => $userB->id,
        'role' => 'owner',
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

test('business user can view appointment belonging to their business', function () {
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

    $data['userA']
        ->businessMemberships()
        ->where('business_id', $data['businessA']->id)
        ->update([
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

test('business user appointment list contains only appointments from accessible businesses', function () {
    $data = createAccessBusinessData();

    $appointmentA = createAccessAppointment(
        $data['businessA'],
        $data['branchA']
    );

    $appointmentB = createAccessAppointment(
        $data['businessB'],
        $data['branchB']
    );

    $response = $this
        ->actingAs($data['userA'])
        ->getJson('/api/appointments');

    $response->assertOk();

    $response->assertJsonFragment([
        'id' => $appointmentA->id,
    ]);

    $response->assertJsonMissing([
        'id' => $appointmentB->id,
    ]);
});