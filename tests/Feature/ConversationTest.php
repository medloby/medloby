<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Conversation;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

function createConversation(array $data, ?int $businessId = null, ?int $branchId = null, ?int $patientProfileId = null, string $status = 'open'): Conversation
{
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
});

test('business user cannot send message to another business conversation', function () {
    $data = createConversationTestData();

    $conversation = createConversation(
        $data,
        $data['otherBusiness']->id,
        $data['otherBusinessBranch']->id
    );

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$conversation->id}/messages",
            [
                'body' => 'Başka işletmenin görüşmesine mesaj.',
            ]
        );

    $response->assertForbidden();

    $response->assertJson([
        'message' => 'Bu görüşmeye erişim yetkiniz yok.',
    ]);
});

test('user cannot send message to closed conversation', function () {
    $data = createConversationTestData();

    $conversation = createConversation(
        $data,
        null,
        null,
        null,
        'closed'
    );

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/conversations/{$conversation->id}/messages",
            [
                'body' => 'Kapalı görüşmeye mesaj.',
            ]
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Kapalı görüşmeye mesaj gönderilemez.',
    ]);

    $this->assertDatabaseMissing('messages', [
        'conversation_id' => $conversation->id,
        'sender_user_id' => $data['patientUser']->id,
        'body' => 'Kapalı görüşmeye mesaj.',
    ]);
});

test('user cannot send empty message without attachment', function () {
    $data = createConversationTestData();

    $conversation = createConversation($data);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/conversations/{$conversation->id}/messages",
            []
        );

    $response->assertUnprocessable();

    $response->assertJson([
        'message' => 'Mesaj veya dosya göndermelisiniz.',
    ]);
});