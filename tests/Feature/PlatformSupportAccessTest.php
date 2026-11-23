<?php

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

test('assigned support uses the existing platform workspace with only assigned read and reply access', function () {
    $administrator = User::factory()->create(['is_admin' => true]);
    $operator = User::factory()->create(['password' => 'synthetic-test-password']);
    $requester = User::factory()->create();
    $assigned = SupportTicket::factory()->for($requester, 'requester')->create();
    $unassigned = SupportTicket::factory()->for($requester, 'requester')->create();
    $oldToken = $operator->createToken('before-grant')->plainTextToken;
    $adminToken = $administrator->createToken('support-administrator')->plainTextToken;
    $grant = '/api/admin/support-agents/'.$operator->id;
    $queue = '/api/admin/support-tickets';

    $useToken = function (string $token): void {
        Auth::forgetGuards();
        $this->withToken($token);
    };

    $this->postJson('/api/admin/login', ['email' => $operator->email, 'password' => 'synthetic-test-password'])->assertForbidden();
    $useToken($adminToken);
    $this->putJson($grant, ['active' => true, 'is_admin' => true])->assertOk()->assertJsonPath('data.active', true);
    $this->putJson($grant, ['active' => true])->assertOk();
    $this->assertDatabaseCount('platform_role_changes', 1);
    $this->assertDatabaseHas('users', ['id' => $operator->id, 'is_admin' => false, 'platform_role' => 'support']);
    $this->putJson($queue.'/'.$assigned->id.'/assignment', ['assigned_to_user_id' => $operator->id, 'assignment_id' => null])
        ->assertOk()->assertJsonPath('data.assignee.id', $operator->id);

    $useToken($oldToken);
    $this->getJson('/api/admin/workspace')->assertUnauthorized();
    $operatorToken = $this->postJson('/api/admin/login', ['email' => $operator->email, 'password' => 'synthetic-test-password'])
        ->assertOk()->assertJsonMissingPath('data.user.platform_role')->json('data.token');
    $useToken($operatorToken);
    $this->get('/api/admin/workspace')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('Bana atanan talepler')->assertDontSee('Hasta hesapları')->assertDontSee('Destek görevlisi yetkisi');
    $this->getJson($queue)->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $assigned->id);
    $this->getJson($queue.'/'.$assigned->id)->assertOk()->assertJsonMissingPath('data.assignment_candidates');
    $this->getJson($queue.'/'.$unassigned->id)->assertNotFound();
    $this->postJson($queue.'/'.$unassigned->id.'/messages', ['body' => 'İzinsiz yanıt'])->assertNotFound();
    $this->putJson($queue.'/'.$assigned->id.'/status', ['status' => 'closed'])->assertForbidden();
    $this->putJson($queue.'/'.$assigned->id.'/assignment', ['assigned_to_user_id' => null, 'assignment_id' => null])->assertForbidden();
    foreach (['dashboard', 'operations', 'patient-accounts', 'audit'] as $path) {
        $this->getJson('/api/admin/'.$path)->assertForbidden();
    }
    $this->putJson($grant, ['active' => false])->assertForbidden();
    $this->postJson($queue.'/'.$assigned->id.'/messages', ['body' => 'Yetkili destek yanıtı'])->assertCreated()
        ->assertJsonPath('data.messages.data.0.from_support', true)->assertJsonMissingPath('data.assignment_candidates');
    $this->assertDatabaseCount('support_ticket_messages', 1);

    $useToken($adminToken);
    $this->putJson('/api/admin/support-agents/'.$administrator->id, ['active' => false, 'is_admin' => false])->assertUnprocessable();
    $this->putJson($grant, ['active' => false])->assertOk();
    $this->getJson('/api/admin/audit?source=platform_role')->assertOk()
        ->assertJsonPath('data.data.0.action', 'support_revoked')->assertJsonPath('data.data.0.actor_name', $administrator->name)
        ->assertJsonPath('data.data.1.action', 'support_granted')->assertJsonPath('data.data.1.target_id', $operator->id);
    $this->getJson($queue.'?assignment=unassigned')->assertOk()->assertJsonCount(2, 'data.data');

    $useToken($operatorToken);
    $this->getJson($queue)->assertUnauthorized();
    $this->assertDatabaseHas('users', ['id' => $administrator->id, 'is_admin' => true]);
});

test('reassignment immediately closes read and reply access for the previous support operator', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $former = User::factory()->create(['platform_role' => 'support']);
    $current = User::factory()->create(['platform_role' => 'support']);
    $ticket = SupportTicket::factory()->create();
    $assignment = $ticket->assignments()->create([
        'assigned_to_user_id' => $former->id, 'assigned_by_user_id' => $admin->id, 'action' => 'assigned',
    ]);
    $url = '/api/admin/support-tickets/'.$ticket->id;
    $this->actingAs($admin, 'sanctum')->putJson($url.'/assignment', [
        'assignment_id' => $assignment->id, 'assigned_to_user_id' => $current->id,
    ])->assertOk();
    $this->actingAs($former, 'sanctum')->getJson($url)->assertNotFound();
    $this->postJson($url.'/messages', ['body' => 'Eski görevlendirme'])->assertNotFound();
    $this->actingAs($current, 'sanctum')->postJson($url.'/messages', ['body' => 'Güncel görevli'])->assertCreated();
    $this->assertDatabaseCount('support_ticket_messages', 1);
});
