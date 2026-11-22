<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Doctor;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClinicBranchOperationsController extends Controller
{
    public function index(Branch $branch): JsonResponse
    {
        $staff = BusinessUser::query()
            ->where('business_id', $branch->business_id)
            ->whereIn('role', ['manager', 'staff'])
            ->with([
                'user:id,name,email,email_verified_at,login_code,staff_business_id',
                'branches' => fn ($query) => $query->where('branches.id', $branch->id)->select('branches.id'),
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (BusinessUser $member) => [
                'id' => $member->id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'login_code' => $member->user->login_code,
                'can_reset_password' => $member->user->staff_business_id === $branch->business_id,
                'is_active' => $member->is_active,
                'branch_access' => (bool) $member->branches->first()?->pivot->is_active,
                'can_assign' => $member->is_active && ($member->user->hasVerifiedEmail() || $member->user->staff_business_id === $branch->business_id),
            ]);

        $doctors = $this->clinicDoctors($branch)
            ->with([
                'person:id,first_name,last_name',
                'branches' => fn ($query) => $query->where('branches.id', $branch->id)->select('branches.id'),
                'treatments' => fn ($query) => $query->select('treatments.id'),
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Doctor $doctor) => [
                'id' => $doctor->id,
                'name' => $doctor->person->first_name.' '.$doctor->person->last_name,
                'specialty' => $doctor->specialty,
                'license_number' => $doctor->license_number,
                'is_active' => $doctor->status === 'active',
                'is_public' => $doctor->is_public,
                'in_branch' => $doctor->branches->first()?->pivot->status === 'active',
                'treatment_ids' => $doctor->treatments->filter(fn (Treatment $treatment) => $treatment->pivot->is_active)
                    ->pluck('id')->values(),
            ]);

        $branchTreatments = $branch->treatments()
            ->orderBy('treatments.name')
            ->get(['treatments.id', 'treatments.name', 'treatments.is_active', 'treatments.duration_minutes'])
            ->map(fn (Treatment $treatment) => [
                'id' => $treatment->id,
                'name' => $treatment->name,
                'catalog_active' => $treatment->is_active,
                'is_active' => (bool) $treatment->pivot->is_active,
                'is_online_bookable' => (bool) $treatment->pivot->is_online_bookable,
                'duration_minutes' => $treatment->pivot->duration_minutes,
                'catalog_duration_minutes' => $treatment->duration_minutes,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'staff' => $staff,
                'doctors' => $doctors,
                'branch_treatments' => $branchTreatments,
                'catalog_treatments' => Treatment::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function addStaff(Request $request, Branch $branch): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $matches = User::query()->whereRaw('lower(email) = ?', [Str::lower($validated['email'])])
            ->whereNotNull('email_verified_at')->where('is_admin', false)->limit(2)->get(['id']);

        if ($matches->count() !== 1) {
            throw ValidationException::withMessages(['email' => 'Personelin e-posta adresi doğrulanmış bir Medloby hesabına ait olmalıdır.']);
        }

        $accountId = $matches->sole()->id;
        DB::transaction(function () use ($accountId, $branch): void {
            $member = BusinessUser::query()->firstOrCreate(
                ['business_id' => $branch->business_id, 'user_id' => $accountId],
                ['role' => 'staff', 'is_active' => true],
            );
            $this->setStaffAccess($branch, $member->id, true);
        }, attempts: 3);

        return $this->saved('Personele şube yetkisi verildi.');
    }

    public function updateStaff(Request $request, Branch $branch, BusinessUser $businessUser): JsonResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        DB::transaction(fn () => $this->setStaffAccess($branch, $businessUser->id, (bool) $validated['is_active']), attempts: 3);

        return $this->saved('Şube yetkisi kaydedildi.');
    }

    public function createStaffAccount(Request $request, Branch $branch): JsonResponse
    {
        if (is_string($request->input('login_code'))) {
            $request->merge(['login_code' => Str::lower($request->input('login_code'))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'login_code' => ['required', 'alpha_dash:ascii', 'min:3', 'max:50', 'not_regex:/\Aklinik-/', 'unique:users,login_code'],
            'password' => ['required', 'string', 'min:8', 'regex:/\A[^\x00]{1,72}\z/s'],
        ], [
            'login_code.unique' => 'Bu kullanıcı kodu kullanımda.',
            'login_code.not_regex' => 'Personel kullanıcı kodu klinik- ile başlayamaz.',
        ]);

        DB::transaction(function () use ($branch, $validated): void {
            $staff = new User(['name' => $validated['name'], 'password' => Hash::make($validated['password'])]);
            $staff->forceFill(['login_code' => $validated['login_code'], 'staff_business_id' => $branch->business_id])->save();
            $member = $staff->businessMemberships()->create([
                'business_id' => $branch->business_id,
                'role' => 'staff',
                'is_active' => true,
            ]);
            $this->setStaffAccess($branch, $member->id, true);
        }, attempts: 3);

        return response()->json(['success' => true, 'message' => 'Personel hesabı açıldı ve şube yetkisi verildi.', 'data' => null], 201);
    }

    public function resetStaffPassword(Request $request, Branch $branch, BusinessUser $businessUser): JsonResponse
    {
        abort_unless($businessUser->business_id === $branch->business_id && in_array($businessUser->role, ['staff', 'manager'], true), 404);
        $validated = $request->validate(['password' => ['required', 'string', 'min:8', 'regex:/\A[^\x00]{1,72}\z/s']]);

        DB::transaction(function () use ($branch, $businessUser, $validated): void {
            $staff = User::query()->where('staff_business_id', $branch->business_id)->lockForUpdate()->findOrFail($businessUser->user_id);
            $staff->update(['password' => Hash::make($validated['password'])]);
            $staff->tokens()->delete();
        }, attempts: 3);

        return $this->saved('Personelin şifresi güncellendi.');
    }

    private function setStaffAccess(Branch $branch, int $memberId, bool $active): void
    {
        $member = BusinessUser::query()->where('business_id', $branch->business_id)
            ->lockForUpdate()->findOrFail($memberId);

        $canAssign = $member->user->hasVerifiedEmail() || $member->user->staff_business_id === $branch->business_id;
        if (! in_array($member->role, ['manager', 'staff'], true) || ($active && (! $member->is_active || ! $canAssign))) {
            throw ValidationException::withMessages(['is_active' => 'Şube yetkisi yalnızca aktif ve doğrulanmış personele verilebilir.']);
        }

        if ($active) {
            $member->branches()->syncWithoutDetaching([$branch->id => ['is_active' => true]]);
        } else {
            $member->branches()->updateExistingPivot($branch->id, ['is_active' => false]);
            if ($member->user->staff_business_id === $branch->business_id && ! $member->branches()
                ->where('branches.business_id', $branch->business_id)->where('branches.status', 'active')
                ->wherePivot('is_active', true)->exists()) {
                $member->user()->lockForUpdate()->firstOrFail()->tokens()->delete();
            }
        }
    }

    public function addDoctor(Request $request, Branch $branch): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:255'],
        ]);

        $doctor = DB::transaction(function () use ($branch, $validated): Doctor {
            $business = Business::query()->lockForUpdate()->findOrFail($branch->business_id);
            $license = $validated['license_number'] ?? null;
            $specialty = $validated['specialty'] ?? null;
            if ($license !== null && $this->clinicDoctors($branch)->where('license_number', $license)->exists()) {
                throw ValidationException::withMessages(['license_number' => 'Bu ruhsat numarası kayıtlı. Şubeye mevcut hekimi ekleyin.']);
            }

            $person = $business->people()->create([
                'branch_id' => $branch->id,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'specialty' => $specialty,
                'job_title' => 'Hekim',
                'status' => 'active',
            ]);
            $doctor = $person->doctor()->create([
                'license_number' => $license,
                'specialty' => $specialty,
                'status' => 'active',
                'is_public' => false,
            ]);
            $doctor->branches()->attach($branch->id, ['status' => 'active']);

            return $doctor;
        }, attempts: 3);

        return response()->json([
            'success' => true,
            'message' => 'Hekim şubeye kaydedildi.',
            'data' => ['id' => $doctor->id],
        ], 201);
    }

    public function updateDoctorBranch(Request $request, Branch $branch, Doctor $doctor): JsonResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        DB::transaction(function () use ($branch, $doctor, $validated): void {
            $doctor = $this->clinicDoctors($branch)->lockForUpdate()->findOrFail($doctor->id);

            if ($validated['is_active']) {
                abort_unless($doctor->status === 'active', 422, 'Pasif hekim şubeye eklenemez.');
                $doctor->branches()->syncWithoutDetaching([$branch->id => ['status' => 'active', 'end_date' => null]]);
            } else {
                $doctor->branches()->updateExistingPivot($branch->id, ['status' => 'inactive']);
            }
        }, attempts: 3);

        return $this->saved('Hekimin şube bağlantısı kaydedildi.');
    }

    public function updateDoctorTreatment(Request $request, Branch $branch, Doctor $doctor, Treatment $treatment): JsonResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        DB::transaction(function () use ($branch, $doctor, $treatment, $validated): void {
            $doctor = $this->clinicDoctors($branch)->lockForUpdate()->findOrFail($doctor->id);
            abort_unless($doctor->branches()->whereKey($branch->id)->wherePivot('status', 'active')->exists(),
                422, 'Hekim seçili şubede aktif olmalıdır.');

            $branchTreatment = $branch->treatments()->whereKey($treatment->id);
            if ($validated['is_active']) {
                abort_unless($doctor->status === 'active' && $treatment->is_active
                    && $branchTreatment->wherePivot('is_active', true)->exists(),
                    422, 'Hekim ve tedavi şubede aktif olmalıdır.');
                $doctor->treatments()->syncWithoutDetaching([$treatment->id => ['is_active' => true]]);
            } else {
                abort_unless($branchTreatment->exists(), 422, 'Bu tedavi seçili şubeye bağlı olmalıdır.');
                $doctor->treatments()->updateExistingPivot($treatment->id, ['is_active' => false]);
            }
        }, attempts: 3);

        return $this->saved('Hekim–tedavi bağlantısı kaydedildi.');
    }

    public function updateDoctorOnlineBooking(Request $request, Branch $branch, Doctor $doctor): JsonResponse
    {
        $validated = $request->validate(['is_public' => ['required', 'boolean']]);

        DB::transaction(function () use ($branch, $doctor, $validated): void {
            $doctor = $this->clinicDoctors($branch)->lockForUpdate()->findOrFail($doctor->id);
            if ($validated['is_public']) {
                abort_unless($doctor->status === 'active'
                    && $doctor->branches()->whereKey($branch->id)->wherePivot('status', 'active')->exists(),
                    422, 'Çevrimiçi randevu için hekim seçili şubede aktif olmalıdır.');
            }

            $doctor->update(['is_public' => $validated['is_public']]);
        }, attempts: 3);

        return $this->saved('Hekimin çevrimiçi randevu ayarı kaydedildi.');
    }

    private function clinicDoctors(Branch $branch): Builder
    {
        return Doctor::query()->whereHas('person', fn (Builder $person) => $person->where('business_id', $branch->business_id));
    }

    private function saved(string $message): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => null]);
    }
}
