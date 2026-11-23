<?php

namespace App\Http\Controllers;

use App\Exceptions\AppointmentRuleViolation;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\PatientProfile;
use App\Models\Treatment;
use App\Models\WaitingList;
use App\Services\AppointmentBookingService;
use App\Services\AppointmentSlotAvailabilityService;
use App\Services\AppointmentWaitingList;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientBookingController extends Controller
{
    public function branches(Request $request): JsonResponse
    {
        $this->patient($request);
        $branches = Branch::query()->bookableOnline()->with('business:id,name')->orderBy('city')->orderBy('name')->orderBy('id')
            ->get(['id', 'business_id', 'name', 'city'])
            ->map(fn (Branch $branch): array => [
                'id' => $branch->id, 'name' => $branch->name, 'clinic' => $branch->business->name, 'city' => $branch->city,
            ]);

        return $this->data(['branches' => $branches]);
    }

    public function treatments(Request $request, int $branch): JsonResponse
    {
        $this->patient($request);
        $branch = Branch::query()->bookableOnline()->with('business:id,name')->findOrFail($branch);
        $doctorIds = $this->publicDoctors($branch)->select('doctors.id')->getQuery();
        $treatments = $this->bookableTreatments($branch)->with(['doctors' => fn (BelongsToMany $doctors) => $doctors
            ->wherePivot('is_active', true)->whereIn('doctors.id', $doctorIds)
            ->select(['doctors.id', 'doctors.person_id'])->with('person:id,first_name,last_name')->orderBy('doctors.id')])
            ->orderBy('treatments.sort_order')->orderBy('treatments.name')->orderBy('treatments.id')->get(['treatments.id', 'treatments.name'])
            ->filter(fn (Treatment $treatment): bool => $treatment->doctors->isNotEmpty())
            ->map(fn (Treatment $treatment): array => [
                'id' => $treatment->id, 'name' => $treatment->name,
                'doctors' => $treatment->doctors->map(fn (Doctor $doctor): array => [
                    'id' => $doctor->id, 'name' => trim($doctor->person->first_name.' '.$doctor->person->last_name),
                ]),
            ])->values();

        return $this->data([
            'branch' => ['name' => $branch->name, 'clinic' => $branch->business->name, 'city' => $branch->city, 'district' => $branch->district],
            'treatments' => $treatments,
        ]);
    }

    public function slots(Request $request, int $branch, AppointmentSlotAvailabilityService $availability, AppointmentWaitingList $waitingList): JsonResponse
    {
        $patient = $this->patient($request);
        $data = $request->validate([
            'treatment_id' => ['required', 'integer'], 'doctor_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);
        $branch = Branch::query()->bookableOnline()->findOrFail($branch);
        [$treatment, $doctor] = $this->selection($branch, (int) $data['treatment_id'], (int) $data['doctor_id']);
        $date = Carbon::parse($data['date']);
        $clinicSlots = $availability->getAvailableSlots($doctor, $branch->id, $treatment, $date);
        $slots = $availability->excludePatientConflicts($clinicSlots, $doctor, $branch->id, $treatment, $patient->id);

        return $this->data([
            'slots' => array_map(fn (Carbon $slot): string => $slot->toDateTimeString(), $slots),
            'can_wait' => $clinicSlots === [] && $waitingList->canWait($branch, $date),
            'waiting_list_id' => $waitingList->pendingRequest($patient, $branch, $doctor, $treatment, $date)?->id,
        ]);
    }

    public function joinWaitingList(Request $request, int $branch, AppointmentWaitingList $waitingList): JsonResponse
    {
        $patient = $this->patient($request);
        $data = $request->validate([
            'treatment_id' => ['required', 'integer'], 'doctor_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);
        $branch = Branch::query()->bookableOnline()->findOrFail($branch);
        [$treatment, $doctor] = $this->selection($branch, (int) $data['treatment_id'], (int) $data['doctor_id']);
        $entry = $waitingList->join($patient, $branch, $doctor, $treatment, Carbon::parse($data['date']));

        return $this->data($entry->only('id'), $entry->wasRecentlyCreated ? 201 : 200);
    }

    public function showWaitingRequest(Request $request, WaitingList $waitingList): JsonResponse
    {
        $this->authorizeWaitingRequest($request, $waitingList);
        abort_unless($waitingList->doctor_id && $waitingList->treatment_id, 404, 'Bu randevu seçimi artık kullanılamıyor.');

        return $this->data([
            'branch_id' => $waitingList->branch_id,
            'doctor_id' => $waitingList->doctor_id,
            'treatment_id' => $waitingList->treatment_id,
            'date' => $waitingList->preferred_date->toDateString(),
        ]);
    }

    public function cancelWaitingRequest(Request $request, WaitingList $waitingList): JsonResponse
    {
        $this->authorizeWaitingRequest($request, $waitingList);
        WaitingList::query()->whereKey($waitingList->id)->where('status', 'waiting')->update(['status' => 'cancelled']);

        return $this->data($waitingList->only('id'));
    }

    public function store(Request $request, int $branch, AppointmentBookingService $booking): JsonResponse
    {
        $patient = $this->patient($request);
        $data = $request->validate([
            'treatment_id' => ['required', 'integer'], 'doctor_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date_format:Y-m-d H:i:s'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $branch = Branch::query()->bookableOnline()->findOrFail($branch);
        [$treatment, $doctor] = $this->selection($branch, (int) $data['treatment_id'], (int) $data['doctor_id']);

        try {
            $appointment = $booking->book($branch->business_id, $branch->id, $patient, $doctor, $treatment,
                Carbon::parse($data['starts_at']), notes: $data['notes'] ?? null, changedBy: $request->user()->id);
        } catch (AppointmentRuleViolation $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422)->header('Cache-Control', 'private, no-store');
        }

        return $this->data($appointment->only(['id', 'status']), 201);
    }

    private function patient(Request $request): PatientProfile
    {
        $patient = $request->user()->patientProfile;
        abort_unless($patient && $request->user()->hasVerifiedEmail(), 403);

        return $patient;
    }

    private function authorizeWaitingRequest(Request $request, WaitingList $waitingList): void
    {
        abort_unless($this->patient($request)->id === $waitingList->patient_profile_id
            && $waitingList->source === WaitingList::ONLINE_BOOKING, 404);
    }

    private function publicDoctors(Branch $branch): BelongsToMany
    {
        return $branch->doctors()->wherePivot('status', 'active')->where('doctors.status', 'active')->where('doctors.is_public', true)
            ->whereHas('person', fn (Builder $person) => $person->where('business_id', $branch->business_id));
    }

    private function bookableTreatments(Branch $branch): BelongsToMany
    {
        return $branch->treatments()->wherePivot('is_active', true)->wherePivot('is_online_bookable', true)->where('treatments.is_active', true);
    }

    /** @return array{Treatment, Doctor} */
    private function selection(Branch $branch, int $treatmentId, int $doctorId): array
    {
        $treatment = $this->bookableTreatments($branch)->findOrFail($treatmentId);
        $doctor = $this->publicDoctors($branch)->whereHas('treatments', fn (Builder $treatments) => $treatments
            ->where('treatments.id', $treatment->id)->where('doctor_treatment.is_active', true))->findOrFail($doctorId);

        return [$treatment, $doctor];
    }

    private function data(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status)->header('Cache-Control', 'private, no-store');
    }
}
