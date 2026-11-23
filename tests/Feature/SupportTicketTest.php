<?php

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;

uses(RefreshDatabase::class);

test('an account and platform support can exchange messages and use private in-app notifications', function () {
    $requester = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $unverified = User::factory()->unverified()->create(['is_admin' => true]);
    $subject = 'Teklif ekranına erişim';
    $body = 'Hesabımda teklif ekranını bulamıyorum.';

    $ticket = $this->actingAs($requester, 'sanctum')->postJson('/api/support-tickets', [
        'subject' => $subject, 'body' => $body, 'requester_id' => $admin->id, 'from_support' => true, 'status' => 'closed',
    ])->assertCreated()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.subject', $subject)->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.messages.data.0.body', $body)->assertJsonPath('data.messages.data.0.from_support', false)
        ->assertJsonMissingPath('data.messages.data.0.sender_user_id')->json('data.id');
    $this->assertDatabaseHas('support_tickets', ['id' => $ticket, 'requester_id' => $requester->id, 'closed_at' => null]);
    $this->assertDatabaseHas('support_ticket_messages', ['support_ticket_id' => $ticket, 'body' => $body, 'sender_user_id' => $requester->id]);
    $notice = $admin->notifications()->sole()->data;
    expect($notice)->toMatchArray(['support_ticket_id' => $ticket, 'notification_type' => NotificationType::SUPPORT_TICKET_UPDATED]);
    expect(array_keys($notice))->toEqualCanonicalizing(['support_ticket_id', 'notification_type', 'title', 'message']);
    expect($unverified->notifications()->count())->toBe(0);

    $this->actingAs($admin, 'sanctum')->getJson('/api/admin/support-tickets?status=open')->assertOk()
        ->assertJsonPath('data.data.0.requester_name', $requester->name)->assertJsonMissingPath('data.data.0.requester_id')
        ->assertJsonMissingPath('data.data.0.messages');
    $queue = '/api/admin/support-tickets?status=awaiting_reply';
    $this->getJson($queue)->assertOk()->assertJsonPath('data.data.0.awaiting_reply', true);
    $adminUrl = "/api/admin/support-tickets/{$ticket}";
    $answer = 'Hesabınızdan Paket teklifleri bölümünü açabilirsiniz.';
    $this->postJson($adminUrl.'/messages', ['body' => $answer])->assertCreated()
        ->assertJsonPath('data.messages.data.0.body', $answer)->assertJsonPath('data.messages.data.0.from_support', true);
    $this->getJson($queue)->assertOk()->assertJsonPath('data.total', 0);
    expect($requester->notifications()->sole()->data)->toMatchArray([
        'support_ticket_id' => $ticket, 'title' => 'Destek talebinize yanıt geldi',
    ]);

    $url = "/api/support-tickets/{$ticket}";
    $this->actingAs($requester, 'sanctum')->getJson($url)->assertOk()
        ->assertJsonPath('data.messages.data.0.body', $answer)->assertJsonMissingPath('data.requester_name');
    $this->postJson($url.'/messages', ['body' => 'Ekranı buldum, teşekkürler.'])->assertCreated()
        ->assertJsonPath('data.messages.data.0.from_support', false);
    $this->actingAs($admin, 'sanctum')->getJson($queue)->assertOk()->assertJsonPath('data.data.0.id', $ticket);
    expect($admin->notifications()->count())->toBe(2);
    $this->assertDatabaseCount('support_ticket_messages', 3);
});

