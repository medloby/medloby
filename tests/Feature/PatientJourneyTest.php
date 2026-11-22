<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the patient journey orders moments from existing records and opens the current appointment after a move', function () {
    $user = User::factory()->create();
    $patient = $user->patientProfile()->create(['first_name' => 'Örnek', 'last_name' => 'Hasta']);
    $clinic = Business::factory()->create();
    $branch = Branch::factory()->for($clinic)->create();
    $owner = User::factory()->create();
    $this->travelTo(CarbonImmutable::parse('2026-11-15 09:00:00'));
    $conversation = $patient->conversations()->create([
        'business_id' => $clinic->id, 'branch_id' => $branch->id, 'subject' => 'Paket görüşmesi',
    ]);
    $this->travel(1)->hour();
    $offer = $patient->offers()->create([
        'conversation_id' => $conversation->id, 'business_id' => $clinic->id, 'branch_id' => $branch->id,
        'created_by' => $owner->id, 'title' => 'İki seanslık paket', 'amount' => 600, 'package_snapshot' => ['treatments' => []],
        'status' => 'accepted', 'responded_at' => now()->addHour(), 'description' => 'Yalnızca teklif ayrıntısı',
    ]);
    $this->travel(2)->hours();
    $fields = ['business_id' => $clinic->id, 'branch_id' => $branch->id, 'offer_id' => $offer->id];
    $original = $patient->appointments()->create($fields + [
        'starts_at' => '2026-11-26 09:00:00', 'ends_at' => '2026-11-26 09:30:00', 'status' => 'rescheduled',
        'patient_phone' => '05550000011', 'notes' => 'Randevuya özel not',
    ]);
    $original->statusHistories()->create(['new_status' => 'pending', 'changed_at' => now()]);
    $this->travel(1)->hour();
    $original->statusHistories()->create([
        'old_status' => 'pending', 'new_status' => 'confirmed', 'changed_at' => now(), 'notes' => 'Klinik içi not',
    ]);
    $this->travel(1)->hour();
    $original->statusHistories()->create(['old_status' => 'confirmed', 'new_status' => 'rescheduled', 'changed_at' => now()]);
    $moved = $patient->appointments()->create($fields + [
        'starts_at' => '2026-11-27 10:00:00', 'ends_at' => '2026-11-27 10:30:00', 'status' => 'confirmed',
        'rescheduled_from_appointment_id' => $original->id,
    ]);
    $moved->statusHistories()->create(['new_status' => 'confirmed', 'changed_at' => now()]);

    $path = '/api/patient/journey';
    $items = $this->actingAs($user, 'sanctum')->getJson($path)
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.has_more', false)
        ->assertDontSee('05550000011')->assertDontSee('Randevuya özel not', false)
        ->assertDontSee('Klinik içi not', false)->assertDontSee('Yalnızca teklif ayrıntısı', false)
        ->json('data.events');
    expect(array_column($items, 'kind'))->toBe([
        'appointment_moved', 'appointment_status', 'appointment_booked', 'offer_responded', 'offer_received', 'conversation',
    ]);
    foreach ($items as $item) {
        expect(array_keys($item))->toBe(['kind', 'title', 'business_name', 'status', 'occurred_at', 'starts_at', 'target_id', 'conversation_id']);
    }
    expect($items[0]['target_id'])->toBe($moved->id);
    expect($items[0]['starts_at'])->toBe('2026-11-27 10:00:00');
    expect($items[1]['status'])->toBe('confirmed');
    expect($items[3]['conversation_id'])->toBe($conversation->id);
    expect($items[3]['target_id'])->toBe($offer->id);
    expect($items[3]['occurred_at'])->toBe('2026-11-15T11:00:00.000000Z');
    expect($items[5]['business_name'])->toBe($clinic->name);
    $this->getJson($path.'?category=offer')->assertOk()->assertJsonCount(2, 'data.events')
        ->assertJsonPath('data.events.0.kind', 'offer_responded');
    $this->getJson($path.'?category=appointment')->assertOk()->assertJsonCount(3, 'data.events');
    $this->getJson('/api/patient/appointments/'.$original->id)->assertOk()->assertJsonPath('data.id', $moved->id);
});

