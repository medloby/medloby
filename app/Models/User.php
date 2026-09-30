<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
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