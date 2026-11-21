<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmLeadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource->toArray(),
            'patient_profile_id' => $this->whenLoaded('patientProfile', fn ($patient) => $patient->id, null),
            'assigned_business_user_id' => $this->whenLoaded('assignedBusinessUser', fn ($member) => $member->id, null),
        ];
    }
}
