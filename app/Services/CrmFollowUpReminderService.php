<?php

namespace App\Services;

use App\Models\CrmFollowUp;
use App\Notifications\CrmFollowUpReminder;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CrmFollowUpReminderService
{
    public function sendDue(): int
    {
        $cutoff = now();
        $sent = 0;

        foreach ($this->dueFollowUps($cutoff)->select('id')->lazyById(200) as $candidate) {
            $sent += (int) $this->sendReminder($candidate->id, $cutoff);
        }

        return $sent;
    }

    private function dueFollowUps(DateTimeInterface $cutoff): Builder
    {
        return CrmFollowUp::query()->where('status', 'pending')->whereNull('reminded_at')
            ->where('scheduled_at', '<=', $cutoff)
            ->whereHas('business', fn (Builder $business) => $business->where('status', 'active')->where('is_verified', true))
            ->whereHas('branch', fn (Builder $branch) => $branch->where('status', 'active')
                ->whereColumn('branches.business_id', 'crm_follow_ups.business_id'))
            ->whereHas('lead', fn (Builder $lead) => $lead->openForFollowUp()
                ->whereColumn('crm_leads.business_id', 'crm_follow_ups.business_id')
                ->whereColumn('crm_leads.branch_id', 'crm_follow_ups.branch_id'));
    }

    private function sendReminder(int $id, DateTimeInterface $cutoff): bool
    {
        return DB::transaction(function () use ($id, $cutoff): bool {
            $followUp = $this->dueFollowUps($cutoff)->with(['branch', 'business'])->lockForUpdate()->find($id);
            if ($followUp === null) {
                return false;
            }

            $members = $followUp->business->memberships()->activeForBranch($followUp->branch)
                ->whereIn('role', ['business_owner', 'staff', 'manager'])
                ->whereHas('user', fn (Builder $user) => $user->where(fn (Builder $account) => $account
                    ->whereNotNull('email_verified_at')->orWhere('staff_business_id', $followUp->business_id)))
                ->with('user');
            $assignee = $followUp->assigned_business_user_id === null
                ? null
                : (clone $members)->whereKey($followUp->assigned_business_user_id)->first();
            $recipients = $assignee === null
                ? $members->where('role', 'business_owner')->get()->pluck('user')
                : collect([$assignee->user]);
            if ($recipients->isEmpty()) {
                return false;
            }

            Notification::send($recipients, new CrmFollowUpReminder($followUp));
            $followUp->forceFill(['reminded_at' => now()])->save();

            return true;
        }, attempts: 3);
    }
}
