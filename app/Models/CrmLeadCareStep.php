<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmLeadCareStep extends Model
{
    public const array PHASES = [
        'consultation' => 'Konsültasyon',
        'treatment' => 'Tedavi',
        'checkup' => 'Kontrol randevusu',
        'follow_up' => 'Takip',
    ];

    protected $fillable = ['crm_lead_id', 'appointment_id', 'updated_by_user_id', 'phase', 'status', 'planned_on', 'completed_at'];

    protected $attributes = ['revision' => 1];

    protected function casts(): array
    {
        return ['planned_on' => 'date', 'completed_at' => 'datetime', 'revision' => 'integer'];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
