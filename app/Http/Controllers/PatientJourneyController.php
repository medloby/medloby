<?php

namespace App\Http\Controllers;

use App\Models\PatientProfile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;

class PatientJourneyController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $patient = $request->user()->patientProfile;
        abort_unless($patient && $request->user()->hasVerifiedEmail(), 403, 'Tedavi süreci için doğrulanmış hasta hesabı gerekir.');

        $filters = $request->validate([
            'category' => ['sometimes', 'in:conversation,offer,appointment'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $events = match ($filters['category'] ?? null) {
            'conversation' => $this->conversationEvents($patient),
            'offer' => $this->offerEvents($patient),
            'appointment' => $this->appointmentEvents($patient),
            default => $this->conversationEvents($patient)
                ->unionAll($this->offerEvents($patient))
                ->unionAll($this->appointmentEvents($patient)),
        };

        $page = DB::query()->fromSub($events, 'events')
            ->leftJoin('businesses', 'businesses.id', '=', 'events.business_id')
            ->orderByRaw('CASE WHEN events.occurred_at IS NULL THEN 1 ELSE 0 END')
            ->latest('events.occurred_at')->latest('events.event_order')->latest('events.event_id')
            ->simplePaginate(20, ['events.*', 'businesses.name as business_name'])
            ->through(fn (stdClass $item): array => [
                'kind' => $item->kind,
                'title' => $item->title,
                'business_name' => $item->business_name,
                'status' => $item->status,
                'occurred_at' => $item->occurred_at === null ? null : CarbonImmutable::parse($item->occurred_at)->toISOString(),
                'starts_at' => $item->starts_at,
                'target_id' => $item->target_id,
                'conversation_id' => $item->conversation_id,
            ]);

        return response()->json(['success' => true, 'data' => [
            'events' => $page->items(),
            'current_page' => $page->currentPage(),
            'has_more' => $page->hasMorePages(),
        ]])->header('Cache-Control', 'private, no-store');
    }

    private function conversationEvents(PatientProfile $patient): Builder
    {
        return $patient->conversations()->getQuery()->toBase()->selectRaw(
            "'conversation' AS kind, 1 AS event_order, id AS event_id, business_id,
            COALESCE(created_at, updated_at) AS occurred_at, subject AS title, status,
            id AS target_id, id AS conversation_id, CAST(NULL AS TIMESTAMP) AS starts_at"
        );
    }

    private function offerEvents(PatientProfile $patient): Builder
    {
        $offers = $patient->offers()->getQuery()->toBase()
            ->join('conversations', fn (JoinClause $join) => $join->on('conversations.id', '=', 'offers.conversation_id')
                ->on('conversations.patient_profile_id', '=', 'offers.patient_profile_id')
                ->on('conversations.business_id', '=', 'offers.business_id'))
            ->whereIn('offers.status', ['pending', 'accepted', 'rejected', 'expired']);
        $received = (clone $offers)->selectRaw(
            "'offer_received' AS kind, 2 AS event_order, offers.id AS event_id, offers.business_id,
            offers.created_at AS occurred_at, offers.title,
            CASE WHEN offers.status = 'pending' AND offers.valid_until < ? THEN 'expired' ELSE offers.status END AS status,
            CASE WHEN offers.package_snapshot IS NOT NULL THEN offers.id END AS target_id,
            offers.conversation_id, CAST(NULL AS TIMESTAMP) AS starts_at",
            [now()->startOfSecond()]
        );
        $responded = $offers->whereNotNull('offers.responded_at')->whereIn('offers.status', ['accepted', 'rejected'])
            ->selectRaw(
                "'offer_responded' AS kind, 3 AS event_order, offers.id AS event_id, offers.business_id,
                offers.responded_at AS occurred_at, offers.title, offers.status,
                CASE WHEN offers.package_snapshot IS NOT NULL THEN offers.id END AS target_id,
                offers.conversation_id, CAST(NULL AS TIMESTAMP) AS starts_at"
            );

        return $received->unionAll($responded);
    }

    private function appointmentEvents(PatientProfile $patient): Builder
    {
        $appointments = $patient->appointments()->getQuery()->toBase()
            ->leftJoin('treatments', 'treatments.id', '=', 'appointments.treatment_id');
        $booked = (clone $appointments)->selectRaw(
            "CASE WHEN appointments.rescheduled_from_appointment_id IS NULL THEN 'appointment_booked' ELSE 'appointment_moved' END AS kind,
            4 AS event_order, appointments.id AS event_id, appointments.business_id,
            COALESCE(appointments.created_at, appointments.updated_at) AS occurred_at, treatments.name AS title, appointments.status,
            appointments.id AS target_id, CAST(NULL AS BIGINT) AS conversation_id, appointments.starts_at"
        );
        $updated = $appointments->join('appointment_status_histories as history', 'history.appointment_id', '=', 'appointments.id')
            ->whereNotNull('history.old_status')
            ->whereIn('history.new_status', ['confirmed', 'cancelled', 'completed', 'no_show'])
            ->selectRaw(
                "'appointment_status' AS kind, 5 AS event_order, history.id AS event_id, appointments.business_id,
                history.changed_at AS occurred_at, treatments.name AS title, history.new_status AS status,
                appointments.id AS target_id, CAST(NULL AS BIGINT) AS conversation_id, CAST(NULL AS TIMESTAMP) AS starts_at"
            );

        return $booked->unionAll($updated);
    }
}
