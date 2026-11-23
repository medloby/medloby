<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use stdClass;

final class PlatformAdminAudit
{
    public const array SOURCES = ['clinic_application', 'clinic_status', 'contract', 'support'];

    public function recent(?string $source): LengthAwarePaginator
    {
        $events = match ($source) {
            'clinic_application' => $this->clinicApplications(),
            'clinic_status' => $this->clinicStatuses(),
            'contract' => $this->publications(),
            'support' => $this->supportReplies()->unionAll($this->supportAssignments()),
            default => $this->clinicApplications()->unionAll($this->clinicStatuses())
                ->unionAll($this->publications())->unionAll($this->supportReplies())->unionAll($this->supportAssignments()),
        };

        return DB::query()->fromSub($events, 'audit_events')
            ->orderByDesc('occurred_at')->orderBy('source')->orderByDesc('event_id')->orderBy('event_action')->paginate(20)
            ->through(fn (stdClass $event): array => [
                'source' => $event->source,
                'action' => $event->event_action,
                'target_id' => (int) $event->target_id,
                'target_name' => $event->target_name,
                'actor_name' => $event->actor_name,
                'occurred_at' => CarbonImmutable::parse($event->occurred_at)->toIso8601String(),
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
        return DB::table('platform_contracts as entry')->whereNotNull('entry.published_at')
            ->selectRaw("'contract' AS source, entry.id AS event_id, entry.id AS target_id,
                NULL AS target_name, NULL AS actor_name, 'published' AS event_action, entry.published_at AS occurred_at");
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
}
