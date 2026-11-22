<?php

namespace App\Http\Controllers;

use App\Exceptions\AppointmentRuleViolation;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Offer;
use App\Models\PatientProfile;
use App\Services\AppointmentService;
use App\Services\AppointmentSlotAvailabilityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClinicAppointmentController extends Controller
{
    public function options(Request $request, Branch $branch, AppointmentSlotAvailabilityService $availability): JsonResponse
    {
        $this->authorizeCreation($request, $branch);

        $treatments = $branch->treatments()
            ->where('treatments.is_active', true)->wherePivot('is_active', true);
        if (! $request->hasAny(['date', 'treatment_id'])) {
            return $this->data(['treatments' => $treatments->orderBy('name')->get(['treatments.id', 'name'])
                ->map(fn ($treatment) => $treatment->only('id', 'name'))]);
        }

        $now = Appointment::clinicLocalNow();
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$now->toDateString()],
            'treatment_id' => ['required', 'integer'],
        ]);
        $treatment = $treatments->find($validated['treatment_id']);
        if (! $treatment) {
            throw ValidationException::withMessages(['treatment_id' => 'Bu şubede aktif bir tedavi seçin.']);
        }

        $date = Carbon::parse($validated['date']);
        $doctors = $branch->doctors()
            ->where('doctors.status', 'active')->wherePivot('status', 'active')
            ->whereHas('person', fn (Builder $person) => $person->where('business_id', $branch->business_id))
            ->whereHas('treatments', fn (Builder $query) => $query->whereKey($treatment->id)->where('doctor_treatment.is_active', true))
            ->with('person:id,first_name,last_name')->orderBy('doctors.id')
            ->get(['doctors.id', 'doctors.person_id', 'doctors.status'])
            ->map(fn (Doctor $doctor) => [
                'id' => $doctor->id,
                'name' => trim($doctor->person->first_name.' '.$doctor->person->last_name),
                'slots' => collect($availability->getAvailableSlots($doctor, $branch->id, $treatment, $date, requireOnlineBookable: false))
                    ->map(fn (Carbon $slot) => $slot->toDateTimeString())->values(),
            ])->filter(fn (array $doctor) => $doctor['slots']->isNotEmpty())->values();

        return $this->data(['doctors' => $doctors]);
    }

    public function patients(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeCreation($request, $branch);
        $search = $request->validate(['search' => ['required', 'string', 'min:2', 'max:80']])['search'];
        $patients = PatientProfile::query()->knownToBranch($branch);

        foreach (preg_split('/\s+/u', $search, flags: PREG_SPLIT_NO_EMPTY) as $word) {
            $patients->where(fn (Builder $name) => $name
                ->whereLike('first_name', '%'.$word.'%')->orWhereLike('last_name', '%'.$word.'%'));
        }

        return $this->data($patients->orderBy('first_name')->orderBy('last_name')->orderBy('id')->limit(25)
            ->get(['id', 'first_name', 'last_name'])->map(fn (PatientProfile $patient) => [
                'id' => $patient->id, 'name' => trim($patient->first_name.' '.$patient->last_name),
            ])->all());
    }

    public function packages(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeCreation($request, $branch);
        $search = $request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? '';
        $offers = Offer::query()->readyForClinicBooking($branch)
            ->when($search !== '', fn (Builder $offers) => $offers->whereLike('title', '%'.$search.'%'))
            ->with('patientProfile:id,first_name,last_name')
            ->latest('id')->limit(25)->get(['id', 'title', 'patient_profile_id']);

        return $this->data($offers->map(fn (Offer $offer) => [
            'id' => $offer->id, 'title' => $offer->title,
            'patient' => trim($offer->patientProfile->first_name.' '.$offer->patientProfile->last_name),
        ])->all());
    }

    public function store(Request $request, Branch $branch, AppointmentService $appointments): JsonResponse
    {
        $this->authorizeCreation($request, $branch);
        $validated = $request->validate([
            'patient_profile_id' => ['nullable', 'integer'],
            'patient_name' => ['required_without:patient_profile_id', 'nullable', 'string', 'max:255'],
            'doctor_id' => ['required', 'integer'],
            'treatment_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date_format:Y-m-d H:i:s', 'after:'.Appointment::clinicLocalNow()->toDateTimeString()],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (isset($validated['patient_profile_id']) && ! PatientProfile::query()->knownToBranch($branch)
            ->whereKey($validated['patient_profile_id'])->exists()) {
            throw ValidationException::withMessages(['patient_profile_id' => 'Seçilen hasta bu şubede ilişkilendirilemiyor.']);
        }

        try {
            $appointment = $appointments->createAppointment([
                ...$validated,
                'business_id' => $branch->business_id,
                'branch_id' => $branch->id,
                'source' => 'clinic',
                'require_online_bookable' => false,
                'created_by_user_id' => $request->user()->id,
            ]);
        } catch (AppointmentRuleViolation $exception) {
            throw ValidationException::withMessages(['starts_at' => $exception->getMessage()]);
        }

        return $this->data(['id' => $appointment->id, 'starts_at' => $appointment->starts_at->toDateTimeString()], 201);
    }

    private function authorizeCreation(Request $request, Branch $branch): void
    {
        abort_unless($request->user()->canManageClinicAppointments($branch, 'appointments.create'), 403, 'Randevu oluşturma yetkiniz bulunmuyor.');
    }

    private function data(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status)->header('Cache-Control', 'private, no-store');
    }
}
