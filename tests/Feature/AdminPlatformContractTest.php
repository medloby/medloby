<?php

use App\Models\PlatformContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAdminUser(): User
{
    return User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);
}

function createRegularUser(): User
{
    return User::factory()->create([
        'is_admin' => false,
        'email_verified_at' => now(),
    ]);
}

function createContract(array $attributes = []): PlatformContract
{
    return PlatformContract::create(array_merge([
        'contract_type' => 'clinic_membership',
        'title' => 'Medloby Klinik Üyelik Sözleşmesi',
        'version' => '1.0',
        'content' => 'Test sözleşme metni.',
        'status' => 'draft',
        'is_required' => true,
        'effective_at' => null,
        'published_at' => null,
        'expires_at' => null,
    ], $attributes));
}

test('admin can access platform contract list', function () {
    $admin = createAdminUser();

    createContract();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/platform-contracts');

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    expect($response->json('data.data'))
        ->toHaveCount(1);
});

test('admin can list clinic membership versions separately from other contract types', function () {
    $admin = createAdminUser();
    $clinicContract = createContract();
    createContract(['contract_type' => 'patient_terms']);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/platform-contracts?contract_type=clinic_membership&per_page=1')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $clinicContract->id);
});

test('admin can view platform contract detail', function () {
    $admin = createAdminUser();

    $contract = createContract();

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson(
            "/api/admin/platform-contracts/{$contract->id}"
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $response->assertJsonPath(
        'data.id',
        $contract->id
    );

    $response->assertJsonPath(
        'data.version',
        '1.0'
    );
});

test('admin can create a platform contract as draft', function () {
    $admin = createAdminUser();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/platform-contracts', [
            'contract_type' => 'clinic_membership',
            'title' => 'Medloby Klinik Üyelik Sözleşmesi',
            'version' => '1.0',
            'content' => 'Test sözleşme metni.',
            'is_required' => true,
        ]);

    $response
        ->assertCreated()
        ->assertJson([
            'success' => true,
            'message' => 'Platform sözleşmesi taslak olarak oluşturuldu.',
        ]);

    $this->assertDatabaseHas('platform_contracts', [
        'contract_type' => 'clinic_membership',
        'version' => '1.0',
        'status' => 'draft',
        'is_required' => true,
        'created_by_user_id' => $admin->id,
        'updated_by_user_id' => $admin->id,
    ]);
});

test('admin cannot create duplicate contract version', function () {
    $admin = createAdminUser();

    createContract([
        'contract_type' => 'clinic_membership',
        'version' => '1.0',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/admin/platform-contracts', [
            'contract_type' => 'clinic_membership',
            'title' => 'Aynı Versiyon',
            'version' => '1.0',
            'content' => 'Yeni içerik.',
        ]);

    $response->assertStatus(422);

    expect(
        PlatformContract::where(
            'contract_type',
            'clinic_membership'
        )->where('version', '1.0')->count()
    )->toBe(1);
});

test('admin can update a draft platform contract', function () {
    $admin = createAdminUser();

    $contract = createContract();

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/platform-contracts/{$contract->id}",
            [
                'title' => 'Güncellenmiş Klinik Sözleşmesi',
                'content' => 'Güncellenmiş sözleşme içeriği.',
            ]
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Platform sözleşmesi güncellendi.',
        ]);

    $contract->refresh();

    expect($contract->title)
        ->toBe('Güncellenmiş Klinik Sözleşmesi');

    expect($contract->content)
        ->toBe('Güncellenmiş sözleşme içeriği.');

    expect($contract->updated_by_user_id)
        ->toBe($admin->id);
});

test('a stale draft edit cannot overwrite a newer edit made in the same second', function () {
    $this->freezeTime();
    $admin = createAdminUser();
    $contract = createContract();
    $path = '/api/admin/platform-contracts/'.$contract->id;
    $client = $this->actingAs($admin, 'sanctum');
    $revision = $client->getJson($path)->assertOk()->json('data.draft_revision');

    $client->putJson($path, ['content' => 'İlk kayıt.', 'draft_revision' => $revision])->assertOk();
    $client->putJson($path, ['content' => 'Eski ekrandan kayıt.', 'draft_revision' => $revision])->assertConflict();

    expect($contract->fresh()->content)->toBe('İlk kayıt.');
});

