<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Conversation;
use App\Models\PatientProfile;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\User;
use App\Notifications\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function createConversationTestData(): array
{
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $otherBusiness = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $patientUser = User::factory()->create();

    $businessUser = User::factory()->create();

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $businessUser->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $patientProfile = PatientProfile::create([
        'user_id' => $patientUser->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
    ]);

    $businessBranch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Test İşletme Şubesi',
        'slug' => 'test-isletme-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $otherBusinessBranch = Branch::create([
        'business_id' => $otherBusiness->id,
        'name' => 'Başka İşletme Şubesi',
        'slug' => 'baska-isletme-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Besiktas',
        'status' => 'active',
    ]);

    return [
        'business' => $business,
        'otherBusiness' => $otherBusiness,
        'businessUser' => $businessUser,
        'patientUser' => $patientUser,
        'patientProfile' => $patientProfile,
        'businessBranch' => $businessBranch,
        'otherBusinessBranch' => $otherBusinessBranch,
    ];
}

function createConversation(
    array $data,
    ?int $businessId = null,
    ?int $branchId = null,
    ?int $patientProfileId = null,
    string $status = 'open'
): Conversation {
    return Conversation::create([
        'business_id' => $businessId ?? $data['business']->id,
        'branch_id' => $branchId ?? $data['businessBranch']->id,
        'patient_profile_id' => $patientProfileId ?? $data['patientProfile']->id,
        'subject' => 'Test görüşmesi',
        'status' => $status,
    ]);
}

test('patient can create conversation for business without selecting branch', function () {
    $data = createConversationTestData();

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson('/api/conversations', [
            'business_id' => $data['business']->id,
            'subject' => 'Genel görüşme',
        ]);

    $response->assertCreated();

    $response->assertJson([
        'success' => true,
        'message' => 'Görüşme başarıyla oluşturuldu.',
    ]);

    $this->assertDatabaseHas('conversations', [
        'business_id' => $data['business']->id,
        'branch_id' => null,
        'patient_profile_id' => $data['patientProfile']->id,
        'status' => 'open',
    ]);
});

test('patient can create conversation for branch belonging to selected business', function () {
    $data = createConversationTestData();

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson('/api/conversations', [
            'business_id' => $data['business']->id,
            'branch_id' => $data['businessBranch']->id,
            'subject' => 'Şube görüşmesi',
        ]);

    $response->assertCreated();

    $response->assertJson([
        'success' => true,
    ]);

    $this->assertDatabaseHas('conversations', [
        'business_id' => $data['business']->id,
        'branch_id' => $data['businessBranch']->id,
        'patient_profile_id' => $data['patientProfile']->id,
        'status' => 'open',
    ]);
});

test('patient cannot create conversation using branch belonging to another business', function () {
    $data = createConversationTestData();

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson('/api/conversations', [
            'business_id' => $data['business']->id,
            'branch_id' => $data['otherBusinessBranch']->id,
            'subject' => 'Yetkisiz şube görüşmesi',
        ]);

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Seçilen şube bu işletmeye ait değil.',
    ]);

    $this->assertDatabaseMissing('conversations', [
        'business_id' => $data['business']->id,
        'branch_id' => $data['otherBusinessBranch']->id,
        'patient_profile_id' => $data['patientProfile']->id,
    ]);
});

test('unauthenticated user cannot create conversation', function () {
    $data = createConversationTestData();

    $response = $this->postJson('/api/conversations', [
        'business_id' => $data['business']->id,
        'branch_id' => $data['businessBranch']->id,
        'subject' => 'Yetkisiz görüşme',
    ]);

    $response->assertUnauthorized();
});

test('user without patient profile cannot create conversation', function () {
    $data = createConversationTestData();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson('/api/conversations', [
            'business_id' => $data['business']->id,
            'branch_id' => $data['businessBranch']->id,
            'subject' => 'Hasta profilsiz görüşme',
        ]);

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu işlem yalnızca hasta hesabı ile yapılabilir.',
    ]);
});

