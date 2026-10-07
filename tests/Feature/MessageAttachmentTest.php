<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createMessageAttachmentTestData(): array
{
    $business = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $branch = Branch::create([
        'business_id' => $business->id,
        'name' => 'Attachment Test Şubesi',
        'slug' => 'attachment-test-subesi-' . Str::uuid(),
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

    $conversation = Conversation::create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'patient_profile_id' => $patientProfile->id,
        'subject' => 'Dosya erişim testi',
        'status' => 'open',
    ]);

    $message = Message::create([
        'conversation_id' => $conversation->id,
        'sender_user_id' => $patientUser->id,
        'body' => 'Dosya içeren mesaj.',
        'message_type' => 'text',
    ]);

    return [
        'business' => $business,
        'branch' => $branch,
        'patientUser' => $patientUser,
        'patientProfile' => $patientProfile,
        'businessUser' => $businessUser,
        'conversation' => $conversation,
        'message' => $message,
    ];
}

function createTestAttachment(
    Message $message,
    string $disk = 'local',
    string $path = 'attachments/test-document.txt'
): MessageAttachment {
    return MessageAttachment::create([
        'message_id' => $message->id,
        'disk' => $disk,
        'path' => $path,
        'original_name' => 'test-document.txt',
        'mime_type' => 'text/plain',
        'size' => 12,
    ]);
}

test('patient can view attachment from their conversation', function () {
    Storage::fake('local');

    $data = createMessageAttachmentTestData();

    Storage::disk('local')->put(
        'attachments/test-document.txt',
        'Hello Medloby'
    );

    $attachment = createTestAttachment(
        $data['message']
    );

    $response = $this
        ->actingAs($data['patientUser'])
        ->get(
            "/api/attachments/{$attachment->id}/view"
        );

    $response
        ->assertOk()
        ->assertHeader(
            'Content-Type',
            'text/plain; charset=UTF-8'
        );

    expect($response->streamedContent())
        ->toBe('Hello Medloby');
});

test('business user can view attachment from their business conversation', function () {
    Storage::fake('local');

    $data = createMessageAttachmentTestData();

    Storage::disk('local')->put(
        'attachments/test-document.txt',
        'Hello Business'
    );

    $attachment = createTestAttachment(
        $data['message']
    );

    $response = $this
        ->actingAs($data['businessUser'])
        ->get(
            "/api/attachments/{$attachment->id}/view"
        );

    $response
        ->assertOk()
        ->assertHeader(
            'Content-Type',
            'text/plain; charset=UTF-8'
        );

    expect($response->streamedContent())
        ->toBe('Hello Business');
});

test('unrelated user cannot view attachment', function () {
    Storage::fake('local');

    $data = createMessageAttachmentTestData();

    Storage::disk('local')->put(
        'attachments/test-document.txt',
        'Private file'
    );

    $attachment = createTestAttachment(
        $data['message']
    );

    $otherUser = User::factory()->create();

    $response = $this
        ->actingAs($otherUser)
        ->get(
            "/api/attachments/{$attachment->id}/view"
        );

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'Bu dosyaya erişim yetkiniz yok.',
        ]);
});

test('business user from another business cannot view attachment', function () {
    Storage::fake('local');

    $data = createMessageAttachmentTestData();

    Storage::disk('local')->put(
        'attachments/test-document.txt',
        'Private business file'
    );

    $attachment = createTestAttachment(
        $data['message']
    );

    $otherBusiness = Business::factory()->create([
        'status' => 'active',
        'is_verified' => true,
    ]);

    $otherBusinessUser = User::factory()->create();

    BusinessUser::create([
        'business_id' => $otherBusiness->id,
        'user_id' => $otherBusinessUser->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($otherBusinessUser)
        ->get(
            "/api/attachments/{$attachment->id}/view"
        );

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'Bu dosyaya erişim yetkiniz yok.',
        ]);
});

test('inactive business membership cannot view attachment', function () {
    Storage::fake('local');

    $data = createMessageAttachmentTestData();

    Storage::disk('local')->put(
        'attachments/test-document.txt',
        'Private file'
    );

    $attachment = createTestAttachment(
        $data['message']
    );

    $inactiveUser = User::factory()->create();

    BusinessUser::create([
        'business_id' => $data['business']->id,
        'user_id' => $inactiveUser->id,
        'role' => 'staff',
        'is_active' => false,
    ]);

    $response = $this
        ->actingAs($inactiveUser)
        ->get(
            "/api/attachments/{$attachment->id}/view"
        );

    $response
        ->assertForbidden()
        ->assertJson([
            'message' => 'Bu dosyaya erişim yetkiniz yok.',
        ]);
});

test('attachment view requires authentication', function () {
    $data = createMessageAttachmentTestData();

    $attachment = createTestAttachment(
        $data['message']
    );

    $response = $this->getJson(
        "/api/attachments/{$attachment->id}/view"
    );

    $response->assertUnauthorized();
});

test('attachment view returns not found when attachment does not exist', function () {
    $response = $this->actingAs(
        User::factory()->create()
    )->get(
        '/api/attachments/999999/view'
    );

    $response->assertNotFound();
});

test('attachment view returns not found when stored file does not exist', function () {
    Storage::fake('local');

    $data = createMessageAttachmentTestData();

    $attachment = createTestAttachment(
        $data['message']
    );

    $response = $this
        ->actingAs($data['patientUser'])
        ->get(
            "/api/attachments/{$attachment->id}/view"
        );

    $response
        ->assertNotFound()
        ->assertJson([
            'message' => 'Dosya bulunamadı.',
        ]);
});

test('attachment view uses attachment original name and mime type', function () {
    Storage::fake('local');

    $data = createMessageAttachmentTestData();

    Storage::disk('local')->put(
        'attachments/photo.jpg',
        'fake-image-content'
    );

    $attachment = MessageAttachment::create([
        'message_id' => $data['message']->id,
        'disk' => 'local',
        'path' => 'attachments/photo.jpg',
        'original_name' => 'hasta-fotografi.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 18,
    ]);

    $response = $this
        ->actingAs($data['patientUser'])
        ->get(
            "/api/attachments/{$attachment->id}/view"
        );

    $response
        ->assertOk()
        ->assertHeader(
            'Content-Type',
            'image/jpeg'
        )
        ->assertHeader(
            'Content-Disposition',
            'inline; filename="hasta-fotografi.jpg"'
        );

    expect($response->streamedContent())
        ->toBe('fake-image-content');
});