<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Offer;
use App\Models\Treatment;
use App\Services\AppointmentBookingService;
use App\Services\AppointmentSlotAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AppointmentBookingController extends Controller
{
    public function __construct(
        protected AppointmentBookingService $bookingService
    ) {}

    public function index(Request $request, Offer $offer): JsonResponse
    {
        $branchIds = null;

        if (! $this->isOfferPatient($request, $offer)) {
            $branchIds = $request->user()->accessibleBranchIds($offer->business_id);
            $offerBranchId = $offer->package_snapshot['branch_id'] ?? $offer->branch_id;
            abort_if(
                $branchIds === [] || ($offerBranchId !== null && ! in_array($offerBranchId, $branchIds, true)),
                403,
                'Bu teklifin randevularını görme yetkiniz yok.'
            );
        }

        $appointments = $offer->appointments()
            ->where('business_id', $offer->business_id)
            ->where('patient_profile_id', $offer->patient_profile_id)
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->whereDoesntHave('rescheduledAppointments', fn ($query) => $query
                ->where('offer_id', $offer->id)
                ->where('patient_profile_id', $offer->patient_profile_id))
            ->with(['branch:id,name', 'doctor.person:id,first_name,last_name', 'treatment:id,name'])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->map(function (Appointment $appointment) {
                $person = $appointment->doctor?->person;

                return [
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                    'starts_at' => $appointment->starts_at->toDateTimeString(),
                    'ends_at' => $appointment->ends_at->toDateTimeString(),
                    'branch_name' => $appointment->branch?->name,
                    'treatment_name' => $appointment->treatment?->name,
                    'doctor_name' => $person ? trim($person->first_name.' '.$person->last_name) : null,
                ];
            });

        return response()->json(['success' => true, 'data' => $appointments]);
    }

    public function store(
        Request $request,
        Offer $offer
    ): JsonResponse {
        $this->ensureBookableOffer($request, $offer);

        if ($offer->package_snapshot !== null) {
            return $this->storePackage($request, $offer);
        }

        if (! $offer->treatment_id) {
            abort(
                422,
                'Bu teklife bağlı bir tedavi bulunmuyor.'
            );
        }

        if ($offer->appointment()->exists()) {
            abort(
                422,
                'Bu teklif daha önce randevuya dönüştürülmüş.'
            );
        }

        $validated = $request->validate([
            'branch_id' => [
                'required',
                'integer',
                'exists:branches,id',
            ],
            'doctor_id' => [
                'required',
                'integer',
                'exists:doctors,id',
            ],
            'starts_at' => [
                'required',
                'date',
                'after:now',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        if (
            $offer->branch_id !== null &&
            (int) $validated['branch_id'] !== (int) $offer->branch_id
        ) {
            abort(
                422,
                'Bu teklif farklı bir şube için oluşturulmuştur.'
            );
        }

        $patient = $offer->patientProfile;

        if (! $patient) {
            abort(
                422,
                'Teklife bağlı hasta profili bulunamadı.'
            );
        }

        $doctor = Doctor::findOrFail(
            $validated['doctor_id']
        );

        try {
            $appointment = $this->bookingService->book(
                $offer->business_id,
                (int) $validated['branch_id'],
                $patient,
                $doctor,
                $offer->treatment,
                Carbon::parse($validated['starts_at']),
                'medloby',
                true,
                $validated['notes'] ?? null,
                $offer
            );

            return response()->json([
                'success' => true,
                'message' => 'Randevu başarıyla oluşturuldu.',
                'data' => [
                    'appointment' => $appointment,
                    'offer' => $offer->fresh()->load([
                        'business',
                        'branch',
                        'patientProfile',
                        'treatment',
                        'creator',
                        'appointment',
                    ]),
                ],
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function options(Request $request, Offer $offer, AppointmentSlotAvailabilityService $availability): JsonResponse
    {
        $this->ensureBookableOffer($request, $offer);
        abort_if($offer->appointments()->exists(), 422, 'Bu teklif daha önce randevuya dönüştürülmüş.');

        $validated = $request->hasAny(['branch_id', 'treatment_id', 'date'])
            ? $request->validate([
                'branch_id' => ['required', 'integer'],
                'treatment_id' => ['required', 'integer'],
                'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            ])
            : [];

        $treatments = $offer->package_snapshot['treatments'] ?? [];
        if ($offer->package_snapshot === null && $offer->treatment) {
            $treatments = [[
                'treatment_id' => $offer->treatment_id,
                'name' => $offer->treatment->name,
                'quantity' => 1,
            ]];
        }

        abort_if($treatments === [], 422, 'Bu teklife bağlı bir tedavi bulunmuyor.');

        $treatmentIds = array_column($treatments, 'treatment_id');
        $offerBranchId = $offer->package_snapshot['branch_id'] ?? $offer->branch_id;
        $branches = Branch::query()
            ->where('business_id', $offer->business_id)
            ->where('status', 'active')
            ->when($offerBranchId !== null, fn ($query) => $query->whereKey($offerBranchId))
            ->whereHas('treatments', fn ($query) => $query
                ->whereIn('treatments.id', $treatmentIds)
                ->where('treatments.is_active', true)
                ->where('branch_treatment.is_active', true)
                ->where('branch_treatment.is_online_bookable', true), '=', count($treatmentIds))
            ->whereDoesntHave('appointmentSetting', fn ($query) => $query
                ->where('is_active', true)->where('online_booking_enabled', false))
            ->orderBy('name')
            ->get(['id', 'name']);

        $data = ['branches' => $branches, 'treatments' => $treatments];

        if ($validated !== []) {
            $branchId = (int) $validated['branch_id'];
            $treatmentId = (int) $validated['treatment_id'];

            if (! $branches->contains('id', $branchId)) {
                throw ValidationException::withMessages(['branch_id' => 'Bu teklif için uygun bir şube seçin.']);
            }
            if (! in_array($treatmentId, $treatmentIds, true)) {
                throw ValidationException::withMessages(['treatment_id' => 'Teklifte yer alan bir tedavi seçin.']);
            }

            $date = Carbon::parse($validated['date'])->startOfDay();
            $treatment = Treatment::findOrFail($treatmentId);
            $now = Carbon::now();

            $data['date'] = $date->toDateString();
            $data['doctors'] = Doctor::query()
                ->with('person:id,first_name,last_name')
                ->where('status', 'active')
                ->where('is_public', true)
                ->whereHas('person', fn ($query) => $query->where('business_id', $offer->business_id))
                ->whereHas('branches', fn ($query) => $query->whereKey($branchId)->where('doctor_branch.status', 'active'))
                ->whereHas('treatments', fn ($query) => $query->whereKey($treatmentId)->where('doctor_treatment.is_active', true))
                ->orderBy('id')
                ->get(['id', 'person_id', 'specialty'])
                ->map(fn (Doctor $doctor) => [
                    'id' => $doctor->id,
                    'name' => trim($doctor->person->first_name.' '.$doctor->person->last_name),
                    'specialty' => $doctor->specialty,
                    'slots' => collect($availability->getAvailableSlots($doctor, $branchId, $treatment, $date))
                        ->filter(fn (Carbon $slot) => $slot->gt($now))
                        ->map(fn (Carbon $slot) => $slot->toDateTimeString())
                        ->values()->all(),
                ])
                ->filter(fn (array $doctor) => $doctor['slots'] !== [])
                ->values();
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    private function isOfferPatient(Request $request, Offer $offer): bool
    {
        return $offer->patientProfile()
            ->where('user_id', $request->user()->id)
            ->exists();
    }

    private function ensureBookableOffer(Request $request, Offer $offer): void
    {
        if (! $this->isOfferPatient($request, $offer)) {
            abort(
                403,
                'Bu teklif üzerinden randevu oluşturma yetkiniz yok.'
            );
        }

        if ($offer->status !== 'accepted') {
            abort(
                422,
                'Randevu oluşturmak için teklifin kabul edilmiş olması gerekir.'
            );
        }

        if (
            $offer->valid_until &&
            $offer->valid_until->isPast()
        ) {
            abort(
                422,
                'Bu teklifin geçerlilik süresi dolmuştur.'
            );
        }
    }

    private function storePackage(Request $request, Offer $offer): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'sessions' => ['required', 'array', 'list', 'min:1'],
            'sessions.*' => ['required', 'array:treatment_id,doctor_id,starts_at,notes'],
            'sessions.*.treatment_id' => ['required', 'integer', 'exists:treatments,id'],
            'sessions.*.doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'sessions.*.starts_at' => ['required', 'date', 'after:now'],
            'sessions.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $appointments = $this->bookingService->bookPackage(
                $offer,
                (int) $validated['branch_id'],
                $validated['sessions']
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Paket randevuları başarıyla oluşturuldu.',
            'data' => [
                'appointments' => $appointments,
                'offer' => $offer->fresh(['appointments']),
            ],
        ], 201);
    }
}
