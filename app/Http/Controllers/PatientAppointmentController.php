<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentLifecycleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PatientAppointmentController extends Controller
{
    public function __construct(private AppointmentLifecycleService $lifecycle) {}

    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        abort_unless($appointment->belongsToPatient($request->user()), 404);
        $data = $request->validate(['cancellation_reason' => ['required', 'string', 'max:1000']]);

        try {
            $cancelled = $this->lifecycle->cancel($appointment, $data['cancellation_reason'], $request->user(), byPatient: true);
        } catch (RuntimeException $exception) {
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
        } catch (RuntimeException $exception) {
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
        } catch (RuntimeException $exception) {
            return $this->unavailable($exception);
        }

        return $this->data([
            ...$moved->only(['id', 'status']),
            'starts_at' => $moved->starts_at->toDateTimeString(),
            'ends_at' => $moved->ends_at->toDateTimeString(),
        ]);
    }

    private function data(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data])->header('Cache-Control', 'private, no-store');
    }

    private function unavailable(RuntimeException $exception): JsonResponse
    {
        if ($exception::class !== RuntimeException::class) {
            throw $exception;
        }

        return response()->json(['success' => false, 'message' => $exception->getMessage()], 422)->header('Cache-Control', 'private, no-store');
    }
}
