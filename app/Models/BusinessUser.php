<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BusinessUser extends Model
{
    public const array BRANCH_PERMISSION_LABELS = [
        'appointments.create' => 'Randevu oluşturma',
        'appointments.confirm' => 'Randevu onaylama',
        'appointments.cancel' => 'Randevu iptal etme',
        'appointments.reschedule' => 'Randevu tarihini değiştirme',
        'appointments.complete' => 'Randevuyu tamamlama',
        'appointments.mark_no_show' => 'Hasta gelmedi olarak işaretleme',
        'branches.manage' => 'Şube kataloğu ve fiyatlarını düzenleme',
        'travel.manage' => 'Seyahat planını düzenleme',
        'finance.view' => 'Gelir ve gider kayıtlarını görüntüleme',
        'finance.manage' => 'Gelir ve gider kayıtlarını düzenleme',
    ];

    protected $table = 'business_user';

    protected $fillable = [
        'business_id',
        'user_id',
        'role',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    #[Scope]
    protected function activeForBranch(Builder $query, Branch $branch): Builder
    {
        return $query->where('business_user.business_id', $branch->business_id)
            ->where('business_user.is_active', true)
            ->where(fn (Builder $membership) => $membership->where('business_user.role', 'business_owner')
                ->orWhereHas('branches', fn (Builder $branches) => $branches->whereKey($branch->id)
                    ->where('business_user_branch.is_active', true)));
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(
            Branch::class,
            'business_user_branch',
            'business_user_id',
            'branch_id'
        )
            ->using(BusinessUserBranch::class)
            ->withPivot([
                'is_active',
                'permission_names',
            ])
            ->withTimestamps();
    }
}