test('business user cannot create conversation for a business they do not belong to', function () {
    $data = createConversationTestData();

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson('/api/conversations', [
            'business_id' => $data['otherBusiness']->id,
            'branch_id' => $data['otherBusinessBranch']->id,
            'subject' => 'İşletme hesabından görüşme',
        ]);

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu işlem yalnızca hasta hesabı ile yapılabilir.',
    ]);
});

test('patient cannot create conversation for inactive business', function () {
    $data = createConversationTestData();

    $data['business']->update([
        'status' => 'inactive',
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson('/api/conversations', [
            'business_id' => $data['business']->id,
            'branch_id' => $data['businessBranch']->id,
            'subject' => 'Pasif işletme görüşmesi',
        ]);

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Bu sağlık merkezi şu anda iletişime açık değil.',
    ]);
});

test('patient cannot create conversation for unverified business', function () {
    $data = createConversationTestData();

    $data['business']->update([
        'is_verified' => false,
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson('/api/conversations', [
            'business_id' => $data['business']->id,
            'branch_id' => $data['businessBranch']->id,
            'subject' => 'Doğrulanmamış işletme görüşmesi',
        ]);

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Bu sağlık merkezi şu anda iletişime açık değil.',
    ]);
});

test('patient can access their own conversation', function () {
    $data = createConversationTestData();

    $conversation = createConversation($data);

    $response = $this
        ->actingAs($data['patientUser'])
        ->getJson(
            "/api/conversations/{$conversation->id}"
        );

    $response->assertOk();

    $response->assertJsonPath(
        'data.conversation.id',
        $conversation->id
    );
});

test('business user can access conversation belonging to their business', function () {
    $data = createConversationTestData();

    $conversation = createConversation($data);

    $response = $this
        ->actingAs($data['businessUser'])
        ->getJson(
            "/api/conversations/{$conversation->id}"
        );

    $response->assertOk();

    $response->assertJsonPath(
        'data.conversation.id',
        $conversation->id
    );
});

test('user cannot access conversation belonging to another business', function () {
    $data = createConversationTestData();

    $conversation = createConversation(
        $data,
        $data['otherBusiness']->id,
        $data['otherBusinessBranch']->id
    );

    $response = $this
        ->actingAs($data['businessUser'])
        ->getJson(
            "/api/conversations/{$conversation->id}"
        );

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu görüşmeye erişim yetkiniz yok.',
    ]);
});

test('patient can send message to their own open conversation', function () {
    $data = createConversationTestData();

    $conversation = createConversation($data);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/conversations/{$conversation->id}/messages",
            [
                'body' => 'Merhaba, tedavi hakkında bilgi almak istiyorum.',
            ]
        );

    $response->assertCreated();

    $response->assertJson([
        'success' => true,
        'message' => 'Mesaj başarıyla gönderildi.',
        'data' => [
            'body' => 'Merhaba, tedavi hakkında bilgi almak istiyorum.',
            'message_type' => 'text',
        ],
    ]);

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'sender_user_id' => $data['patientUser']->id,
        'body' => 'Merhaba, tedavi hakkında bilgi almak istiyorum.',
        'message_type' => 'text',
    ]);

    $this->assertDatabaseHas('conversations', [
        'id' => $conversation->id,
    ]);
});

test('business user can send message to conversation belonging to their business', function () {
    $data = createConversationTestData();

    $conversation = createConversation($data);

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$conversation->id}/messages",
            [
                'body' => 'Merhaba, size yardımcı olmak isteriz.',
            ]
        );

    $response->assertCreated();

    $response->assertJson([
        'success' => true,
        'message' => 'Mesaj başarıyla gönderildi.',
        'data' => [
            'body' => 'Merhaba, size yardımcı olmak isteriz.',
            'message_type' => 'text',
        ],
    ]);

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'sender_user_id' => $data['businessUser']->id,
        'body' => 'Merhaba, size yardımcı olmak isteriz.',
        'message_type' => 'text',
    ]);
});