test('the admin reply queue combines ticket search and requester scope without changing the personal inbox', function () {
    $requester = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $awaiting = SupportTicket::factory()->for($requester, 'requester')->create(['subject' => 'Takvim sorusu']);
    $closed = SupportTicket::factory()->for($requester, 'requester')->create(['subject' => 'Takvim kapali', 'closed_at' => now()]);
    $empty = SupportTicket::factory()->for($requester, 'requester')->create(['subject' => 'Takvim eski kayit']);
    $other = SupportTicket::factory()->create(['subject' => 'Takvim baska hesap']);
    foreach ([$awaiting, $closed, $other] as $ticket) {
        SupportTicketMessage::factory()->for($ticket)->create();
    }

    $path = '/api/admin/support-tickets';
    $this->actingAs($admin, 'sanctum')->getJson($path.'?status=awaiting_reply&search=takvim&requester_id='.$requester->id)
        ->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $awaiting->id);
    $this->getJson($path.'?search=%23'.$closed->id)->assertOk()
        ->assertJsonPath('data.data.0.id', $closed->id)->assertJsonPath('data.data.0.awaiting_reply', false);
    $this->getJson($path.'?search='.$empty->id)->assertOk()
        ->assertJsonPath('data.data.0.id', $empty->id)->assertJsonPath('data.data.0.awaiting_reply', false);
    $this->getJson($path.'?search=%23'.$other->id.'&requester_id='.$requester->id)->assertOk()->assertJsonPath('data.total', 0);

    $this->actingAs($requester, 'sanctum')->getJson($path.'?search='.$other->id)->assertForbidden();
    $this->getJson('/api/support-tickets?search='.$other->id)
        ->assertOk()->assertJsonPath('data.total', 3)->assertJsonMissingPath('data.data.0.awaiting_reply');
    $this->getJson('/api/support-tickets?status=awaiting_reply')->assertUnprocessable()->assertJsonValidationErrors('status');
});

test('support tickets stay private and the inbox requires platform administration', function () {
    $ticket = SupportTicket::factory()->create();
    $user = User::factory()->create();
    $url = "/api/support-tickets/{$ticket->id}";
    $adminUrl = "/api/admin/support-tickets/{$ticket->id}";
    $this->getJson($url)->assertUnauthorized();
    $this->actingAs(User::factory()->unverified()->create(), 'sanctum')->getJson('/api/support-tickets')->assertForbidden();

    $this->actingAs($user, 'sanctum')->getJson('/api/support-tickets')->assertOk()->assertJsonPath('data.data', []);
    $this->getJson($url)->assertNotFound();
    $this->postJson($url.'/messages', ['body' => 'Başka hesaba ait talep.'])->assertNotFound();
    $this->getJson('/api/admin/support-tickets')->assertForbidden();
    $this->getJson($adminUrl)->assertForbidden();
    $this->postJson($adminUrl.'/messages', ['body' => 'Yetkisiz yanıt.'])->assertForbidden();
    $this->putJson($adminUrl.'/status', ['status' => 'closed'])->assertForbidden();
    $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'closed_at' => null]);
    $this->assertDatabaseCount('support_ticket_messages', 0);
});

