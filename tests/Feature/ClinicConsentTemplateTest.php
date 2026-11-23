<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\ConsentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Request;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['medloby.consent_template_preview' => true]));

test('clinical consent drafts require explicit preview and are unavailable in production', function () {
    $clinic = Business::factory()->create();
    $owner = User::factory()->create();
    $clinic->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $url = "/api/businesses/{$clinic->id}/crm/consent-templates";
    $fields = ['title' => 'Sentetik taslak', 'content' => 'Taslak metin'];
    $this->actingAs($owner, 'sanctum');

    config(['medloby.consent_template_preview' => false]);
    $this->getJson($url)->assertNotFound();
    $this->postJson($url, $fields)->assertNotFound();

    config(['medloby.consent_template_preview' => true]);
    $trustedHosts = Request::getTrustedHosts();
    $environment = $this->app->environment();
    $this->app->instance('env', 'production');
    try {
        $this->getJson($url)->assertNotFound();
        $this->postJson($url, $fields)->assertNotFound();
    } finally {
        Request::setTrustedHosts($trustedHosts);
        $this->app->instance('env', $environment);
    }

    $this->assertDatabaseCount('consent_templates', 0);
});

test('an approved clinic owner can draft and edit reusable consent text without a patient record', function () {
    $clinic = Business::factory()->create();
    $other = Business::factory()->create();
    $owner = User::factory()->create();
    $clinic->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $url = "/api/businesses/{$clinic->id}/crm/consent-templates";
    $fields = ['title' => 'Muayene için onam taslağı', 'content' => "Taslak ilk satır.\nİkinci satır."];

    $this->actingAs($owner, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('data.data', []);
    $draft = $this->postJson($url, $fields + [
        'business_id' => $other->id, 'patient_profile_id' => 100, 'status' => 'published', 'revision' => 9,
    ])->assertCreated()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.revision', 1)
        ->assertJsonMissingPath('data.patient_profile_id')
        ->json('data');
    $templateUrl = $url.'/'.$draft['id'];
    $this->assertDatabaseHas('consent_templates', $fields + ['id' => $draft['id'], 'business_id' => $clinic->id, 'revision' => 1]);

    $content = 'Gözden geçirilmiş genel metin.';
    $this->putJson($templateUrl, ['title' => $fields['title'], 'content' => $content, 'revision' => 1])
        ->assertOk()->assertJsonPath('data.revision', 2)->assertJsonPath('data.content', $content);
    $this->getJson($templateUrl)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.content', $content)->assertJsonPath('data.revision', 2);

    $this->putJson($templateUrl, $fields + ['revision' => 1])->assertConflict()
        ->assertJsonPath('message', 'Bu taslak başka bir oturumda değişti. En son kaydı açıp değişikliklerinizi yeniden uygulayın.');
    $this->assertDatabaseHas('consent_templates', ['id' => $draft['id'], 'content' => $content, 'revision' => 2]);
    $this->assertDatabaseCount('consents', 0);
    $this->assertDatabaseCount('medical_records', 0);
});

test('the draft list is paginated by newest entry and stays within the owners clinic', function () {
    $clinic = Business::factory()->create();
    $owner = User::factory()->create();
    $clinic->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $first = ConsentTemplate::factory()->for($clinic)->create();
    $last = ConsentTemplate::factory()->for($clinic)->count(20)->create()->last();
    $other = ConsentTemplate::factory()->create();
    $url = "/api/businesses/{$clinic->id}/crm/consent-templates";

    $this->actingAs($owner, 'sanctum')->getJson($url)->assertOk()
        ->assertJsonCount(20, 'data.data')->assertJsonPath('data.total', 21)
        ->assertJsonPath('data.data.0', ['id' => $last->id, 'title' => $last->title]);
    $this->getJson($url.'?page=2')->assertOk()
        ->assertJsonPath('data.data', [['id' => $first->id, 'title' => $first->title]]);
    $this->getJson($url.'/'.$other->id)->assertNotFound();
    $this->putJson($url.'/'.$other->id, ['title' => 'Başka klinik', 'content' => 'Genel metin.', 'revision' => 1])
        ->assertNotFound();
    expect($other->fresh()->revision)->toBe(1);
});

test('only the verified owner of an approved clinic can use draft endpoints', function (string $actor) {
    $clinic = Business::factory()->create();
    $draft = ConsentTemplate::factory()->for($clinic)->create();
    $user = $actor === 'unverified' ? User::factory()->unverified()->create() : User::factory()->create();

    if ($actor === 'staff') {
        $branch = Branch::factory()->for($clinic)->create();
        $clinic->memberships()->create(['user_id' => $user->id, 'role' => 'staff', 'is_active' => true])
            ->branches()->attach($branch->id, ['is_active' => true, 'permission_names' => ['branches.manage']]);
    } elseif ($actor === 'platform admin') {
        $user->update(['is_admin' => true]);
    } elseif (in_array($actor, ['unverified', 'inactive owner', 'suspended clinic'], true)) {
        $clinic->memberships()->create([
            'user_id' => $user->id, 'role' => 'business_owner', 'is_active' => $actor !== 'inactive owner',
        ]);
        if ($actor === 'suspended clinic') {
            $clinic->update(['status' => 'suspended']);
        }
    }

    if ($actor !== 'guest') {
        $this->actingAs($user, 'sanctum');
    }
    $url = "/api/businesses/{$clinic->id}/crm/consent-templates";
    $status = $actor === 'guest' ? 401 : 403;
    $fields = ['title' => 'Genel taslak', 'content' => 'Genel onam metni.'];
    $this->getJson($url)->assertStatus($status);
    $this->getJson($url.'/'.$draft->id)->assertStatus($status);
    $this->postJson($url, $fields)->assertStatus($status);
    $this->putJson($url.'/'.$draft->id, $fields + ['revision' => 1])->assertStatus($status);
    $this->assertDatabaseCount('consent_templates', 1);
    expect($draft->fresh()->revision)->toBe(1);
})->with(['guest', 'staff', 'platform admin', 'unverified', 'inactive owner', 'suspended clinic']);

test('drafts require a title and text and changes require the revision being edited', function () {
    $clinic = Business::factory()->create();
    $owner = User::factory()->create();
    $clinic->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $url = "/api/businesses/{$clinic->id}/crm/consent-templates";

    $this->actingAs($owner, 'sanctum')->postJson($url, ['title' => ' ', 'content' => ''])
        ->assertUnprocessable()->assertJsonValidationErrors(['title', 'content']);
    $this->postJson($url, ['title' => str_repeat('a', 256), 'content' => str_repeat('a', 20001)])
        ->assertUnprocessable()->assertJsonValidationErrors(['title', 'content']);
    $this->assertDatabaseCount('consent_templates', 0);

    $draft = ConsentTemplate::factory()->for($clinic)->create();
    $this->putJson($url.'/'.$draft->id, ['title' => 'Genel taslak', 'content' => 'Genel onam metni.'])
        ->assertUnprocessable()->assertJsonValidationErrors('revision');
    expect($draft->fresh()->revision)->toBe(1);
});
