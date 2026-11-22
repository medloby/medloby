<?php

namespace App\Models;

use Database\Factories\ConsentTemplateVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentTemplateVersion extends Model
{
    /** @use HasFactory<ConsentTemplateVersionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['revision', 'title', 'content', 'prepared_by_user_id'];

    protected function casts(): array
    {
        return ['revision' => 'integer'];
    }

    public function consentTemplate(): BelongsTo
    {
        return $this->belongsTo(ConsentTemplate::class);
    }
}