test('user cannot send message to conversation they cannot access', function () {
    $data = createConversationTestData();

    $unauthorizedUser = User::factory()->create();

    $conversation = createConversation($data);

    $response = $this
        ->actingAs($unauthorizedUser)
        ->postJson(
            "/api/conversations/{$conversation->id}/messages",
            [
                'body' => 'Yetkisiz mesaj.',
            ]
        );

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu görüşmeye erişim yetkiniz yok.',
    ]);

    $this->assertDatabaseMissing('messages', [
        'conversation_id' => $conversation->id,
        'sender_user_id' => $unauthorizedUser->id,
        'body' => 'Yetkisiz mesaj.',
    ]);
});

test('patient cannot send message to another patients conversation', function () {
    $data = createConversationTestData();

    $otherPatientUser = User::factory()->create();

    $otherPatientProfile = PatientProfile::create([
        'user_id' => $otherPatientUser->id,
        'first_name' => 'Başka',
        'last_name' => 'Hasta',
        'phone' => '05551111111',
    ]);

    $conversation = createConversation(
        $data,
        $data['business']->id,
        $data['businessBranch']->id,
        $otherPatientProfile->id
    );

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/conversations/{$conversation->id}/messages",
            [
                'body' => 'Başkasının görüşmesine mesaj.',
            ]
        );

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu görüşmeye erişim yetkiniz yok.',
    ]);

    $this->assertDatabaseMissing('messages', [
        'conversation_id' => $conversation->id,
        'sender_user_id' => $data['patientUser']->id,
        'body' => 'Başkasının görüşmesine mesaj.',
    ]);
});

test('patient can send jpg attachment to their own conversation', function () {
    Storage::fake('private');

    $data = createConversationTestData();

    $conversation = createConversation($data);

    $file = UploadedFile::fake()->image('hasta-fotografi.jpg');

    $response = $this
        ->actingAs($data['patientUser'])
        ->post(
            "/api/conversations/{$conversation->id}/messages",
            [
                'body' => 'Tedavi öncesi fotoğraf.',
                'attachment' => $file,
            ]
        );

    $response->assertCreated();

    $response->assertJson([
        'success' => true,
        'message' => 'Mesaj başarıyla gönderildi.',
        'data' => [
            'body' => 'Tedavi öncesi fotoğraf.',
            'message_type' => 'file',
        ],
    ]);

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'sender_user_id' => $data['patientUser']->id,
        'body' => 'Tedavi öncesi fotoğraf.',
        'message_type' => 'file',
    ]);

    $this->assertDatabaseHas('message_attachments', [
        'original_name' => 'hasta-fotografi.jpg',
        'mime_type' => 'image/jpeg',
        'disk' => 'private',
    ]);
});

test('business user can send pdf attachment to conversation belonging to their business', function () {
    Storage::fake('private');

    $data = createConversationTestData();

    $conversation = createConversation($data);

    $file = UploadedFile::fake()->create(
        'tedavi-belgesi.pdf',
        100,
        'application/pdf'
    );

    $response = $this
        ->actingAs($data['businessUser'])
        ->post(
            "/api/conversations/{$conversation->id}/messages",
            [
                'body' => 'Tedavi belgesini iletiyorum.',
                'attachment' => $file,
            ]
        );

    $response->assertCreated();

    $response->assertJson([
        'success' => true,
        'message' => 'Mesaj başarıyla gönderildi.',
        'data' => [
            'body' => 'Tedavi belgesini iletiyorum.',
            'message_type' => 'file',
        ],
    ]);

    $this->assertDatabaseHas('message_attachments', [
        'original_name' => 'tedavi-belgesi.pdf',
        'mime_type' => 'application/pdf',
        'disk' => 'private',
    ]);
});