test('verified platform administrators can assign tickets once per change and filter their own queue', function () {
    $actor = User::factory()->create(['is_admin' => true, 'name' => 'Ayşe Yönetici']);
    $assignee = User::factory()->create(['is_admin' => true, 'name' => 'Can Yönetici']);
    User::factory()->unverified()->create(['is_admin' => true]);
    $ticket = SupportTicket::factory()->create();
    $other = SupportTicket::factory()->create();
    $queue = '/api/admin/support-tickets';
    $path = $queue.'/'.$ticket->id.'/assignment';
    $assign = ['assigned_to_user_id' => $assignee->id, 'assignment_id' => null];

    $this->actingAs($actor, 'sanctum');
    $assignment = $this->putJson($path, $assign)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.assignee', ['id' => $assignee->id, 'name' => $assignee->name])
        ->assertJsonCount(2, 'data.assignment_candidates')->assertJsonMissingPath('data.assignment_candidates.0.email')
        ->json('data.assignment_id');
    $this->putJson($path, $assign)->assertOk()->assertJsonPath('data.assignment_id', $assignment);
    $this->assertDatabaseCount('support_ticket_assignments', 1);
    expect($assignee->notifications()->sole()->data)->toMatchArray([
        'support_ticket_id' => $ticket->id, 'title' => 'Destek talebi size atandı',
    ]);

    $this->getJson($queue.'?status=open&assignment=unassigned')->assertOk()
        ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $other->id);
    $this->actingAs($assignee, 'sanctum')->getJson($queue.'?assignment=mine')->assertOk()
        ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $ticket->id);

    $this->actingAs($actor, 'sanctum')->putJson($path, ['assigned_to_user_id' => $actor->id, 'assignment_id' => $assignment])
        ->assertOk()->assertJsonPath('data.assignee.id', $actor->id);
    $this->putJson($path, ['assigned_to_user_id' => null, 'assignment_id' => $assignment])->assertConflict();
    $this->getJson($queue.'?assignment=mine')->assertOk()->assertJsonPath('data.data.0.id', $ticket->id);
    $this->actingAs($assignee, 'sanctum')->getJson($queue.'?assignment=mine')->assertOk()->assertJsonPath('data.total', 0);
    $this->assertDatabaseCount('support_ticket_assignments', 2);
    $this->assertDatabaseCount('notifications', 1);
});

