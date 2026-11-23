<?php

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('administrators can search patient accounts using limited account metadata', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $pending = User::factory()->unverified()->create(['name' => 'Aranan Hasta', 'email' => 'private@example.test']);
    $pending->patientProfile()->create(['first_name' => 'Örnek', 'last_name' => 'Hasta', 'phone' => '5557771234', 'birth_date' => '1990-01-02']);
    $verified = User::factory()->create(['name' => 'Doğrulanmış Hasta']);
    $verified->patientProfile()->create(['first_name' => 'Örnek', 'last_name' => 'Hasta']);
    User::factory()->create(['name' => 'Hastası Olmayan Üye']);
    $path = '/api/admin/patient-accounts';

    $this->getJson($path)->assertUnauthorized();
    $this->actingAs($verified, 'sanctum')->getJson($path)->assertForbidden();
    $this->actingAs($admin, 'sanctum')->getJson($path.'?search=private%40example.test&verification=pending')
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0', [
            'id' => $pending->id, 'name' => 'Aranan Hasta', 'verified' => false, 'profile_status' => 'active',
            'created_at' => $pending->created_at->toIso8601String(),
        ])->assertDontSee('private@example.test')->assertDontSee('5557771234')->assertDontSee('1990-01-02');
    $this->getJson($path.'?search=%23'.$verified->id.'&verification=verified')
        ->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $verified->id);
    $this->getJson($path)->assertOk()->assertJsonCount(2, 'data.data');
});

test('an administrator can open a patient support history without changing the patient inbox visibility', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $patient = User::factory()->create();
    $other = User::factory()->create();
    $patientTicket = SupportTicket::factory()->for($patient, 'requester')->create([
        'created_at' => '2026-11-20 09:00:00', 'closed_at' => '2026-11-20 11:00:00',
    ]);
    $otherTicket = SupportTicket::factory()->for($other, 'requester')->create();
    $patientTicket->messages()->forceCreate([
        'sender_user_id' => $admin->id, 'body' => 'Deneme yanıtı', 'from_support' => true, 'created_at' => '2026-11-20 09:15:00',
    ]);

    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/support-tickets?requester_id='.$patient->id)
        ->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $patientTicket->id)
        ->assertJsonPath('data.data.0.first_response_seconds', 900)->assertJsonPath('data.data.0.resolution_seconds', 7200);
    $this->actingAs($patient, 'sanctum')->getJson('/api/admin/support-tickets?requester_id='.$other->id)->assertForbidden();
    $this->getJson('/api/support-tickets?requester_id='.$other->id)
        ->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $patientTicket->id)
        ->assertJsonMissingPath('data.data.0.first_response_seconds')->assertJsonMissingPath('data.data.0.resolution_seconds');
    $this->getJson('/api/support-tickets/'.$otherTicket->id)->assertNotFound();
});
