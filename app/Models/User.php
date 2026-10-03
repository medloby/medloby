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
}