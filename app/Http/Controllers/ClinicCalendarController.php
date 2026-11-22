<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureClinicCrmAccess;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CalendarBlock;
use App\Models\Doctor;
use App\Services\BookingCalendarService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicCalendarController extends Controller
{
    public function show(Request $request, Branch $branch, BookingCalendarService $calendar): JsonResponse
    {
        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $date = Carbon::parse($validated['date'])->startOfDay();
        $starts = $date->toDateTimeString();
        $ends = $date->copy()->addDay()->toDateTimeString();
        $canManage = $request->attributes->get(EnsureClinicCrmAccess::class)->role === 'business_owner';
        $permissions = $request->user()->allowedClinicAppointmentPermissions($branch, [
            ...Appointment::ACTION_PERMISSIONS, 'create' => 'appointments.create',
        ]);

        $doctorPerson = fn ($people) => $people->where('business_id', $branch->business_id)
            ->select('id', 'first_name', 'last_name');

        $appointments = $branch->appointments()->where('business_id', $branch->business_id)
            ->where('starts_at', '<', $ends)->where('ends_at', '>', $starts)
            ->with([
                'doctor.person' => $doctorPerson,
                'patientProfile' => fn ($patients) => $patients->knownToBranch($branch)->select('id', 'first_name', 'last_name'),
                'treatment:id,name',
                'offer' => fn ($offers) => $offers->where('business_id', $branch->business_id)
                    ->where(fn ($scope) => $scope->whereNull('branch_id')->orWhere('branch_id', $branch->id))
                    ->whereNotNull('package_snapshot')->select('id', 'patient_profile_id', 'title'),
            ])
            ->orderBy('starts_at')->orderBy('id')
            ->get(['id', 'doctor_id', 'patient_profile_id', 'patient_name', 'treatment_id', 'offer_id', 'starts_at', 'ends_at', 'status'])
            ->map(fn (Appointment $appointment) => [
                'id' => $appointment->id,
                'starts_at' => $appointment->starts_at->toDateTimeString(),
                'ends_at' => $appointment->ends_at->toDateTimeString(),
                'status' => $appointment->status,
                'patient' => $appointment->patientProfile
                    ? trim($appointment->patientProfile->first_name.' '.$appointment->patientProfile->last_name)
                    : $appointment->patient_name,
                'doctor' => $this->doctorName($appointment->doctor),
                'treatment' => $appointment->treatment?->name,
                'package' => $appointment->offer && $appointment->offer->patient_profile_id === $appointment->patient_profile_id
                    ? $appointment->offer->title : null,
                'actions' => array_values(array_intersect($appointment->availableActions(), array_keys($permissions))),
            ]);

        $blocks = $branch->calendarBlocks()->where('business_id', $branch->business_id)->where('is_active', true)
            ->where('starts_at', '<', $ends)->where('ends_at', '>', $starts)
            ->with(['doctor.person' => $doctorPerson])
            ->orderBy('starts_at')->orderBy('id')
            ->get(['id', 'doctor_id', 'starts_at', 'ends_at', 'title', 'reason'])
            ->map(fn (CalendarBlock $block) => [
                'id' => $block->id,
                'doctor_id' => $block->doctor_id,
                'doctor' => $this->doctorName($block->doctor),
                'starts_at' => $block->starts_at->toDateTimeString(),
                'ends_at' => $block->ends_at->toDateTimeString(),
                'title' => $block->title,
                'reason' => $canManage ? $block->reason : null,
            ]);

        $doctors = $canManage ? $branch->doctors()->wherePivot('status', 'active')
            ->where('doctors.status', 'active')->whereHas('person', $doctorPerson)
            ->with(['person' => $doctorPerson])->get(['doctors.id', 'doctors.person_id'])
            ->map(fn (Doctor $doctor) => ['id' => $doctor->id, 'name' => $this->doctorName($doctor)])
            ->sortBy('name')->values() : [];

        return response()->json([
            'success' => true,
            'data' => [
                'open' => $calendar->isDateOpen($branch->id, $date),
                'online_booking_enabled' => $branch->appointmentSetting()->where('is_active', true)->value('online_booking_enabled') ?? true,
                'can_create_appointments' => isset($permissions['create']),
                'appointments' => $appointments,
                'blocks' => $blocks,
                'doctors' => $doctors,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function contact(Branch $branch, Appointment $appointment): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->appointmentContact($branch, $appointment),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function appointment(Branch $branch, Appointment $appointment): JsonResponse
    {
        $contact = $this->appointmentContact($branch, $appointment);
        $appointment->load([
            'doctor.person' => fn ($people) => $people->where('business_id', $branch->business_id)
                ->select('id', 'first_name', 'last_name'),
            'treatment:id,name',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                ...$contact,
                'id' => $appointment->id,
                'clinic' => $branch->business->name,
                'branch' => $branch->name,
                'starts_at' => $appointment->starts_at->toDateTimeString(),
                'ends_at' => $appointment->ends_at->toDateTimeString(),
                'status' => $appointment->status,
                'doctor' => $this->doctorName($appointment->doctor),
                'treatment' => $appointment->treatment?->name,
                'notes' => $appointment->notes,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    /** @return array{name: ?string, phone: ?string, email: ?string} */
    private function appointmentContact(Branch $branch, Appointment $appointment): array
    {
        abort_unless($appointment->branch_id === $branch->id && $appointment->business_id === $branch->business_id, 404);

        $patient = $appointment->patientProfile()->knownToBranch($branch)
            ->with('user:id,email')->first(['id', 'user_id', 'first_name', 'last_name', 'phone']);

        return [
            'name' => $patient ? trim($patient->first_name.' '.$patient->last_name) : $appointment->patient_name,
            'phone' => filled($appointment->patient_phone) ? $appointment->patient_phone : $patient?->phone,
            'email' => filled($appointment->patient_email) ? $appointment->patient_email : $patient?->user?->email,
        ];
    }

    private function doctorName(?Doctor $doctor): ?string
    {
        $person = $doctor?->person;

        return $person ? trim($person->first_name.' '.$person->last_name) : null;
    }
}