test('journey items are restricted to the verified patients own records and legacy offers link to their conversation', function () {
    $user = User::factory()->create();
    $patient = $user->patientProfile()->create(['first_name' => 'Birinci', 'last_name' => 'Hasta']);
    $other = User::factory()->create()->patientProfile()->create(['first_name' => 'İkinci', 'last_name' => 'Hasta']);
    $branch = Branch::factory()->create();
    $fields = ['business_id' => $branch->business_id, 'branch_id' => $branch->id];
    $conversation = $patient->conversations()->create($fields + ['subject' => 'Kendi görüşmem']);
    $foreign = $other->conversations()->create($fields + ['subject' => 'Başka görüşme']);
    $offerFields = $fields + ['created_by' => User::factory()->create()->id, 'amount' => 200, 'status' => 'pending'];
    $patient->offers()->create($offerFields + ['conversation_id' => $conversation->id, 'title' => 'Paket dışı teklif']);
    $patient->offers()->create($offerFields + ['conversation_id' => $foreign->id, 'title' => 'Tutarsız teklif']);
    $other->appointments()->create($fields + ['starts_at' => '2026-11-26 13:00:00', 'ends_at' => '2026-11-26 13:30:00']);

    $path = '/api/patient/journey';
    $this->getJson($path)->assertUnauthorized();
    $this->actingAs(User::factory()->create(['is_admin' => true]), 'sanctum')->getJson($path)->assertForbidden();
    $unverified = User::factory()->unverified()->create();
    $unverified->patientProfile()->create(['first_name' => 'Doğrulanmamış', 'last_name' => 'Hasta']);
    $this->actingAs($unverified, 'sanctum')->getJson($path)->assertForbidden();
    $this->actingAs($user, 'sanctum')->getJson($path)->assertOk()->assertJsonCount(2, 'data.events');
    $this->getJson($path.'?category=offer')->assertOk()->assertJsonCount(1, 'data.events')
        ->assertJsonPath('data.events.0.title', 'Paket dışı teklif')
        ->assertJsonPath('data.events.0.target_id', null)
        ->assertJsonPath('data.events.0.conversation_id', $conversation->id);
});

test('the timeline validates its filters and can page through equally dated and undated records', function () {
    $user = User::factory()->create();
    $patient = $user->patientProfile()->create(['first_name' => 'Örnek', 'last_name' => 'Hasta']);
    $clinic = Business::factory()->create();
    $this->freezeSecond();
    $older = $patient->conversations()->create(['business_id' => $clinic->id, 'subject' => 'Tarihsiz eski kayıt']);
    $older->timestamps = false;
    $older->forceFill(['created_at' => null, 'updated_at' => null])->save();
    foreach (range(1, 20) as $number) {
        $patient->conversations()->create(['business_id' => $clinic->id, 'subject' => 'Kayıt '.$number]);
    }
    $path = '/api/patient/journey';
    $this->actingAs($user, 'sanctum')->getJson($path.'?category=invalid&page=0')
        ->assertUnprocessable()->assertJsonValidationErrors(['category', 'page']);
    $this->getJson($path.'?category=conversation')->assertOk()->assertJsonCount(20, 'data.events')
        ->assertJsonPath('data.has_more', true)->assertJsonPath('data.events.0.title', 'Kayıt 20');
    $this->getJson($path.'?category=conversation&page=2')->assertOk()->assertJsonCount(1, 'data.events')
        ->assertJsonPath('data.has_more', false)->assertJsonPath('data.current_page', 2)
        ->assertJsonPath('data.events.0.occurred_at', null)->assertJsonPath('data.events.0.target_id', $older->id);
});
