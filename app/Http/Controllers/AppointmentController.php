<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentLifecycleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AppointmentController extends Controller
{
    public function __construct(
        protected AppointmentLifecycleService $lifecycleService
    ) {
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
            'cancellation_reason' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
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
            'starts_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
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
        return response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
        ], 422);
    }
}