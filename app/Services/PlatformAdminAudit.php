<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use stdClass;

final class PlatformAdminAudit
{
    public const array SOURCES = ['clinic_application', 'clinic_status', 'contract', 'support', 'platform_role', 'clinic_membership'];

    public function recent(?string $source): LengthAwarePaginator
    {
        $events = match ($source) {
            'clinic_application' => $this->clinicApplications(),
            'clinic_status' => $this->clinicStatuses(),
            'contract' => $this->publications()->unionAll($this->deactivations()),
            'support' => $this->supportReplies()->unionAll($this->supportAssignments()),
            'platform_role' => $this->roleChanges(),
            'clinic_membership' => $this->clinicMembershipChanges(),
            default => $this->clinicApplications()->unionAll($this->clinicStatuses())
                ->unionAll($this->publications())->unionAll($this->deactivations())
                ->unionAll($this->supportReplies())->unionAll($this->supportAssignments())->unionAll($this->roleChanges())
                ->unionAll($this->clinicMembershipChanges()),
        };

        return DB::query()->fromSub($events, 'audit_events')
            ->leftJoin('clinic_membership_changes as membership_change', fn (JoinClause $join) => $join
                ->on('audit_events.event_id', '=', 'membership_change.id')->where('audit_events.source', 'clinic_membership'))
            ->leftJoin('permissions as permission', 'permission.id', '=', 'membership_change.permission_id')
            ->select('audit_events.*', 'membership_change.business_id', 'membership_change.previous_value',
                'membership_change.new_value', 'permission.display_name as permission_name')
            ->orderByDesc('audit_events.occurred_at')->orderBy('audit_events.source')
            ->orderByDesc('audit_events.event_id')->orderBy('audit_events.event_action')->paginate(20)
            ->through(fn (stdClass $event): array => [
                'source' => $event->source,
                'action' => $event->event_action,
                'target_id' => (int) $event->target_id,
                'target_name' => $event->target_name,
                'actor_name' => $event->actor_name,
                'occurred_at' => CarbonImmutable::parse($event->occurred_at)->toIso8601String(),
                ...($event->source === 'clinic_membership' ? ['change' => [
                    'business_id' => (int) $event->business_id,
                    'previous_value' => $event->previous_value,
                    'new_value' => $event->new_value,
                    'permission_name' => $event->permission_name,
                ]] : []),
            ]);
    }

    private function clinicApplications(): Builder
    {
        return DB::table('business_reviews as entry')->join('businesses as clinic', 'clinic.id', '=', 'entry.business_id')
            ->leftJoin('users as actor', 'actor.id', '=', 'entry.reviewed_by_user_id')
            ->whereNotNull('entry.reviewed_at')->whereIn('entry.decision', ['approved', 'rejected'])
            ->selectRaw("'clinic_application' AS source, entry.id AS event_id, entry.business_id AS target_id,
                clinic.name AS target_name, actor.name AS actor_name, entry.decision AS event_action, entry.reviewed_at AS occurred_at");
    }

    private function clinicStatuses(): Builder
    {
        return DB::table('business_status_histories as entry')->join('businesses as clinic', 'clinic.id', '=', 'entry.business_id')
            ->leftJoin('users as actor', 'actor.id', '=', 'entry.changed_by_user_id')
            ->whereNotNull('entry.changed_at')
            ->selectRaw("'clinic_status' AS source, entry.id AS event_id, entry.business_id AS target_id,
                clinic.name AS target_name, actor.name AS actor_name, entry.new_status AS event_action, entry.changed_at AS occurred_at");
    }

    private function publications(): Builder
    {
        return DB::table('platform_contracts as entry')->leftJoin('users as actor', 'actor.id', '=', 'entry.published_by_user_id')
            ->whereNotNull('entry.published_at')
            ->selectRaw("'contract' AS source, entry.id AS event_id, entry.id AS target_id,
                NULL AS target_name, actor.name AS actor_name, 'published' AS event_action, entry.published_at AS occurred_at");
    }

    private function deactivations(): Builder
    {
        return DB::table('platform_contracts as entry')->leftJoin('users as actor', 'actor.id', '=', 'entry.deactivated_by_user_id')
            ->whereNotNull('entry.deactivated_at')
            ->selectRaw("'contract' AS source, entry.id AS event_id, entry.id AS target_id,
                NULL AS target_name, actor.name AS actor_name, 'deactivated' AS event_action, entry.deactivated_at AS occurred_at");
    }

    private function supportReplies(): Builder
    {
        return DB::table('support_ticket_messages as entry')->leftJoin('users as actor', 'actor.id', '=', 'entry.sender_user_id')
            ->where('entry.from_support', true)
            ->selectRaw("'support' AS source, entry.id AS event_id, entry.support_ticket_id AS target_id,
                NULL AS target_name, actor.name AS actor_name, 'replied' AS event_action, entry.created_at AS occurred_at");
    }

    private function supportAssignments(): Builder
    {
        return DB::table('support_ticket_assignments as entry')->leftJoin('users as actor', 'actor.id', '=', 'entry.assigned_by_user_id')
            ->selectRaw("'support' AS source, entry.id AS event_id, entry.support_ticket_id AS target_id,
                NULL AS target_name, actor.name AS actor_name, entry.action AS event_action, entry.created_at AS occurred_at");
    }

    private function roleChanges(): Builder
    {
        return DB::table('platform_role_changes as entry')->join('users as target', 'target.id', '=', 'entry.user_id')
            ->leftJoin('users as actor', 'actor.id', '=', 'entry.actor_user_id')
            ->selectRaw("'platform_role' AS source, entry.id AS event_id, entry.user_id AS target_id,
                target.name AS target_name, actor.name AS actor_name, entry.action AS event_action, entry.created_at AS occurred_at");
    }

    private function clinicMembershipChanges(): Builder
    {
        return DB::table('clinic_membership_changes as entry')->join('users as target', 'target.id', '=', 'entry.user_id')
            ->leftJoin('users as actor', 'actor.id', '=', 'entry.actor_user_id')
            ->selectRaw("'clinic_membership' AS source, entry.id AS event_id, entry.business_user_id AS target_id,
                target.name AS target_name, actor.name AS actor_name, entry.action AS event_action, entry.created_at AS occurred_at");
    }
}
