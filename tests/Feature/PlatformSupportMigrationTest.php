<?php

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('the support role migration preserves existing accounts tokens tickets and assignments across upgrade and rollback', function () {
    $this->withConcurrentDatabase(function (): void {
        $migration = require database_path('migrations/2026_11_23_210007_add_platform_support_role_and_changes.php');
        $migration->down();

        $admin = User::factory()->create(['is_admin' => true]);
        $operator = User::factory()->create();
        $token = $operator->createToken('legacy-session')->accessToken;
        $ticket = SupportTicket::factory()->create();
        $assignment = $ticket->assignments()->create([
            'assigned_to_user_id' => $operator->id, 'assigned_by_user_id' => $admin->id, 'action' => 'assigned',
        ]);

        expect(Schema::hasColumn('users', 'platform_role'))->toBeFalse();
        $migration->up();

        expect(Schema::hasColumn('users', 'platform_role'))->toBeTrue();
        $this->assertDatabaseCount('platform_role_changes', 0);
        $this->assertDatabaseHas('users', ['id' => $operator->id, 'platform_role' => null]);

        $assertLegacyData = function () use ($admin, $token, $ticket, $assignment): void {
            $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_admin' => true, 'password' => $admin->password]);
            $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->id, 'token' => $token->token]);
            $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'subject' => $ticket->subject]);
            $this->assertDatabaseHas('support_ticket_assignments', [
                'id' => $assignment->id, 'support_ticket_id' => $ticket->id, 'assigned_to_user_id' => $token->tokenable_id,
            ]);
        };
        $assertLegacyData();

        $migration->down();
        expect(Schema::hasColumn('users', 'platform_role'))->toBeFalse()
            ->and(Schema::hasTable('platform_role_changes'))->toBeFalse();
        $assertLegacyData();

        $migration->up();
        $assertLegacyData();
    });
});
