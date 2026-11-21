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
        'role' => 'business_owner',
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

test('branch assignment controls conversation listing, messages, offer access and attachments together', function () {
    Storage::fake('local');
    $data = createMessageAttachmentTestData();
    $attachment = createTestAttachment($data['message']);
    Storage::disk('local')->put($attachment->path, 'Test document');
    $staff = User::factory()->create();
    $otherBranch = Branch::factory()->for($data['business'])->create();
    $membership = $data['business']->memberships()->create([
        'user_id' => $staff->id, 'role' => 'staff', 'is_active' => true,
    ]);
    $membership->branches()->attach($otherBranch->id, ['is_active' => true]);
    $membership->branches()->attach($data['branch']->id, ['is_active' => false]);
    $conversationUrl = "/api/conversations/{$data['conversation']->id}";
    $fileUrl = "/api/attachments/{$attachment->id}/view";
    $message = ['body' => 'Şube takip mesajı'];

    $this->actingAs($staff)->getJson('/api/conversations')->assertOk()->assertJsonCount(0, 'data.data');
    $this->getJson($conversationUrl)->assertForbidden();
    $this->getJson($conversationUrl.'/offers')->assertForbidden();
    $this->getJson($conversationUrl.'/offers/package-options')->assertForbidden();
    $this->postJson($conversationUrl.'/messages', $message)->assertForbidden();
    $this->getJson($fileUrl)->assertForbidden();

    $membership->branches()->updateExistingPivot($data['branch']->id, ['is_active' => true]);
    $this->getJson('/api/conversations')->assertOk()->assertJsonPath('data.data.0.id', $data['conversation']->id);
    $this->getJson($conversationUrl)->assertOk()->assertJsonPath('data.messages.data.0.id', $data['message']->id);
    $this->getJson($conversationUrl.'/offers')->assertOk();
    $this->getJson($conversationUrl.'/offers/package-options')->assertOk();
    $this->postJson($conversationUrl.'/messages', $message)->assertCreated();
    $this->get($fileUrl)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
});

test('patients retain their history when a clinic is suspended while new exchanges are blocked', function () {
    Storage::fake('local');
    $data = createMessageAttachmentTestData();
    $attachment = createTestAttachment($data['message']);
    Storage::disk('local')->put($attachment->path, 'Test document');
    $conversation = $data['conversation'];
    $offer = $conversation->offers()->create([
        'business_id' => $data['business']->id,
        'branch_id' => $data['branch']->id,
        'patient_profile_id' => $data['patientProfile']->id,
        'created_by' => $data['businessUser']->id,
        'title' => 'Paket teklifi',
        'amount' => 5000,
        'currency' => 'TRY',
        'status' => 'pending',
    ]);
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->postJson("/api/admin/businesses/{$data['business']->id}/suspend", [
        'reason' => 'Klinik inceleme için askıya alındı.',
    ])->assertOk();
    $conversationUrl = "/api/conversations/{$conversation->id}";
    $fileUrl = "/api/attachments/{$attachment->id}/view";
    $offerUrl = "/api/offers/{$offer->id}";

    $this->actingAs($data['businessUser'])->getJson('/api/conversations')->assertOk()->assertJsonCount(0, 'data.data');
    $this->getJson($conversationUrl.'/offers')->assertForbidden();
    $this->postJson($conversationUrl.'/offers', [])->assertForbidden();
    $this->getJson($fileUrl)->assertForbidden();

    $this->actingAs($data['patientUser'])->getJson('/api/conversations')
        ->assertOk()->assertJsonPath('data.data.0.id', $conversation->id);
    $this->getJson($conversationUrl)->assertOk()->assertJsonPath('data.messages.data.0.id', $data['message']->id);
    $this->get($fileUrl)->assertOk();
    $this->getJson($conversationUrl.'/offers')->assertOk()->assertJsonPath('data.0.id', $offer->id);
    $this->postJson($conversationUrl.'/messages', ['body' => 'Yeni mesaj'])->assertUnprocessable();
    $this->postJson('/api/conversations', ['business_id' => $data['business']->id])->assertUnprocessable();
    $this->postJson($offerUrl.'/accept')->assertUnprocessable();
    $this->postJson($offerUrl.'/reject')->assertUnprocessable();
    expect($offer->refresh()->status)->toBe('pending');

    $offer->update(['status' => 'accepted']);
    $this->getJson($offerUrl.'/appointments')->assertOk();
    $this->getJson($offerUrl.'/appointments/options')->assertUnprocessable();
    $this->postJson($offerUrl.'/appointments', [])->assertUnprocessable();

    $this->actingAs($admin)->postJson("/api/admin/businesses/{$data['business']->id}/reactivate", [
        'reason' => 'Klinik incelemesi tamamlandı.',
    ])->assertOk();
    $this->actingAs($data['patientUser'])->postJson($conversationUrl.'/messages', [
        'body' => 'Klinik açıldıktan sonra yeni mesaj.',
    ])->assertCreated();
});

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
