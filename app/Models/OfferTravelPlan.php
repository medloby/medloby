<?php

namespace App\Models;

use Database\Factories\OfferTravelPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferTravelPlan extends Model
{
    /** @use HasFactory<OfferTravelPlanFactory> */
    use HasFactory;

    protected $fillable = ['hotel', 'transfers', 'revision'];

    protected function casts(): array
    {
        return ['hotel' => 'array', 'transfers' => 'array', 'revision' => 'integer'];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
