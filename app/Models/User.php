<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function businessMemberships(): HasMany
    {
        return $this->hasMany(BusinessUser::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'business_user_permission'
        )
            ->withPivot([
                'business_id',
                'granted_by_user_id',
                'is_allowed',
            ])
            ->withTimestamps();
    }

    public function patientProfile(): HasOne
    {
        return $this->hasOne(PatientProfile::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(
            Message::class,
            'sender_user_id'
        );
    }

    public function cancelledAppointments(): HasMany
    {
        return $this->hasMany(
            Appointment::class,
            'cancelled_by_user_id'
        );
    }

    public function appointmentStatusChanges(): HasMany
    {
        return $this->hasMany(
            AppointmentStatusHistory::class,
            'changed_by_user_id'
        );
    }

    public function grantedPermissions(): HasMany
    {
        return $this->hasMany(
            BusinessUserPermission::class,
            'granted_by_user_id'
        );
    }

    public function contractAcceptances(): HasMany
    {
        return $this->hasMany(
            ClinicContractAcceptance::class
        );
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(
            BusinessReview::class,
            'reviewed_by_user_id'
        );
    }

    public function businessStatusChanges(): HasMany
    {
        return $this->hasMany(
            BusinessStatusHistory::class,
            'changed_by_user_id'
        );
    }

    public function hasBusinessPermission(
        int $businessId,
        string $permissionName
    ): bool {
        $isBusinessMember = $this->businessMemberships()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->exists();

        if (! $isBusinessMember) {
            return false;
        }

        return $this->permissions()
            ->where('permissions.name', $permissionName)
            ->where('permissions.is_active', true)
            ->wherePivot('business_id', $businessId)
            ->wherePivot('is_allowed', true)
            ->exists();
    }

    public function hasBusinessBranchAccess(
        int $businessId,
        int $branchId
    ): bool {
        $membership = $this->businessMemberships()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->first();

        if (! $membership) {
            return false;
        }

        $branchBelongsToBusiness = Branch::query()
            ->whereKey($branchId)
            ->where('business_id', $businessId)
            ->exists();

        if (! $branchBelongsToBusiness) {
            return false;
        }

        if ($membership->role === 'business_owner') {
            return true;
        }

        return $membership->branches()
            ->where('branches.id', $branchId)
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function accessibleBranchIds(int $businessId): array
    {
        $membership = $this->businessMemberships()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->first();

        if (! $membership) {
            return [];
        }

        if ($membership->role === 'business_owner') {
            return Branch::query()
                ->where('business_id', $businessId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $membership->branches()
            ->where('branches.business_id', $businessId)
            ->where('branches.status', 'active')
            ->wherePivot('is_active', true)
            ->pluck('branches.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}