test('a draft cannot be updated to an existing version of the same contract type', function () {
    $admin = createAdminUser();
    $contract = createContract();
    createContract(['version' => '2.0']);

    $this->actingAs($admin, 'sanctum')
        ->putJson('/api/admin/platform-contracts/'.$contract->id, ['version' => '2.0'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('version');

    expect($contract->fresh()->version)->toBe('1.0');
});

test('admin cannot update a published platform contract', function () {
    $admin = createAdminUser();

    $contract = createContract([
        'status' => 'published',
        'published_at' => now(),
        'effective_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->putJson(
            "/api/admin/platform-contracts/{$contract->id}",
            [
                'title' => 'Değiştirilmemesi gereken sözleşme',
            ]
        );

    $response->assertStatus(422);

    $contract->refresh();

    expect($contract->title)
        ->toBe('Medloby Klinik Üyelik Sözleşmesi');
});

test('admin can publish a draft platform contract', function () {
    $admin = createAdminUser();

    $contract = createContract();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/platform-contracts/{$contract->id}/publish"
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Platform sözleşmesi yayınlandı.',
        ]);

    $contract->refresh();

    expect($contract->status)
        ->toBe('published');

    expect($contract->published_at)
        ->not->toBeNull();

    expect($contract->effective_at)
        ->not->toBeNull();

    expect($contract->updated_by_user_id)
        ->toBe($admin->id);
});

test('admin cannot publish an already published contract', function () {
    $admin = createAdminUser();

    $contract = createContract([
        'status' => 'published',
        'published_at' => now(),
        'effective_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/platform-contracts/{$contract->id}/publish"
        );

    $response->assertStatus(422);
});

test('publishing from an older draft screen cannot publish changes made by another administrator', function () {
    $admin = createAdminUser();
    $contract = createContract();
    $path = '/api/admin/platform-contracts/'.$contract->id;

    $this->actingAs($admin, 'sanctum');
    $revision = $this->getJson($path)->assertOk()->json('data.draft_revision');
    $this->putJson($path, ['content' => 'Güncellenen sözleşme.', 'draft_revision' => $revision])->assertOk();
    $this->postJson($path.'/publish', ['draft_revision' => $revision])->assertConflict();
    $this->assertDatabaseHas('platform_contracts', ['id' => $contract->id, 'status' => 'draft']);
    $this->getJson('/api/register/business/contract')->assertNotFound();
});

test('a contract must have a valid effective period when it is published', function () {
    $this->freezeTime();
    $past = createContract(['version' => '1.1', 'effective_at' => now()->subDays(2), 'expires_at' => now()->subDay()]);
    $equal = createContract(['version' => '1.2', 'effective_at' => now()->addDay(), 'expires_at' => now()->addDay()]);

    $this->actingAs(createAdminUser(), 'sanctum');
    foreach ([$past, $equal] as $contract) {
        $this->postJson('/api/admin/platform-contracts/'.$contract->id.'/publish')
            ->assertUnprocessable()->assertJsonValidationErrors('expires_at');
        $this->assertDatabaseHas('platform_contracts', ['id' => $contract->id, 'status' => 'draft', 'published_at' => null]);
    }
});

test('a future version starts on its effective date and keeps its private acceptance records after removal', function () {
    $this->travelTo(now()->startOfSecond());
    $previous = createContract(['status' => 'published', 'published_at' => now()->subDay(), 'effective_at' => now()->subDay()]);
    $new = createContract(['version' => '2.0', 'effective_at' => now()->addHour(), 'expires_at' => now()->addHours(3)]);
    $path = '/api/admin/platform-contracts/'.$new->id;
    $acceptances = $path.'/acceptances?per_page=1';
    $admin = createAdminUser();

    $this->actingAs($admin, 'sanctum');
    $revision = $this->getJson($path)->assertOk()->json('data.draft_revision');
    $this->postJson($path.'/publish', ['draft_revision' => $revision])->assertOk()
        ->assertJsonPath('data.effective_at', $new->effective_at->toISOString());

    $apply = function (string $email, string $clinic, PlatformContract $contract): void {
        $this->postJson('/api/register/business/application', [
            'owner_email' => $email, 'business_name' => $clinic, 'business_phone' => '+90 312 555 32 01',
            'platform_contract_id' => $contract->id, 'contract_accepted' => true,
        ])->assertCreated();
    };

    $this->getJson('/api/register/business/contract')->assertOk()->assertJsonPath('data.id', $previous->id);
    $apply('onceki@example.test', 'Önceki Sürüm Kliniği', $previous);
    $this->travel(1)->hour();
    $this->getJson('/api/register/business/contract')->assertOk()->assertJsonPath('data.id', $new->id);
    $apply('yeni@example.test', 'Yeni Sürüm Kliniği', $new);
    $apply('son@example.test', 'Son Başvuran Klinik', $new);

    $this->postJson($path.'/deactivate')->assertOk();
    $this->getJson('/api/register/business/contract')->assertOk()->assertJsonPath('data.id', $previous->id);
    $this->getJson($acceptances)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.total', 2)->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.contract_version', '2.0')
        ->assertJsonPath('data.data.0.business.name', 'Son Başvuran Klinik')
        ->assertJsonMissingPath('data.data.0.ip_address')->assertJsonMissingPath('data.data.0.user_agent')
        ->assertJsonMissingPath('data.data.0.user.email');
    $this->getJson($acceptances.'&page=2')->assertOk()->assertJsonPath('data.data.0.business.name', 'Yeni Sürüm Kliniği');
    $this->actingAs(createRegularUser(), 'sanctum')->getJson($acceptances)->assertForbidden();
});

test('admin can deactivate a published platform contract', function () {
    $admin = createAdminUser();

    $contract = createContract([
        'status' => 'published',
        'published_at' => now(),
        'effective_at' => now(),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/platform-contracts/{$contract->id}/deactivate"
        );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Platform sözleşmesi pasifleştirildi.',
        ]);

    $contract->refresh();

    expect($contract->status)
        ->toBe('inactive');

    expect($contract->updated_by_user_id)
        ->toBe($admin->id);
});

