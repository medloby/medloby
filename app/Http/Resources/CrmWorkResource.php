<?php

namespace App\Http\Resources;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmWorkResource extends JsonResource
{
    public static function leadSummary(Branch $branch): Closure
    {
        return fn ($lead) => $lead->forBranch($branch)->select('id', 'first_name', 'last_name');
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource->toArray(),
            'crm_lead_id' => $this->whenLoaded('lead', fn ($lead) => $lead->id, null),
            'lead' => $this->whenLoaded('lead', fn ($lead) => $lead->only('id', 'first_name', 'last_name'), null),
        ];
    }
}
