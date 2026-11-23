<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CrmLead;
use App\Models\PatientProfile;
use App\Services\BranchPatientSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmLeadPatientController extends Controller
{
    public function index(Request $request, Branch $branch, CrmLead $crmLead, BranchPatientSearch $patients): JsonResponse
    {
        abort_unless($crmLead->business_id === $branch->business_id && $crmLead->branch_id === $branch->id, 404);
        $search = $request->validate(['search' => ['required', 'string', 'min:2', 'max:80']])['search'];

        return response()->json([
            'success' => true,
            'data' => $patients->search($branch, $search),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Branch $branch, CrmLead $crmLead): JsonResponse
    {
        abort_unless($crmLead->business_id === $branch->business_id && $crmLead->branch_id === $branch->id, 404);
        $patientId = $request->validate(['patient_profile_id' => ['required', 'integer']])['patient_profile_id'];

        return DB::transaction(function () use ($branch, $crmLead, $patientId): JsonResponse {
            $lead = CrmLead::query()->forBranch($branch)->lockForUpdate()->findOrFail($crmLead->id);
            $patient = PatientProfile::query()->knownToBranch($branch)->find($patientId, ['id', 'first_name', 'last_name']);
            if ($patient === null) {
                throw ValidationException::withMessages(['patient_profile_id' => 'Seçilen hasta bu şubede ilişkilendirilemiyor.']);
            }

            abort_if($lead->patient_profile_id !== null && $lead->patient_profile_id !== $patient->id,
                409, 'Adaya başka bir hasta bağlı. Önce mevcut bağlantıyı kaldırın.');

            if ($lead->patient_profile_id === null) {
                abort_if($lead->conversations()->where('business_id', $branch->business_id)->where('branch_id', $branch->id)
                    ->where('patient_profile_id', '!=', $patient->id)->exists(),
                    409, 'Adayın bağlı görüşmeleri aynı hastaya ait olmalıdır.');

                $lead->update(['patient_profile_id' => $patient->id]);
            }

            return response()->json([
                'success' => true,
                'data' => ['id' => $patient->id, 'name' => trim($patient->first_name.' '.$patient->last_name)],
            ])->header('Cache-Control', 'private, no-store');
        }, attempts: 3);
    }
}