test('admin cannot deactivate a draft contract', function () {
    $admin = createAdminUser();

    $contract = createContract([
        'status' => 'draft',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/platform-contracts/{$contract->id}/deactivate"
        );

    $response->assertStatus(422);

    $contract->refresh();

    expect($contract->status)
        ->toBe('draft');
});

test('admin cannot deactivate an already inactive contract', function () {
    $admin = createAdminUser();

    $contract = createContract([
        'status' => 'inactive',
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson(
            "/api/admin/platform-contracts/{$contract->id}/deactivate"
        );

    $response->assertStatus(422);
});

test('non admin user cannot access platform contract list', function () {
    $user = createRegularUser();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/admin/platform-contracts');

    $response
        ->assertStatus(403)
        ->assertJson([
            'success' => false,
            'message' => 'Bu işlem için admin yetkisi gereklidir.',
        ]);
});

test('non admin user cannot create platform contract', function () {
    $user = createRegularUser();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/admin/platform-contracts', [
            'contract_type' => 'clinic_membership',
            'title' => 'Yetkisiz Sözleşme',
            'version' => '1.0',
            'content' => 'Test içeriği.',
        ]);

    $response->assertForbidden();

    $this->assertDatabaseMissing('platform_contracts', [
        'title' => 'Yetkisiz Sözleşme',
    ]);
});

test('guest cannot access platform contract list', function () {
    $response = $this->getJson(
        '/api/admin/platform-contracts'
    );

    $response->assertUnauthorized();
});
