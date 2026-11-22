<?php

namespace App\Http\Controllers;

use App\Exceptions\AppointmentRuleViolation;
use App\Models\Appointment;
use App\Services\AppointmentLifecycleService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientAppointmentController extends Controller
{
    private const array SUMMARY_RELATIONS = [
        'business:id,name', 'branch:id,business_id,name,status', 'branch.business:id,status,is_verified',
        'branch.appointmentSetting', 'doctor:id,person_id,status,is_public', 'doctor.person:id,first_name,last_name',
        'treatment:id,name,is_active', 'offer:id,patient_profile_id,title',
    ];

    public function __construct(private AppointmentLifecycleService $lifecycle) {}

    public function index(Request $request): JsonResponse
    {
        $patient = $request->user()->patientProfile;
        abort_unless($patient, 403);

        $filters = $request->validate(['view' => ['sometimes', 'in:upcoming,history'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $now = Appointment::clinicLocalNow();
        $appointments = $patient->appointments()->with(self::SUMMARY_RELATIONS)
            ->where(fn (Builder $query) => $query->where('status', '!=', 'rescheduled')
                ->orWhereDoesntHave('rescheduledAppointments', fn (Builder $replacement) => $replacement
                    ->where('patient_profile_id', $patient->id)
                    ->whereColumn($replacement->qualifyColumn('id'), '>', 'appointments.id')
                    ->whereColumn($replacement->qualifyColumn('business_id'), 'appointments.business_id')
                    ->whereColumn($replacement->qualifyColumn('branch_id'), 'appointments.branch_id')
                    ->where(fn (Builder $offers) => $offers->whereNull($replacement->qualifyColumn('offer_id'))
                        ->orWhereColumn($replacement->qualifyColumn('offer_id'), 'appointments.offer_id'))));

        if (($filters['view'] ?? 'upcoming') === 'history') {
            $appointments->where(fn (Builder $query) => $query->whereNotIn('status', ['pending', 'confirmed'])
                ->orWhere('starts_at', '<=', $now))->latest('starts_at')->latest('id');
        } else {
            $appointments->whereIn('status', ['pending', 'confirmed'])->where('starts_at', '>', $now)
                ->orderBy('starts_at')->orderBy('id');
        }

        $page = $appointments->paginate(12, ['id', 'patient_profile_id', 'business_id', 'branch_id', 'doctor_id', 'treatment_id',
            'offer_id', 'starts_at', 'ends_at', 'status'])->through(fn (Appointment $appointment): array => $this->summary($appointment, $now));

        return $this->data(['appointments' => $page->items(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()]);
    }

    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        abort_unless($appointment->belongsToPatient($request->user()), 404);

        $current = $appointment->currentVersion()->load(self::SUMMARY_RELATIONS);

        return $this->data($this->summary($current, Appointment::clinicLocalNow()));
    }

    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        abort_unless($appointment->belongsToPatient($request->user()), 404);
        $data = $request->validate(['cancellation_reason' => ['required', 'string', 'max:1000']]);

        try {
            $cancelled = $this->lifecycle->cancel($appointment, $data['cancellation_reason'], $request->user(), byPatient: true);
        } catch (AppointmentRuleViolation $exception) {
            return $this->unavailable($exception);
        }

        return $this->data($cancelled->only(['id', 'status']));
    }

    public function options(Request $request, Appointment $appointment): JsonResponse
    {
        abort_unless($appointment->belongsToPatient($request->user()), 404);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        try {
            $slots = $this->lifecycle->patientRescheduleSlots($appointment, $request->user(), Carbon::parse($data['date']));
        } catch (AppointmentRuleViolation $exception) {
            return $this->unavailable($exception);
        }

        return $this->data(['slots' => $slots]);
    }

    public function reschedule(Request $request, Appointment $appointment): JsonResponse
    {
        abort_unless($appointment->belongsToPatient($request->user()), 404);
        $data = $request->validate([
            'starts_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $moved = $this->lifecycle->reschedule($appointment, Carbon::parse($data['starts_at']), $request->user(),
                reason: $data['reason'] ?? null, byPatient: true);
        } catch (AppointmentRuleViolation $exception) {
            return $this->unavailable($exception);
        }

        return $this->data([
            ...$moved->only(['id', 'status']),
            'starts_at' => $moved->starts_at->toDateTimeString(),
            'ends_at' => $moved->ends_at->toDateTimeString(),
        ]);
    }

    private function summary(Appointment $appointment, Carbon $now): array
    {
        $person = $appointment->doctor?->person;
        $offer = $appointment->offer;

        return [
            ...$appointment->only(['id', 'status']),
            'starts_at' => $appointment->starts_at->toDateTimeString(),
            'ends_at' => $appointment->ends_at->toDateTimeString(),
            'business_name' => $appointment->business?->name,
            'branch_name' => $appointment->branch?->name,
            'doctor_name' => $person ? trim($person->first_name.' '.$person->last_name) : null,
            'treatment_name' => $appointment->treatment?->name,
            'offer_title' => $offer && $offer->patient_profile_id === $appointment->patient_profile_id ? $offer->title : null,
            'patient_actions' => $appointment->patientActions($now),
        ];
    }

    private function data(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data])->header('Cache-Control', 'private, no-store');
    }

    private function unavailable(AppointmentRuleViolation $exception): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $exception->getMessage()], 422)->header('Cache-Control', 'private, no-store');
    }
}
