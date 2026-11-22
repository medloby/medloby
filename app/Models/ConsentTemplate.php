<?php

namespace App\Models;

use Database\Factories\ConsentTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsentTemplate extends Model
{
    /** @use HasFactory<ConsentTemplateFactory> */
    use HasFactory;

    protected $fillable = ['title', 'content'];

    protected $attributes = ['revision' => 1];

    protected function casts(): array
    {
        return ['revision' => 'integer'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ConsentTemplateVersion::class);
    }
}
