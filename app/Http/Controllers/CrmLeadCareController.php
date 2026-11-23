<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CrmLead;
use App\Models\CrmLeadCareStep;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmLeadCareController extends Controller
{
    public function index(Branch $branch, CrmLead $crmLead): JsonResponse
    {
        $visitQuery = $this->appointments($branch, $crmLead);
        $steps = CrmLeadCareStep::query()->where('crm_lead_id', $crmLead->id)->get()->keyBy('phase');
        $knownVisits = (clone $visitQuery)->whereIn('id', $steps->pluck('appointment_id')->filter()->all())->get()->keyBy('id');
        $choices = $visitQuery->where('status', '!=', 'rescheduled')->latest('starts_at')->latest('id')->limit(25)->get()
            ->map($this->visit(...))->all();

        $phases = [];
        foreach (CrmLeadCareStep::PHASES as $phase => $label) {
            $record = $steps->get($phase);
            $appointment = $knownVisits->get($record?->appointment_id)?->currentVersion();
            $phases[] = [
                'phase' => $phase, 'label' => $label, 'status' => $record?->status ?? 'pending',
                'planned_on' => $record?->planned_on?->toDateString(), 'completed_at' => $record?->completed_at?->toIso8601String(),
                'revision' => $record?->revision ?? 0, 'appointment' => $appointment ? $this->visit($appointment) : null,
            ];
        }

        return $this->respond(['phases' => $phases, 'appointments' => $choices]);
    }

    public function update(Request $request, Branch $branch, CrmLead $crmLead, string $phase): JsonResponse
    {
        abort_unless(array_key_exists($phase, CrmLeadCareStep::PHASES), 404);
        $this->ensureLead($branch, $crmLead);
        $fields = $request->validate([
            'status' => ['required', 'in:pending,planned,completed,skipped'],
            'planned_on' => ['nullable', 'date_format:Y-m-d', 'regex:/\A(?!0000)\d{4}-\d{2}-\d{2}\z/'],
            'appointment_id' => ['nullable', 'integer', 'min:1'],
            'revision' => ['required', 'integer', 'min:0'],
        ]);
        DB::transaction(function () use ($request, $branch, $crmLead, $phase, $fields): void {
            $lead = CrmLead::query()->forBranch($branch)->lockForUpdate()->findOrFail($crmLead->id);
            $appointment = isset($fields['appointment_id']) ? $this->appointments($branch, $lead)->find($fields['appointment_id']) : null;
            if (isset($fields['appointment_id']) && $appointment === null) {
                throw ValidationException::withMessages(['appointment_id' => 'Adaya ve şubeye ait bir randevu seçin.']);
            }

            $step = CrmLeadCareStep::query()->firstOrNew(['crm_lead_id' => $lead->id, 'phase' => $phase]);
            $step->fill([
                'status' => $fields['status'],
                'planned_on' => $fields['planned_on'] ?? null,
                'appointment_id' => $appointment?->currentVersion()->id,
            ]);
            if (! $step->exists || $step->isDirty()) {
                $revision = $step->exists ? $step->revision : 0;
                abort_unless($revision === (int) $fields['revision'], 409, 'Aşama başka bir oturumda değişti.');
                $step->revision = $revision + 1;
                $step->updated_by_user_id = $request->user()->id;
                $step->completed_at = $step->status === 'completed' ? ($step->completed_at ?? now()) : null;
                $step->save();
            }
        }, attempts: 3);

        return $this->index($branch, $crmLead->refresh());
    }

    /** @return Builder<Appointment> */
    private function appointments(Branch $branch, CrmLead $lead): Builder
    {
        $this->ensureLead($branch, $lead);

        $eligiblePatient = $lead->patientProfile()->knownToBranch($branch)->select('patient_profiles.id');

        return Appointment::query()->where('business_id', $branch->business_id)->where('branch_id', $branch->id)
            ->whereIn('patient_profile_id', $eligiblePatient);
    }

    private function ensureLead(Branch $branch, CrmLead $lead): void
    {
        abort_unless($lead->business_id === $branch->business_id && $lead->branch_id === $branch->id, 404);
    }

    private function visit(Appointment $visit): array
    {
        return ['id' => $visit->id, 'starts_at' => $visit->starts_at->toDateTimeString(), 'status' => $visit->status];
    }

    private function respond(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data])->header('Cache-Control', 'private, no-store');
    }
}
