<?php
namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Treatment;
use App\Models\Appointment;
use App\Services\AppointmentLifecycleService;
use App\Services\AppointmentService;
use App\Services\AppointmentSlotAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
class AppointmentController extends Controller
{
    public function __construct(
    protected AppointmentService $appointmentService,
    protected AppointmentLifecycleService $lifecycleService,
    protected AppointmentSlotAvailabilityService $slotAvailabilityService
) {
}
    public function index(Request $request): JsonResponse
{
    $validated = $request->validate([
        'business_id' => [
            'nullable',
            'integer',
            'exists:businesses,id',
        ],
        'branch_id' => [
            'nullable',
            'integer',
            'exists:branches,id',
        ],
        'doctor_id' => [
            'nullable',
            'integer',
            'exists:doctors,id',
        ],
        'treatment_id' => [
            'nullable',
            'integer',
            'exists:treatments,id',
        ],
        'status' => [
            'nullable',
            'string',
            'in:pending,confirmed,cancelled,completed,rescheduled,no_show',
        ],
        'from' => [
            'nullable',
            'date',
        ],
        'to' => [
            'nullable',
            'date',
            'after_or_equal:from',
        ],
        'per_page' => [
            'nullable',
            'integer',
            'min:1',
            'max:100',
        ],
    ]);

    $user = $request->user();

    /*
     * Kullanıcının aktif işletmelerini bul.
     */
    $businessIds = $user->businessMemberships()
        ->where('is_active', true)
        ->pluck('business_id');

    /*
     * Kullanıcının erişebildiği şubeleri bul.
     */
    $accessibleBusinessBranchIds = [];

    foreach ($businessIds as $businessId) {
        $accessibleBusinessBranchIds = array_merge(
            $accessibleBusinessBranchIds,
            $user->accessibleBranchIds((int) $businessId)
        );
    }

    $accessibleBusinessBranchIds = array_values(
        array_unique($accessibleBusinessBranchIds)
    );

    /*
     * Hasta ise yalnızca kendi randevularını,
     * işletme kullanıcısı ise yetkili olduğu
     * şubelerin randevularını görebilir.
     */
    $appointments = Appointment::query()
        ->with([
            'business',
            'branch',
            'patientProfile',
            'doctor.person',
            'treatment',
            'statusHistories',
        ])
        ->where(function ($query) use (
            $user,
            $accessibleBusinessBranchIds
        ) {
            $query->whereHas(
                'patientProfile',
                function ($patientQuery) use ($user) {
                    $patientQuery->where(
                        'user_id',
                        $user->id
                    );
                }
            );

            if (! empty($accessibleBusinessBranchIds)) {
                $query->orWhereIn(
                    'branch_id',
                    $accessibleBusinessBranchIds
                );
            }
        })

        /*
         * İşletme filtresi.
         */
        ->when(
            isset($validated['business_id']),
            function ($query) use ($validated) {
                $query->where(
                    'business_id',
                    $validated['business_id']
                );
            }
        )

        /*
         * Şube filtresi.
         */
        ->when(
            isset($validated['branch_id']),
            function ($query) use ($validated) {
                $query->where(
                    'branch_id',
                    $validated['branch_id']
                );
            }
        )

        /*
         * Doktor filtresi.
         */
        ->when(
            isset($validated['doctor_id']),
            function ($query) use ($validated) {
                $query->where(
                    'doctor_id',
                    $validated['doctor_id']
                );
            }
        )

        /*
         * Tedavi filtresi.
         */
        ->when(
            isset($validated['treatment_id']),
            function ($query) use ($validated) {
                $query->where(
                    'treatment_id',
                    $validated['treatment_id']
                );
            }
        )

        /*
         * Durum filtresi.
         */
        ->when(
            isset($validated['status']),
            function ($query) use ($validated) {
                $query->where(
                    'status',
                    $validated['status']
                );
            }
        )

        /*
         * Başlangıç tarihi.
         */
        ->when(
            isset($validated['from']),
            function ($query) use ($validated) {
                $query->whereDate(
                    'starts_at',
                    '>=',
                    $validated['from']
                );
            }
        )

        /*
         * Bitiş tarihi.
         */
        ->when(
            isset($validated['to']),
            function ($query) use ($validated) {
                $query->whereDate(
                    'starts_at',
                    '<=',
                    $validated['to']
                );
            }
        )

        ->orderBy('starts_at')

        ->paginate(
            $validated['per_page'] ?? 50
        );

    return response()->json([
        'success' => true,
        'data' => $appointments,
    ]);
}
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_id' => [
                'required',
                'integer',
                'exists:businesses,id',
            ],
            'branch_id' => [
                'required',
                'integer',
                'exists:branches,id',
            ],
            'patient_profile_id' => [
                'nullable',
                'integer',
                'exists:patient_profiles,id',
            ],
            'doctor_id' => [
                'required',
                'integer',
                'exists:doctors,id',
            ],
            'treatment_id' => [
                'required',
                'integer',
                'exists:treatments,id',
            ],
            'starts_at' => [
                'required',
                'date',
            ],
            'status' => [
                'nullable',
                'string',
                'in:pending,confirmed',
            ],
            'source' => [
                'nullable',
                'string',
                'max:50',
            ],
            'patient_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'patient_phone' => [
                'nullable',
                'string',
                'max:50',
            ],
            'patient_email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'require_online_bookable' => [
                'nullable',
                'boolean',
            ],
        ]);
        $user = $request->user();
        $isPatient = $user->patientProfile()
            ->whereKey($validated['patient_profile_id'] ?? null)
            ->exists();
        $isBusinessUser = $user->businessMemberships()
            ->where('business_id', $validated['business_id'])
            ->where('is_active', true)
            ->exists();
        if (! $isPatient && ! $isBusinessUser) {
            return response()->json([
                'success' => false,
                'message' => 'Bu işletme için randevu oluşturma yetkiniz yok.',
            ], 403);
        }
        if ($isBusinessUser) {
            $hasBranchAccess = $user->hasBusinessBranchAccess(
                (int) $validated['business_id'],
                (int) $validated['branch_id']
            );
            if (! $hasBranchAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu şube için randevu oluşturma yetkiniz yok.',
                ], 403);
            }
            if (! $user->hasBusinessPermission(
                (int) $validated['business_id'],
                'appointments.create'
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'Randevu oluşturma yetkiniz bulunmuyor.',
                ], 403);
            }
        }
        if ($isPatient) {
            $validated['patient_profile_id'] = $user
                ->patientProfile()
                ->value('id');
        }
        $validated['created_by_user_id'] = $user->id;
        if (! isset($validated['source'])) {
            $validated['source'] = $isPatient
                ? 'medloby'
                : 'clinic';
        }
        try {
            $appointment = $this->appointmentService->createAppointment(
                $validated
            );
            return response()->json([
                'success' => true,
                'message' => 'Randevu başarıyla oluşturuldu.',
                'data' => $appointment,
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
    public function show(
    Request $request,
    Appointment $appointment
): JsonResponse {
    $user = $request->user();

    $isPatient = $appointment->patientProfile()
        ->where('user_id', $user->id)
        ->exists();

    $isBusinessUser = $user->businessMemberships()
        ->where('business_id', $appointment->business_id)
        ->where('is_active', true)
        ->exists();

    if (! $isPatient && ! $isBusinessUser) {
        abort(
            403,
            'Bu randevuyu görüntüleme yetkiniz yok.'
        );
    }

    if (
        $isBusinessUser &&
        ! $user->hasBusinessBranchAccess(
            (int) $appointment->business_id,
            (int) $appointment->branch_id
        )
    ) {
        abort(
            403,
            'Bu randevuyu görüntüleme yetkiniz yok.'
        );
    }

    $appointment->load([
        'business',
        'branch',
        'patientProfile',
        'doctor.person',
        'treatment',
        'offer',
        'statusHistories',
        'cancelledBy',
        'rescheduledFrom',
        'rescheduledAppointments',
    ]);

    return response()->json([
        'success' => true,
        'data' => $appointment,
    ]);
}
    public function confirm(
        Request $request,
        Appointment $appointment
    ): JsonResponse {
        $this->ensureBusinessBranchAccess(
            $request,
            $appointment
        );

        try {
            $appointment = $this->lifecycleService->confirm(
                $appointment,
                $request->user(),
                $request->input('reason'),
                $request->input('notes')
            );
            return response()->json([
                'success' => true,
                'message' => 'Randevu başarıyla onaylandı.',
                'data' => $appointment,
            ]);
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception);
        }
    }
    public function cancel(
        Request $request,
        Appointment $appointment
    ): JsonResponse {
        $validated = $request->validate([
            'cancellation_reason' => [
                'required',
                'string',
                'max:1000',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);
        $this->ensureBusinessBranchAccess(
            $request,
            $appointment
        );

        try {
            $appointment = $this->lifecycleService->cancel(
                $appointment,
                $validated['cancellation_reason'],
                $request->user(),
                $validated['notes'] ?? null
            );
            return response()->json([
                'success' => true,
                'message' => 'Randevu başarıyla iptal edildi.',
                'data' => $appointment,
            ]);
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception);
        }
    }
    public function complete(
        Request $request,
        Appointment $appointment
    ): JsonResponse {
        $this->ensureBusinessBranchAccess(
            $request,
            $appointment
        );

        try {
            $appointment = $this->lifecycleService->complete(
                $appointment,
                $request->user(),
                $request->input('reason'),
                $request->input('notes')
            );
            return response()->json([
                'success' => true,
                'message' => 'Randevu başarıyla tamamlandı.',
                'data' => $appointment,
            ]);
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception);
        }
    }
        public function availability(Request $request): JsonResponse
{
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
        'treatment_id' => [
            'required',
            'integer',
            'exists:treatments,id',
        ],
        'date' => [
            'required',
            'date',
        ],
        'require_online_bookable' => [
            'nullable',
            'boolean',
        ],
    ]);

    $user = $request->user();

    $branch = Branch::query()
        ->findOrFail($validated['branch_id']);

    $isBusinessUser = $user->businessMemberships()
        ->where('business_id', $branch->business_id)
        ->where('is_active', true)
        ->exists();

    $isPatient = $user->patientProfile()->exists();

    if (! $isBusinessUser && ! $isPatient) {
        return response()->json([
            'success' => false,
            'message' => 'Müsait randevu saatlerini görüntüleme yetkiniz yok.',
        ], 403);
    }

    if ($isBusinessUser) {
        if (! $user->hasBusinessBranchAccess(
            (int) $branch->business_id,
            (int) $branch->id
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Bu şubeye erişim yetkiniz yok.',
            ], 403);
        }
    }

    $doctor = Doctor::query()
        ->findOrFail($validated['doctor_id']);

    $treatment = Treatment::query()
        ->findOrFail($validated['treatment_id']);

    $date = Carbon::parse($validated['date'])->startOfDay();

    /*
     * Slot aralığı artık request'ten alınmıyor.
     * Şubenin randevu ayarları esas alınıyor.
     *
     * Ayar kaydı yoksa servis kendi güvenli varsayılanı
     * olan 30 dakikayı kullanır.
     */
    $appointmentSettings = $branch->appointmentSetting;

    $slotInterval = $appointmentSettings?->slot_interval_minutes ?? 30;

    /*
     * Online randevu kontrolü request'ten açıkça false gönderilse bile
     * şube ayarı online randevuyu kapatmışsa servis bunu engelleyecektir.
     */
    $requireOnlineBookable =
        $validated['require_online_bookable'] ?? true;

    try {
        $slots = $this->slotAvailabilityService->getAvailableSlots(
            doctor: $doctor,
            branchId: (int) $validated['branch_id'],
            treatment: $treatment,
            date: $date,
            slotIntervalMinutes: null,
            requireOnlineBookable: $requireOnlineBookable
        );

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date->toDateString(),
                'branch_id' => (int) $validated['branch_id'],
                'doctor_id' => $doctor->id,
                'treatment_id' => $treatment->id,
                'slot_interval' => (int) $slotInterval,
                'slots' => array_map(
                    fn (Carbon $slot) => $slot->format('Y-m-d H:i:s'),
                    $slots
                ),
            ],
        ]);
    } catch (RuntimeException $exception) {
        return response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
        ], 422);
    }
}
public function options(Request $request): JsonResponse
{
    $user = $request->user();

    $businessIds = $user->businessMemberships()
        ->where('is_active', true)
        ->pluck('business_id');

    if ($businessIds->isEmpty()) {
        return response()->json([
            'success' => true,
            'data' => [
                'branches' => [],
                'doctors' => [],
                'treatments' => [],
                'statuses' => [
                    'pending',
                    'confirmed',
                    'cancelled',
                    'completed',
                    'rescheduled',
                    'no_show',
                ],
            ],
        ]);
    }

    $businessId = $request->integer('business_id');

    if ($businessId && ! $businessIds->contains($businessId)) {
        abort(
            403,
            'Bu işletmeye erişim yetkiniz yok.'
        );
    }

    $selectedBusinessIds = $businessId
        ? collect([$businessId])
        : $businessIds;

    $branchIds = [];

    foreach ($selectedBusinessIds as $selectedBusinessId) {
        $branchIds = array_merge(
            $branchIds,
            $user->accessibleBranchIds((int) $selectedBusinessId)
        );
    }

    $branchIds = array_values(
        array_unique($branchIds)
    );

    $branches = Branch::query()
        ->whereIn('id', $branchIds)
        ->where('status', 'active')
        ->orderBy('name')
        ->get([
            'id',
            'business_id',
            'name',
            'slug',
            'city',
            'district',
        ]);

    $doctors = Doctor::query()
        ->with('person:id,first_name,last_name')
        ->where('status', 'active')
        ->whereHas('branches', function ($query) use ($branchIds) {
            $query
                ->whereIn('branches.id', $branchIds)
                ->where('doctor_branch.status', 'active');
        })
        ->orderBy('id')
        ->get([
            'id',
            'person_id',
            'specialty',
            'status',
            'is_public',
        ]);

    $treatments = Treatment::query()
        ->where('is_active', true)
        ->whereHas('branches', function ($query) use ($branchIds) {
            $query
                ->whereIn('branches.id', $branchIds)
                ->where('branch_treatment.is_active', true);
        })
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get([
            'id',
            'treatment_category_id',
            'name',
            'slug',
            'duration_minutes',
            'is_active',
        ]);

    return response()->json([
        'success' => true,
        'data' => [
            'branches' => $branches,
            'doctors' => $doctors,
            'treatments' => $treatments,
            'statuses' => [
                'pending',
                'confirmed',
                'cancelled',
                'completed',
                'rescheduled',
                'no_show',
            ],
        ],
    ]);
}
    public function noShow(
        Request $request,
        Appointment $appointment
    ): JsonResponse {
        $this->ensureBusinessBranchAccess(
            $request,
            $appointment
        );

        try {
            $appointment = $this->lifecycleService->noShow(
                $appointment,
                $request->user(),
                $request->input('reason'),
                $request->input('notes')
            );
            return response()->json([
                'success' => true,
                'message' => 'Randevu gelmedi olarak işaretlendi.',
                'data' => $appointment,
            ]);
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception);
        }
    }
    public function reschedule(
        Request $request,
        Appointment $appointment
    ): JsonResponse {
        $validated = $request->validate([
            'starts_at' => [
                'required',
                'date',
            ],
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);
        $this->ensureBusinessBranchAccess(
            $request,
            $appointment
        );

        try {
            $appointment = $this->lifecycleService->reschedule(
                $appointment,
                Carbon::parse($validated['starts_at']),
                $request->user(),
                $validated['reason'] ?? null,
                $validated['notes'] ?? null,
                false
            );
            return response()->json([
                'success' => true,
                'message' => 'Randevu tarihi başarıyla değiştirildi.',
                'data' => $appointment,
            ]);
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception);
        }
    }
    private function ensureBusinessBranchAccess(
        Request $request,
        Appointment $appointment
    ): void {
        $user = $request->user();

        $isBusinessUser = $user->businessMemberships()
            ->where('business_id', $appointment->business_id)
            ->where('is_active', true)
            ->exists();

        if (! $isBusinessUser) {
            abort(
                403,
                'Bu randevu üzerinde işlem yapma yetkiniz yok.'
            );
        }

        if (! $user->hasBusinessBranchAccess(
            (int) $appointment->business_id,
            (int) $appointment->branch_id
        )) {
            abort(
                403,
                'Bu randevu üzerinde işlem yapma yetkiniz yok.'
            );
        }
    }

    private function errorResponse(
        RuntimeException $exception
    ): JsonResponse {
        $message = $exception->getMessage();
        $status = match ($message) {
            'Bu randevu işlemi için yetkiniz bulunmuyor.',
            'Bu randevu işlemi için yetkili kullanıcı gereklidir.' => 403,
            default => 422,
        };
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}