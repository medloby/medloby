<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CrmLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmLeadAppointmentController extends Controller
{
    public function __invoke(Request $request, Branch $branch, CrmLead $crmLead): JsonResponse
    {
        abort_unless($crmLead->business_id === $branch->business_id && $crmLead->branch_id === $branch->id, 404);

        $patient = $crmLead->patientProfile()->knownToBranch($branch)->first(['id', 'first_name', 'last_name']);
        $appointments = $patient?->appointments()->where('business_id', $branch->business_id)->where('branch_id', $branch->id)
            ->with([
                'doctor.person' => fn ($people) => $people->where('business_id', $branch->business_id)->select('id', 'first_name', 'last_name'),
                'treatment:id,name',
            ])
            ->latest('starts_at')->orderByDesc('id')
            ->paginate(10, ['id', 'doctor_id', 'treatment_id', 'starts_at', 'ends_at', 'status'])
            ->through(fn (Appointment $appointment): array => [
                'id' => $appointment->id,
                'starts_at' => $appointment->starts_at->toDateTimeString(),
                'ends_at' => $appointment->ends_at->toDateTimeString(),
                'status' => $appointment->status,
                'doctor' => $appointment->doctor?->person
                    ? trim($appointment->doctor->person->first_name.' '.$appointment->doctor->person->last_name)
                    : null,
                'treatment' => $appointment->treatment?->name,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'patient' => $patient ? ['id' => $patient->id, 'name' => trim($patient->first_name.' '.$patient->last_name)] : null,
                'can_book' => $patient !== null && $request->user()->canManageClinicAppointments($branch, 'appointments.create'),
                'appointments' => $appointments,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