test('invalid assignees stay private and clearing a stale assignment leaves an audit event', function () {
    $actor = User::factory()->create(['is_admin' => true]);
    $assignee = User::factory()->create(['is_admin' => true]);
    $unverified = User::factory()->unverified()->create(['is_admin' => true]);
    $ticket = SupportTicket::factory()->create();
    $requester = $ticket->requester;
    $queue = '/api/admin/support-tickets';
    $detail = $queue.'/'.$ticket->id;
    $assign = ['assigned_to_user_id' => $assignee->id, 'assignment_id' => null];

    $this->actingAs($requester, 'sanctum')->putJson($detail.'/assignment', $assign)->assertForbidden();
    $this->actingAs($unverified, 'sanctum')->putJson($detail.'/assignment', $assign)->assertForbidden();
    $this->actingAs($actor, 'sanctum');
    foreach ([$requester, $unverified] as $invalid) {
        $this->putJson($detail.'/assignment', ['assigned_to_user_id' => $invalid->id, 'assignment_id' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('assigned_to_user_id');
    }

    $assignment = $this->putJson($detail.'/assignment', $assign)->assertOk()->json('data.assignment_id');
    $assignee->forceFill(['is_admin' => false])->save();
    $this->getJson($queue.'?assignment=unassigned')->assertOk()->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.assignment_needs_attention', true)->assertJsonPath('data.data.0.assignee', null);

    $clear = ['assigned_to_user_id' => null, 'assignment_id' => $assignment];
    $this->putJson($detail.'/status', ['status' => 'closed'])->assertOk();
    $this->putJson($detail.'/assignment', $clear)->assertConflict();
    $this->putJson($detail.'/status', ['status' => 'open'])->assertOk();
    $this->putJson($detail.'/assignment', $clear)->assertOk()->assertJsonPath('data.assignee', null)
        ->assertJsonPath('data.assignment_needs_attention', false);
    $this->assertDatabaseCount('support_ticket_assignments', 2);
    $this->getJson('/api/admin/audit?source=support')->assertOk()->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.data.0.action', 'unassigned')->assertJsonPath('data.data.0.target_id', $ticket->id)
        ->assertJsonPath('data.data.0.actor_name', $actor->name)->assertJsonPath('data.data.1.action', 'assigned')
        ->assertJsonMissingPath('data.data.1.assignee');
    $this->actingAs($requester, 'sanctum')->getJson('/api/support-tickets/'.$ticket->id)
        ->assertOk()->assertJsonMissingPath('data.assignee')->assertJsonMissingPath('data.assignment_id')
        ->assertJsonMissingPath('data.assignment_candidates');
});

test('the platform can close and reopen a support request and both sides can read closed requests', function () {
    $ticket = SupportTicket::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $url = "/api/admin/support-tickets/{$ticket->id}";
    $this->actingAs($admin, 'sanctum')->putJson($url.'/status', ['status' => 'closed'])->assertOk()
        ->assertJsonPath('data.status', 'closed');
    $closedAt = $ticket->fresh()->closed_at;
    $this->travel(2)->minutes();
    $this->putJson($url.'/status', ['status' => 'closed'])->assertOk();
    expect($ticket->fresh()->closed_at->equalTo($closedAt))->toBeTrue();
    $this->getJson('/api/admin/support-tickets?status=open')->assertOk()->assertJsonPath('data.total', 0);
    $this->getJson('/api/admin/support-tickets?status=closed')->assertOk()->assertJsonPath('data.data.0.id', $ticket->id);
    $this->postJson($url.'/messages', ['body' => 'Kapalı talebe yanıt.'])->assertConflict();

    $mine = "/api/support-tickets/{$ticket->id}";
    $this->actingAs($ticket->requester, 'sanctum')->getJson($mine)->assertOk()->assertJsonPath('data.status', 'closed');
    $this->postJson($mine.'/messages', ['body' => 'Kapalı talebe mesaj.'])->assertConflict();
    $this->assertDatabaseCount('support_ticket_messages', 0);

    $this->actingAs($admin, 'sanctum')->putJson($url.'/status', ['status' => 'open'])->assertOk()
        ->assertJsonPath('data.status', 'open');
    $this->actingAs($ticket->requester, 'sanctum')->postJson($mine.'/messages', ['body' => 'Ek bilgi.'])->assertCreated();
    $this->assertDatabaseCount('support_ticket_messages', 1);
});

test('support input is validated and longer discussions can be read page by page', function () {
    $ticket = SupportTicket::factory()->create();
    $first = SupportTicketMessage::factory()->for($ticket)->create(['body' => 'İlk mesaj']);
    $last = SupportTicketMessage::factory()->for($ticket)->count(20)->create()->last();
    $url = "/api/support-tickets/{$ticket->id}";
    $this->actingAs($ticket->requester, 'sanctum')->postJson('/api/support-tickets', ['subject' => ' ', 'body' => str_repeat('a', 4001)])
        ->assertUnprocessable()->assertJsonValidationErrors(['subject', 'body']);
    $this->getJson('/api/support-tickets?status=unknown')->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->postJson($url.'/messages', ['body' => ' '])->assertUnprocessable()->assertJsonValidationErrors('body');
    $this->getJson($url)->assertOk()->assertJsonCount(20, 'data.messages.data')
        ->assertJsonPath('data.messages.data.0.id', $last->id);
    $this->getJson($url.'?message_page=2')->assertOk()->assertJsonPath('data.messages.data.0.id', $first->id);
    $this->assertDatabaseCount('support_tickets', 1);
    $this->assertDatabaseCount('support_ticket_messages', 21);
});

test('a failed support notification leaves ticket creation retryable', function () {
    $user = User::factory()->create();
    User::factory()->create(['is_admin' => true]);
    Exceptions::fake();
    $available = false;
    Event::listen(NotificationSending::class, function () use (&$available): void {
        if (! $available) {
            throw new RuntimeException('Test notification storage unavailable');
        }
    });

    $body = ['subject' => 'Teknik destek', 'body' => 'Hesap ekranını açamıyorum.'];
    $this->actingAs($user, 'sanctum')->postJson('/api/support-tickets', $body)->assertServerError();
    $this->assertDatabaseCount('support_tickets', 0);
    $this->assertDatabaseCount('support_ticket_messages', 0);

    $available = true;
    $this->postJson('/api/support-tickets', $body)->assertCreated();
    $this->assertDatabaseCount('support_tickets', 1);
    $this->assertDatabaseCount('support_ticket_messages', 1);
    $this->assertDatabaseCount('notifications', 1);
});
