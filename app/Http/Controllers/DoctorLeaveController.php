<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorLeave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DoctorLeaveController extends Controller
{
    public function index(Request $request, Branch $branch, Doctor $doctor): JsonResponse
    {
        $this->authorizeAccess($request, $branch, $doctor);

        $leaves = DoctorLeave::query()
            ->where('doctor_id', $doctor->id)
            ->where(function ($query) use ($branch) {
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', $branch->id);
            })
            ->orderBy('start_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'leaves' => $leaves,
            ],
        ]);
    }

    public function store(Request $request, Branch $branch, Doctor $doctor): JsonResponse
    {
        $this->authorizeAccess($request, $branch, $doctor);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'leave_type' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string'],
            'is_approved' => ['sometimes', 'boolean'],
        ]);

        $leaveType = $validated['leave_type'] ?? 'leave';

        $this->ensureNoOverlap(
            $doctor,
            $branch,
            $validated['start_date'],
            $validated['end_date']
        );

        $leave = DoctorLeave::create([
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'leave_type' => $leaveType,
            'reason' => $validated['reason'] ?? null,
            'is_approved' => $validated['is_approved'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $leave,
        ], 201);
    }

    public function update(
        Request $request,
        Branch $branch,
        Doctor $doctor,
        DoctorLeave $doctorLeave
    ): JsonResponse {
        $this->authorizeLeaveAccess(
            $request,
            $branch,
            $doctor,
            $doctorLeave
        );

        $validated = $request->validate([
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'leave_type' => ['sometimes', 'string', 'max:100'],
            'reason' => ['nullable', 'string'],
            'is_approved' => ['sometimes', 'boolean'],
        ]);

        $startDate = $validated['start_date']
            ?? $doctorLeave->start_date->format('Y-m-d');

        $endDate = $validated['end_date']
            ?? $doctorLeave->end_date->format('Y-m-d');

        $this->ensureNoOverlap(
            $doctor,
            $branch,
            $startDate,
            $endDate,
            $doctorLeave->id
        );

        $doctorLeave->update($validated);

        return response()->json([
            'success' => true,
            'data' => $doctorLeave->fresh(),
        ]);
    }

    public function deactivate(
        Request $request,
        Branch $branch,
        Doctor $doctor,
        DoctorLeave $doctorLeave
    ): JsonResponse {
        $this->authorizeLeaveAccess(
            $request,
            $branch,
            $doctor,
            $doctorLeave
        );

        $doctorLeave->update([
            'is_approved' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => $doctorLeave->fresh(),
        ]);
    }

    private function authorizeAccess(
        Request $request,
        Branch $branch,
        Doctor $doctor
    ): void {
        if (!$request->user()) {
            abort(401);
        }

        $businessUser = $request->user()
            ->businessMemberships()
            ->where('business_id', $branch->business_id)
            ->where('is_active', true)
            ->first();

        if (!$businessUser) {
            abort(403);
        }

        $isOwner = in_array(
            $businessUser->role,
            ['business_owner', 'owner'],
            true
        );

        if (!$isOwner) {
            $hasBranchAccess = $businessUser->branches()
                ->where('branches.id', $branch->id)
                ->wherePivot('is_active', true)
                ->exists();

            if (!$hasBranchAccess) {
                abort(403);
            }
        }

        $doctorBelongsToBranch = $doctor->branches()
            ->where('branches.id', $branch->id)
            ->wherePivot('status', 'active')
            ->exists();

        if (!$doctorBelongsToBranch) {
            abort(422, 'Doctor is not assigned to this branch.');
        }
    }

    private function authorizeLeaveAccess(
        Request $request,
        Branch $branch,
        Doctor $doctor,
        DoctorLeave $doctorLeave
    ): void {
        $this->authorizeAccess($request, $branch, $doctor);

        if ($doctorLeave->doctor_id !== $doctor->id) {
            abort(404);
        }

        if (
            $doctorLeave->branch_id !== null &&
            $doctorLeave->branch_id !== $branch->id
        ) {
            abort(404);
        }
    }

    private function ensureNoOverlap(
        Doctor $doctor,
        Branch $branch,
        string $startDate,
        string $endDate,
        ?int $ignoreId = null
    ): void {
        $query = DoctorLeave::query()
            ->where('doctor_id', $doctor->id)
            ->where('is_approved', true)
            ->where(function ($query) use ($branch) {
                $query->whereNull('branch_id')
                    ->orWhere('branch_id', $branch->id);
            })
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_date' => [
                    'Bu tarih aralığında doktor için mevcut bir izin bulunmaktadır.',
                ],
            ]);
        }
    }
}