test('patient can send png attachment without message body', function () {
    Storage::fake('private');

    $data = createConversationTestData();

    $conversation = createConversation($data);

    $file = UploadedFile::fake()->image('rontgen.png');

    $response = $this
        ->actingAs($data['patientUser'])
        ->post(
            "/api/conversations/{$conversation->id}/messages",
            [
                'attachment' => $file,
            ]
        );

    $response->assertCreated();

    $response->assertJson([
        'success' => true,
        'message' => 'Mesaj başarıyla gönderildi.',
        'data' => [
            'body' => null,
            'message_type' => 'file',
        ],
    ]);

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'sender_user_id' => $data['patientUser']->id,
        'body' => null,
        'message_type' => 'file',
    ]);

    $this->assertDatabaseHas('message_attachments', [
        'original_name' => 'rontgen.png',
        'mime_type' => 'image/png',
        'disk' => 'private',
    ]);
});

test('user cannot upload unsupported attachment type', function () {
    Storage::fake('private');

    $data = createConversationTestData();

    $conversation = createConversation($data);

    $file = UploadedFile::fake()->create(
        'zararli.exe',
        100,
        'application/octet-stream'
    );

    $response = $this
        ->actingAs($data['patientUser'])
        ->post(
            "/api/conversations/{$conversation->id}/messages",
            [
                'body' => 'Geçersiz dosya.',
                'attachment' => $file,
            ]
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'attachment',
    ]);

    $this->assertDatabaseCount('messages', 0);
    $this->assertDatabaseCount('message_attachments', 0);
});

test('user cannot upload attachment larger than 10 mb', function () {
    Storage::fake('private');

    $data = createConversationTestData();

    $conversation = createConversation($data);

    $file = UploadedFile::fake()->create(
        'buyuk-dosya.pdf',
        10241,
        'application/pdf'
    );

    $response = $this
        ->actingAs($data['patientUser'])
        ->post(
            "/api/conversations/{$conversation->id}/messages",
            [
                'body' => 'Büyük dosya.',
                'attachment' => $file,
            ]
        );

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'attachment',
    ]);

    $this->assertDatabaseCount('messages', 0);
    $this->assertDatabaseCount('message_attachments', 0);
});

test('message attachment is stored under the conversation directory', function () {
    Storage::fake('private');

    $data = createConversationTestData();

    $conversation = createConversation($data);

    $file = UploadedFile::fake()->image('klinik-fotografi.jpg');

    $response = $this
        ->actingAs($data['businessUser'])
        ->post(
            "/api/conversations/{$conversation->id}/messages",
            [
                'attachment' => $file,
            ]
        );

    $response->assertCreated();

    $attachment = \App\Models\MessageAttachment::first();

    expect($attachment)->not->toBeNull();
    expect($attachment->disk)->toBe('private');
    expect($attachment->path)
        ->toStartWith('conversations/' . $conversation->id . '/');

    Storage::disk('private')->assertExists($attachment->path);
});

