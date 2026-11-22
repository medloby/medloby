<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\OfferTravelPlan;
use App\Models\User;
use App\Notifications\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function travelPlanFixture(array $included = ['includes_hotel' => true, 'includes_transfer' => true], bool $branchScoped = true): array
{
    $business = Business::factory()->create();
    $branch = Branch::factory()->for($business)->create();
    $owner = User::factory()->create();
    $business->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $user = User::factory()->create();
    $patient = $user->patientProfile()->create(['first_name' => 'Deneme', 'last_name' => 'Hasta']);
    $context = ['business_id' => $business->id, 'branch_id' => $branchScoped ? $branch->id : null];
    $conversation = $patient->conversations()->create($context + ['subject' => 'Örnek paket']);
    $offer = $patient->offers()->create($context + [
        'conversation_id' => $conversation->id, 'created_by' => $owner->id,
        'title' => 'Örnek paket', 'amount' => 900, 'status' => 'accepted',
        'package_snapshot' => $included + ['branch_id' => $context['branch_id']],
    ]);

    return [$branch, $owner, $user, $offer];
}

test('accepted package hotel and transfers can be saved once per revision and read by the patient', function () {
    [$branch, $owner, $patient, $offer] = travelPlanFixture();
    $url = "/api/offers/{$offer->id}/travel-plan";
    $hotel = ['name' => 'Örnek Tesis', 'address' => 'Örnek adres', 'check_in' => '2026-12-02', 'check_out' => '2026-12-05'];
    $incoming = ['starts_at' => '2026-12-02T09:40', 'from' => 'Terminal A', 'to' => 'Örnek Tesis'];
    $outgoing = ['starts_at' => '2026-12-05T17:30', 'from' => 'Örnek Tesis', 'to' => 'Terminal A'];
    $plan = ['revision' => 0, 'hotel' => $hotel, 'transfers' => [$outgoing, $incoming]];

    $this->actingAs($patient, 'sanctum')->getJson($url)->assertOk()
        ->assertJsonPath('data.can_manage', false)->assertJsonPath('data.revision', 0);
    $this->putJson($url, $plan)->assertForbidden();
    $this->actingAs($owner, 'sanctum')->putJson($url, $plan)->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('data.revision', 1)
        ->assertJsonPath('data.hotel', $hotel)->assertJsonPath('data.transfers', [$incoming, $outgoing]);
    $this->putJson($url, $plan)->assertOk()->assertJsonPath('data.revision', 1);
    $this->putJson($url, array_replace($plan, ['transfers' => [$incoming]]))->assertConflict();
    $this->assertDatabaseHas('offer_travel_plans', ['offer_id' => $offer->id, 'revision' => 1]);
    $this->assertDatabaseCount('offer_travel_plans', 1);
    expect($patient->notifications()->sole()->data)->toBe([
        'notification_type' => NotificationType::OFFER_TRAVEL_PLAN_UPDATED,
        'title' => 'Seyahat planınız güncellendi',
        'message' => 'Güncel bilgileri kabul ettiğiniz tekliften açabilirsiniz.',
        'conversation_id' => $offer->conversation_id, 'offer_id' => $offer->id,
    ]);

    $branch->update(['status' => 'inactive']);
    $this->putJson($url, $plan)->assertForbidden();
    $this->actingAs($patient, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('data.can_manage', false)
        ->assertJsonPath('data.hotel.name', 'Örnek Tesis')->assertJsonPath('data.transfers.1.starts_at', '2026-12-05T17:30');
});

test('the travel permission applies only to the assigned branch and can be revoked', function () {
    [$branch, $owner, , $offer] = travelPlanFixture(['includes_transfer' => true]);
    $another = Branch::factory()->for($branch->business)->create();
    $staff = User::factory()->create();
    $member = $branch->business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true]);
    $member->branches()->attach($branch, ['is_active' => true]);
    $member->branches()->attach($another, ['is_active' => true, 'permission_names' => ['travel.manage']]);
    $url = "/api/offers/{$offer->id}/travel-plan";
    $permissionUrl = "/api/branches/{$branch->id}/operations/staff/{$member->id}/permissions";
    $payload = ['revision' => 1, 'transfers' => [['starts_at' => '2026-12-02T09:40', 'from' => 'Terminal A', 'to' => 'Tesis']]];
    OfferTravelPlan::factory()->for($offer)->create();

    $this->getJson($url)->assertUnauthorized();
    $this->actingAs($staff, 'sanctum')->getJson($url)->assertNotFound();
    $this->putJson($url, $payload)->assertForbidden();
    $this->actingAs($owner, 'sanctum')->putJson($permissionUrl, ['permissions' => ['travel.manage']])->assertOk();
    $this->actingAs($staff, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('data.can_manage', true);
    $this->putJson($url, $payload)->assertOk()->assertJsonPath('data.revision', 2);
    $this->actingAs($owner, 'sanctum')->putJson($permissionUrl, ['permissions' => []])->assertOk();
    $this->actingAs($staff, 'sanctum')->getJson($url)->assertNotFound();
    $this->actingAs(User::factory()->create(['is_admin' => true]), 'sanctum')->getJson($url)->assertNotFound();
});

test('only accepted travel inclusions can be updated and lodging dates must be in order', function () {
    [, $owner, , $offer] = travelPlanFixture(['includes_hotel' => true]);
    $url = "/api/offers/{$offer->id}/travel-plan";
    $hotel = ['name' => 'Örnek Tesis', 'check_in' => '2026-12-05', 'check_out' => '2026-12-02'];
    $this->actingAs($owner, 'sanctum')->putJson($url, ['revision' => 0, 'hotel' => $hotel])
        ->assertUnprocessable()->assertJsonValidationErrors('hotel.check_out');
    $this->putJson($url, ['revision' => 0, 'transfers' => [['starts_at' => '2026-12-02T13:20', 'from' => 'Lobi', 'to' => 'Klinik']]])
        ->assertUnprocessable()->assertJsonValidationErrors('transfers');
    $offer->update(['status' => 'pending']);
    $this->putJson($url, ['revision' => 0])->assertNotFound();
    $offer->update(['status' => 'accepted', 'package_snapshot' => ['includes_hotel' => false]]);
    $this->getJson($url)->assertNotFound();
    $this->assertDatabaseCount('offer_travel_plans', 0);
});

test('the owner handles a clinic-wide travel plan for a package without a branch', function () {
    [$branch, $owner, $patient, $offer] = travelPlanFixture(['includes_hotel' => true], branchScoped: false);
    $staff = User::factory()->create();
    $branch->business->memberships()->create(['user_id' => $staff->id, 'role' => 'staff', 'is_active' => true])
        ->branches()->attach($branch, ['is_active' => true, 'permission_names' => ['travel.manage']]);
    $url = "/api/offers/{$offer->id}/travel-plan";
    $hotel = ['name' => 'Örnek Tesis', 'check_in' => '2026-12-02', 'check_out' => '2026-12-03'];
    $this->actingAs($staff, 'sanctum')->putJson($url, ['revision' => 0, 'hotel' => $hotel])->assertForbidden();
    $this->actingAs($owner, 'sanctum')->putJson($url, ['revision' => 0, 'hotel' => $hotel])
        ->assertOk()->assertJsonPath('data.hotel.name', 'Örnek Tesis');
    $this->actingAs($patient, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('data.hotel.name', 'Örnek Tesis')
        ->assertJsonPath('data.transfer_included', false);
});
