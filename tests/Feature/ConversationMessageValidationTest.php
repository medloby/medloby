<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Conversation;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createConversationMessageValidationData(): array
{
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Validation Test Şubesi',
        'slug' => 'validation-test-subesi-' . uniqid(),
        'country_code' => 'TR',
        'city' => 'Istanbul',
        'district' => 'Kadikoy',
        'status' => 'active',
    ]);

    $patientUser = User::factory()->create();

    $patientProfile = PatientProfile::create([
        'user_id' => $patientUser->id,
        'first_name' => 'Test',
        'last_name' => 'Hasta',
        'phone' => '05550000000',
    ]);

    $businessUser = User::factory()->create();

    BusinessUser::create([
        'business_id' => $business->id,
        'user_id' => $businessUser->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    return [
        'business' => $business,
        'branch' => $branch,
        'patientUser' => $patientUser,
        'patientProfile' => $patientProfile,
        'businessUser' => $businessUser,
    ];
}

function createValidationConversation(
    array $data,
    string $status = 'open'
): Conversation {
    return Conversation::create([
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'patient_profile_id' => $data['patientProfile']->id,
        'subject' => 'Validation test görüşmesi',
        'status' => $status,
    ]);
}

test('cannot send message to closed conversation', function () {
    $data = createConversationMessageValidationData();

    $conversation = createValidationConversation(
        $data,
        'closed'
    );

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/conversations/{$conversation->id}/messages",
            [
                'body' => 'Kapalı görüşmeye gönderilmemesi gereken mesaj.',
            ]
        );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'Kapalı görüşmeye mesaj gönderilemez.',
        ]);

    $this->assertDatabaseMissing('messages', [
        'conversation_id' => $conversation->id,
        'sender_user_id' => $data['patientUser']->id,
        'body' => 'Kapalı görüşmeye gönderilmemesi gereken mesaj.',
    ]);
});

test('business user cannot send message to closed conversation', function () {
    $data = createConversationMessageValidationData();

    $conversation = createValidationConversation(
        $data,
        'closed'
    );

    $response = $this
        ->actingAs($data['businessUser'])
        ->postJson(
            "/api/conversations/{$conversation->id}/messages",
            [
                'body' => 'Business tarafından kapalı görüşmeye mesaj.',
            ]
        );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'Kapalı görüşmeye mesaj gönderilemez.',
        ]);

    $this->assertDatabaseMissing('messages', [
        'conversation_id' => $conversation->id,
        'sender_user_id' => $data['businessUser']->id,
        'body' => 'Business tarafından kapalı görüşmeye mesaj.',
    ]);
});

test('cannot send empty message without attachment', function () {
    $data = createConversationMessageValidationData();

    $conversation = createValidationConversation($data);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/conversations/{$conversation->id}/messages",
            []
        );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'Mesaj veya dosya göndermelisiniz.',
        ]);

    $this->assertDatabaseCount('messages', 0);
    $this->assertDatabaseCount('message_attachments', 0);
});

test('empty string message without attachment is rejected', function () {
    $data = createConversationMessageValidationData();

    $conversation = createValidationConversation($data);

    $response = $this
        ->actingAs($data['patientUser'])
        ->postJson(
            "/api/conversations/{$conversation->id}/messages",
            [
                'body' => '',
            ]
        );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'Mesaj veya dosya göndermelisiniz.',
        ]);

    $this->assertDatabaseCount('messages', 0);
    $this->assertDatabaseCount('message_attachments', 0);
});