test('guests can discover eligible packages and patients can open one conversation for a package', function () {
    $data = createConversationTestData();
    $data['businessUser']->businessMemberships()->sole()->update(['role' => 'business_owner']);
    $assigned = User::factory()->create();
    $data['business']->memberships()->create([
        'user_id' => $assigned->id,
        'role' => 'staff',
        'is_active' => true,
    ])->branches()->attach($data['businessBranch']->id, ['is_active' => true]);
    $unassigned = User::factory()->create();
    $data['business']->memberships()->create([
        'user_id' => $unassigned->id,
        'role' => 'staff',
        'is_active' => true,
    ]);
    $category = TreatmentCategory::create(['name' => 'Tedaviler', 'slug' => 'paket-tedavileri']);
    $treatment = Treatment::create([
        'treatment_category_id' => $category->id,
        'name' => 'Paket tedavisi',
        'slug' => 'paket-tedavisi',
        'is_active' => true,
    ]);
    $data['businessBranch']->treatments()->attach($treatment->id, ['is_active' => true]);
    $package = $data['business']->treatmentPackages()->create([
        'branch_id' => $data['businessBranch']->id,
        'name' => 'Tedavi paketi',
        'slug' => 'tedavi-paketi',
        'valid_from' => today(),
        'valid_until' => today(),
    ]);
    $package->treatments()->attach($treatment->id, ['quantity' => 2, 'notes' => 'İç not']);

    $draft = $data['business']->treatmentPackages()->create([
        'name' => 'Taslak',
        'slug' => 'taslak',
        'is_active' => false,
    ]);
    $draft->treatments()->attach($treatment->id);

    $unavailable = $data['otherBusiness']->treatmentPackages()->create([
        'branch_id' => $data['otherBusinessBranch']->id,
        'name' => 'Şubede sunulmayan paket',
        'slug' => 'sunulmayan-paket',
    ]);
    $unavailable->treatments()->attach($treatment->id);

    $this->getJson('/api/package-catalog')->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $package->id)
        ->assertJsonPath('data.data.0.treatments', [['name' => $treatment->name, 'quantity' => 2]]);

    $payload = ['treatment_package_id' => $package->id];
    $this->postJson('/api/conversations', $payload)->assertUnauthorized();

    $this->actingAs($data['patientUser']);
    $this->getJson('/api/me')->assertOk()->assertJsonPath('data.user.patient_profile_exists', true);
    $this->postJson('/api/conversations', ['treatment_package_id' => $draft->id])
        ->assertUnprocessable()->assertJsonValidationErrors('treatment_package_id');
    $this->postJson('/api/conversations', ['treatment_package_id' => $unavailable->id])
        ->assertUnprocessable()->assertJsonValidationErrors('treatment_package_id');
    $this->postJson('/api/conversations', $payload + ['branch_id' => $data['otherBusinessBranch']->id])
        ->assertUnprocessable()->assertJsonValidationErrors('treatment_package_id');

    $id = $this->postJson('/api/conversations', $payload)->assertCreated()
        ->assertJsonPath('data.branch_id', $data['businessBranch']->id)
        ->assertJsonPath('data.business_id', $data['business']->id)
        ->assertJsonPath('data.treatment_package.id', $package->id)
        ->assertJsonPath('data.subject', $package->name)
        ->json('data.id');
    $this->postJson('/api/conversations', $payload)->assertOk()->assertJsonPath('data.id', $id);
    $this->assertDatabaseCount('conversations', 1);
    $this->assertDatabaseCount('messages', 1);
    $this->assertDatabaseHas('messages', [
        'conversation_id' => $id,
        'sender_user_id' => $data['patientUser']->id,
        'body' => 'Bu tedavi paketi için teklif almak istiyorum.',
    ]);

    foreach ([$data['businessUser'], $assigned] as $recipient) {
        $this->actingAs($recipient)->getJson('/api/notifications')->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.data.notification_type', NotificationType::PATIENT_REQUEST)
            ->assertJsonPath('data.data.0.data.conversation_id', $id);
    }
    $this->actingAs($unassigned)->getJson('/api/notifications/unread-count')->assertOk()
        ->assertJsonPath('data.count', 0);

    $this->actingAs($data['businessUser'])->getJson('/api/conversations')->assertOk()
        ->assertJsonPath('data.data.0.treatment_package.name', $package->name);
    $this->postJson('/api/conversations', $payload)->assertForbidden();

    $messages = "/api/conversations/{$id}/messages";
    $this->postJson($messages, ['body' => 'Size nasıl yardımcı olabiliriz?'])->assertCreated();
    $this->actingAs($data['patientUser'])->getJson('/api/notifications')->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.data.notification_type', NotificationType::NEW_MESSAGE)
        ->assertJsonPath('data.data.0.data.conversation_id', $id);

    $data['business']->notificationPreferences()->create([
        'notification_type' => NotificationType::NEW_MESSAGE,
        'in_app_enabled' => false,
        'email_enabled' => false,
    ]);
    $this->postJson($messages, ['body' => 'Paketle ilgili bilgi almak istiyorum.'])->assertCreated();

    $this->actingAs($data['businessUser'])->getJson('/api/notifications/unread-count')->assertOk()
        ->assertJsonPath('data.count', 1);

    $package->update(['valid_until' => today()->subDay()]);
    $this->getJson('/api/package-catalog')->assertOk()->assertJsonPath('data.data', []);
    $this->actingAs($data['patientUser'])->postJson('/api/conversations', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('treatment_package_id');
});
