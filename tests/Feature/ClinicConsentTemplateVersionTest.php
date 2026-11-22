<?php

use App\Models\Business;
use App\Models\ConsentTemplate;
use App\Models\ConsentTemplateVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an owner can prepare fixed versions and review earlier text after editing the draft', function () {
    $clinic = Business::factory()->create();
    $owner = User::factory()->create();
    $clinic->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $draft = ConsentTemplate::factory()->for($clinic)->create([
        'title' => 'Genel muayene',
        'content' => "İlk metin.\nİkinci satır.",
    ]);
    $url = "/api/businesses/{$clinic->id}/crm/consent-templates/{$draft->id}";
    $versions = $url.'/versions';

    $this->actingAs($owner, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('data.prepared_revision', null);
    $first = $this->postJson($versions, [
        'revision' => 1, 'title' => 'İstekteki başlık', 'content' => 'İstekteki metin', 'prepared_by_user_id' => 0,
    ])->assertCreated()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.title', $draft->title)->assertJsonPath('data.content', $draft->content)
        ->assertJsonPath('data.revision', 1)->assertJsonMissingPath('data.prepared_by_user_id')->json('data');

    $this->assertDatabaseHas('consent_template_versions', [
        'id' => $first['id'], 'consent_template_id' => $draft->id, 'revision' => 1,
        'title' => $draft->title, 'content' => $draft->content, 'prepared_by_user_id' => $owner->id,
    ]);
    $this->putJson($url, ['revision' => 1, 'title' => 'Yeni başlık', 'content' => 'Güncel metin.'])
        ->assertOk()->assertJsonPath('data.revision', 2)->assertJsonPath('data.prepared_revision', 1);
    $this->postJson($versions, ['revision' => 1])->assertOk()->assertJsonPath('data', $first);
    $second = $this->postJson($versions, ['revision' => 2])->assertCreated()
        ->assertJsonPath('data.content', 'Güncel metin.')->json('data');

    $this->getJson($versions)->assertOk()->assertJsonCount(2, 'data.data')
        ->assertJsonPath('data.data.0.revision', 2)->assertJsonPath('data.data.1.revision', 1)
        ->assertJsonMissingPath('data.data.0.content');
    $this->getJson($versions.'/'.$first['id'])->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data', $first);
    $this->getJson($versions.'/'.$second['id'])->assertOk()->assertJsonPath('data', $second);
    $this->getJson($url)->assertOk()->assertJsonPath('data.prepared_revision', 2);
    $this->assertDatabaseCount('consent_template_versions', 2);
    $this->assertDatabaseCount('consents', 0);
});

test('version creation rejects a stale unprepared revision and requires the revision being prepared', function () {
    $clinic = Business::factory()->create();
    $owner = User::factory()->create();
    $clinic->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $draft = ConsentTemplate::factory()->for($clinic)->create(['revision' => 2]);
    $url = "/api/businesses/{$clinic->id}/crm/consent-templates/{$draft->id}/versions";

    $this->actingAs($owner, 'sanctum')->postJson($url, ['revision' => 1])->assertConflict();
    $this->postJson($url, ['revision' => 3])->assertConflict();
    $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('revision');
    $this->assertDatabaseCount('consent_template_versions', 0);
});

test('prepared versions are scoped to the exact template and active clinical ownership', function () {
    $clinic = Business::factory()->create();
    $owner = User::factory()->create();
    $membership = $clinic->memberships()->create(['user_id' => $owner->id, 'role' => 'business_owner', 'is_active' => true]);
    $first = ConsentTemplate::factory()->for($clinic)->create();
    $second = ConsentTemplate::factory()->for($clinic)->create();
    $foreign = ConsentTemplate::factory()->create();
    $version = ConsentTemplateVersion::factory()->for($first)->create();
    $url = "/api/businesses/{$clinic->id}/crm/consent-templates";
    $detail = $url."/{$first->id}/versions/{$version->id}";

    $this->getJson($detail)->assertUnauthorized();
    $this->actingAs($owner, 'sanctum')->getJson($url."/{$second->id}/versions/{$version->id}")->assertNotFound();
    $this->getJson($url."/{$foreign->id}/versions")->assertNotFound();
    $this->postJson($url."/{$foreign->id}/versions", ['revision' => 1])->assertNotFound();

    $membership->update(['role' => 'staff']);
    $this->getJson($detail)->assertForbidden();
    $this->getJson($url."/{$first->id}/versions")->assertForbidden();
    $this->postJson($url."/{$first->id}/versions", ['revision' => 1])->assertForbidden();
    $this->assertDatabaseCount('consent_template_versions', 1);
});
