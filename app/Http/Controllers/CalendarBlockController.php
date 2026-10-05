<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CalendarBlock;
use App\Models\Doctor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CalendarBlockController extends Controller
{
    public function index(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeBranchAccess($request, $branch);

        $query = CalendarBlock::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true);

        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->integer('doctor_id'));
        }

        if ($request->filled('from')) {
            $query->where('ends_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('starts_at', '<=', $request->input('to'));
        }

        $blocks = $query
            ->orderBy('starts_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'blocks' => $blocks,
            ],
        ]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeBranchAccess($request, $branch);

        $validated = $request->validate([
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'block_type' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $doctor = null;

        if (!empty($validated['doctor_id'])) {
            $doctor = Doctor::findOrFail($validated['doctor_id']);
            $this->authorizeDoctorBranch($doctor, $branch);
        }

        $this->ensureNoOverlap(
            $branch,
            $validated['starts_at'],
            $validated['ends_at'],
            $validated['doctor_id'] ?? null
        );

        $block = CalendarBlock::create([
            'business_id' => $branch->business_id,
            'branch_id' => $branch->id,
            'doctor_id' => $doctor?->id,
            'created_by_user_id' => $request->user()->id,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'block_type' => $validated['block_type'] ?? 'manual',
            'title' => $validated['title'] ?? null,
            'reason' => $validated['reason'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $block,
        ], 201);
    }

    public function update(
        Request $request,
        Branch $branch,
        CalendarBlock $calendarBlock
    ): JsonResponse {
        $this->authorizeBlockAccess(
            $request,
            $branch,
            $calendarBlock
        );

        $validated = $request->validate([
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date', 'after:starts_at'],
            'block_type' => ['sometimes', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $doctorId = array_key_exists('doctor_id', $validated)
            ? $validated['doctor_id']
            : $calendarBlock->doctor_id;

        if ($doctorId !== null) {
            $doctor = Doctor::findOrFail($doctorId);
            $this->authorizeDoctorBranch($doctor, $branch);
        }

        $startsAt = $validated['starts_at']
            ?? $calendarBlock->starts_at->format('Y-m-d H:i:s');

        $endsAt = $validated['ends_at']
            ?? $calendarBlock->ends_at->format('Y-m-d H:i:s');

        $this->ensureNoOverlap(
            $branch,
            $startsAt,
            $endsAt,
            $doctorId,
            $calendarBlock->id
        );

        $calendarBlock->update($validated);

        return response()->json([
            'success' => true,
            'data' => $calendarBlock->fresh(),
        ]);
    }

    public function deactivate(
        Request $request,
        Branch $branch,
        CalendarBlock $calendarBlock
    ): JsonResponse {
        $this->authorizeBlockAccess(
            $request,
            $branch,
            $calendarBlock
        );

        $calendarBlock->update([
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => $calendarBlock->fresh(),
        ]);
    }

    private function authorizeBranchAccess(
        Request $request,
        Branch $branch
    ): void {
        if (!$request->user()) {
            abort(401);
        }

        $businessMembership = $request->user()
            ->businessMemberships()
            ->where('business_id', $branch->business_id)
            ->where('is_active', true)
            ->first();

        if (!$businessMembership) {
            abort(403);
        }

        $isOwner = in_array(
            $businessMembership->role,
            ['business_owner', 'owner'],
            true
        );

        if ($isOwner) {
            return;
        }

        $hasBranchAccess = $businessMembership->branches()
            ->where('branches.id', $branch->id)
            ->wherePivot('is_active', true)
            ->exists();

        if (!$hasBranchAccess) {
            abort(403);
        }
    }

    private function authorizeBlockAccess(
        Request $request,
        Branch $branch,
        CalendarBlock $calendarBlock
    ): void {
        $this->authorizeBranchAccess($request, $branch);

        if ($calendarBlock->branch_id !== $branch->id) {
            abort(404);
        }
    }

    private function authorizeDoctorBranch(
        Doctor $doctor,
        Branch $branch
    ): void {
        $belongsToBranch = $doctor->branches()
            ->where('branches.id', $branch->id)
            ->wherePivot('status', 'active')
            ->exists();

        if (!$belongsToBranch) {
            abort(422, 'Doctor is not assigned to this branch.');
        }
    }

    private function ensureNoOverlap(
        Branch $branch,
        string $startsAt,
        string $endsAt,
        ?int $doctorId = null,
        ?int $ignoreId = null
    ): void {
        $query = CalendarBlock::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->where(function ($query) use ($doctorId) {
                if ($doctorId === null) {
                    $query->whereNull('doctor_id');
                } else {
                    $query->whereNull('doctor_id')
                        ->orWhere('doctor_id', $doctorId);
                }
            })
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'starts_at' => [
                    'Bu zaman aralığında mevcut bir takvim engeli bulunmaktadır.',
                ],
            ]);
        }
    }
}