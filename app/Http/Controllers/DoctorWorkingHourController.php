<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorWorkingHour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DoctorWorkingHourController extends Controller
{
    /**
     * Doktorun belirli şubedeki çalışma saatlerini listeler.
     */
    public function index(
        Request $request,
        Branch $branch,
        Doctor $doctor
    ): JsonResponse {
        $this->ensureBusinessBranchDoctorAccess(
            $request,
            $branch,
            $doctor
        );

        $workingHours = DoctorWorkingHour::query()
            ->where('branch_id', $branch->id)
            ->where('doctor_id', $doctor->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'branch_id' => $branch->id,
                'doctor_id' => $doctor->id,
                'working_hours' => $workingHours,
            ],
        ]);
    }

    /**
     * Doktora yeni çalışma saati ekler.
     *
     * Aynı gün içerisinde birden fazla çalışma aralığı
     * tanımlanabilir.
     *
     * Örnek:
     * 09:00 - 13:00
     * 14:00 - 21:00
     */
    public function store(
        Request $request,
        Branch $branch,
        Doctor $doctor
    ): JsonResponse {
        $this->ensureBusinessBranchDoctorAccess(
            $request,
            $branch,
            $doctor
        );

        $validated = $request->validate([
            'day_of_week' => [
                'required',
                'integer',
                'between:0,6',
            ],
            'start_time' => [
                'required',
                'date_format:H:i',
            ],
            'end_time' => [
                'required',
                'date_format:H:i',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        if ($validated['start_time'] >= $validated['end_time']) {
            throw ValidationException::withMessages([
                'end_time' => [
                    'Çalışma bitiş saati başlangıç saatinden sonra olmalıdır.',
                ],
            ]);
        }

        $hasConflict = DoctorWorkingHour::query()
            ->where('doctor_id', $doctor->id)
            ->where('branch_id', $branch->id)
            ->where('day_of_week', $validated['day_of_week'])
            ->where('is_active', true)
            ->where('start_time', '<', $validated['end_time'])
            ->where('end_time', '>', $validated['start_time'])
            ->exists();

        if ($hasConflict) {
            throw ValidationException::withMessages([
                'start_time' => [
                    'Bu gün için girilen çalışma saatleri mevcut bir çalışma saatiyle çakışıyor.',
                ],
            ]);
        }

        $workingHour = DoctorWorkingHour::create([
            'doctor_id' => $doctor->id,
            'branch_id' => $branch->id,
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Doktor çalışma saati başarıyla eklendi.',
            'data' => $workingHour,
        ], 201);
    }

    /**
     * Mevcut çalışma saatini günceller.
     */
    public function update(
        Request $request,
        DoctorWorkingHour $doctorWorkingHour
    ): JsonResponse {
        $this->ensureWorkingHourAccess(
            $request,
            $doctorWorkingHour
        );

        $validated = $request->validate([
            'day_of_week' => [
                'sometimes',
                'integer',
                'between:0,6',
            ],
            'start_time' => [
                'sometimes',
                'date_format:H:i',
            ],
            'end_time' => [
                'sometimes',
                'date_format:H:i',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $dayOfWeek = $validated['day_of_week']
            ?? $doctorWorkingHour->day_of_week;

        $startTime = $validated['start_time']
            ?? substr((string) $doctorWorkingHour->start_time, 0, 5);

        $endTime = $validated['end_time']
            ?? substr((string) $doctorWorkingHour->end_time, 0, 5);

        $isActive = $validated['is_active']
            ?? $doctorWorkingHour->is_active;

        if ($startTime >= $endTime) {
            throw ValidationException::withMessages([
                'end_time' => [
                    'Çalışma bitiş saati başlangıç saatinden sonra olmalıdır.',
                ],
            ]);
        }

        if ($isActive) {
            $hasConflict = DoctorWorkingHour::query()
                ->where('doctor_id', $doctorWorkingHour->doctor_id)
                ->where('branch_id', $doctorWorkingHour->branch_id)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_active', true)
                ->whereKeyNot($doctorWorkingHour->id)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->exists();

            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'start_time' => [
                        'Bu gün için girilen çalışma saatleri mevcut bir çalışma saatiyle çakışıyor.',
                    ],
                ]);
            }
        }

        $doctorWorkingHour->update([
            'day_of_week' => $dayOfWeek,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'is_active' => $isActive,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Doktor çalışma saati başarıyla güncellendi.',
            'data' => $doctorWorkingHour->fresh(),
        ]);
    }

    /**
     * Çalışma saatini aktif/pasif yapar.
     */
    public function toggle(
        Request $request,
        DoctorWorkingHour $doctorWorkingHour
    ): JsonResponse {
        $this->ensureWorkingHourAccess(
            $request,
            $doctorWorkingHour
        );

        $newStatus = ! $doctorWorkingHour->is_active;

        if ($newStatus) {
            $hasConflict = DoctorWorkingHour::query()
                ->where('doctor_id', $doctorWorkingHour->doctor_id)
                ->where('branch_id', $doctorWorkingHour->branch_id)
                ->where('day_of_week', $doctorWorkingHour->day_of_week)
                ->where('is_active', true)
                ->whereKeyNot($doctorWorkingHour->id)
                ->where('start_time', '<', $doctorWorkingHour->end_time)
                ->where('end_time', '>', $doctorWorkingHour->start_time)
                ->exists();

            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'is_active' => [
                        'Bu çalışma saatini aktif hale getirmek mevcut aktif çalışma saatiyle çakışmaya neden oluyor.',
                    ],
                ]);
            }
        }

        $doctorWorkingHour->update([
            'is_active' => $newStatus,
        ]);

        return response()->json([
            'success' => true,
            'message' => $newStatus
                ? 'Doktor çalışma saati aktif hale getirildi.'
                : 'Doktor çalışma saati pasif hale getirildi.',
            'data' => $doctorWorkingHour->fresh(),
        ]);
    }

    /**
     * Çalışma saatini siler.
     */
    public function destroy(
        Request $request,
        DoctorWorkingHour $doctorWorkingHour
    ): JsonResponse {
        $this->ensureWorkingHourAccess(
            $request,
            $doctorWorkingHour
        );

        $doctorWorkingHour->delete();

        return response()->json([
            'success' => true,
            'message' => 'Doktor çalışma saati başarıyla silindi.',
        ]);
    }

    /**
     * Kullanıcının şube ve doktor üzerinde erişimi var mı?
     */
    private function ensureBusinessBranchDoctorAccess(
        Request $request,
        Branch $branch,
        Doctor $doctor
    ): void {
        $user = $request->user();

        $isBusinessUser = $user->businessMemberships()
            ->where('business_id', $branch->business_id)
            ->where('is_active', true)
            ->exists();

        if (! $isBusinessUser) {
            abort(
                403,
                'Bu işletmeye erişim yetkiniz yok.'
            );
        }

        if (! $user->hasBusinessBranchAccess(
            (int) $branch->business_id,
            (int) $branch->id
        )) {
            abort(
                403,
                'Bu şubeye erişim yetkiniz yok.'
            );
        }

        $doctorBelongsToBranch = $doctor->branches()
            ->where('branches.id', $branch->id)
            ->where('doctor_branch.status', 'active')
            ->exists();

        if (! $doctorBelongsToBranch) {
            abort(
                422,
                'Bu doktor seçilen şubede aktif olarak çalışmıyor.'
            );
        }
    }

    /**
     * Mevcut çalışma saatinin bağlı olduğu
     * işletme, şube ve doktor erişimini kontrol eder.
     */
    private function ensureWorkingHourAccess(
        Request $request,
        DoctorWorkingHour $doctorWorkingHour
    ): void {
        $branch = Branch::query()
            ->findOrFail($doctorWorkingHour->branch_id);

        $doctor = Doctor::query()
            ->findOrFail($doctorWorkingHour->doctor_id);

        $this->ensureBusinessBranchDoctorAccess(
            $request,
            $branch,
            $doctor
        );
    }
}