<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentLifecycleService;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AppointmentController extends Controller
{
    public function __construct(
        protected AppointmentService $appointmentService,
        protected AppointmentLifecycleService $lifecycleService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $businessIds = $user->businessMemberships()
            ->where('is_active', true)
            ->pluck('business_id');

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

        $appointments = Appointment::query()
            ->with([
                'business',
                'branch',
                'patientProfile',
                'doctor',
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
                        $patientQuery->where('user_id', $user->id);
                    }
                );

                if (! empty($accessibleBusinessBranchIds)) {
                    $query->orWhereIn(
                        'branch_id',
                        $accessibleBusinessBranchIds
                    );
                }
            })
            ->when(
                $request->filled('status'),
                function ($query) use ($request) {
                    $query->where(
                        'status',
                        $request->input('status')
                    );
                }
            )
            ->when(
                $request->filled('from'),
                function ($query) use ($request) {
                    $query->whereDate(
                        'starts_at',
                        '>=',
                        $request->input('from')
                    );
                }
            )
            ->when(
                $request->filled('to'),
                function ($query) use ($request) {
                    $query->whereDate(
                        'starts_at',
                        '<=',
                        $request->input('to')
                    );
                }
            )
            ->latest('starts_at')
            ->paginate(
                min(
                    max((int) $request->input('per_page', 20), 1),
                    100
                )
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

        if ($isPatient) {
            return response()->json([
                'success' => true,
                'data' => $appointment->load([
                    'business',
                    'branch',
                    'patientProfile',
                    'doctor',
                    'treatment',
                    'statusHistories',
                    'cancelledBy',
                    'rescheduledFrom',
                    'rescheduledAppointments',
                ]),
            ]);
        }

        if (! $isBusinessUser) {
            abort(
                403,
                'Bu randevuyu görüntüleme yetkiniz yok.'
            );
        }

        if (! $user->hasBusinessBranchAccess(
            (int) $appointment->business_id,
            (int) $appointment->branch_id
        )) {
            abort(
                403,
                'Bu randevuyu görüntüleme yetkiniz yok.'
            );
        }

        return response()->json([
            'success' => true,
            'data' => $appointment->load([
                'business',
                'branch',
                'patientProfile',
                'doctor',
                'treatment',
                'statusHistories',
                'cancelledBy',
                'rescheduledFrom',
                'rescheduledAppointments',
            ]),
        ]);
    }

    public function confirm(
        Request $request,
        Appointment $appointment
    ): JsonResponse {
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

    public function noShow(
        Request $request,
        Appointment $appointment
    ): JsonResponse {
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