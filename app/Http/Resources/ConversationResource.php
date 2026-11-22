<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ConversationResource extends JsonResource
{
    public const array DISPLAY_RELATIONS = [
        'business:id,name', 'branch:id,name', 'patientProfile:id,user_id,first_name,last_name', 'treatmentPackage:id,name',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...Arr::only($this->resource->attributesToArray(), [
                'id', 'business_id', 'branch_id', 'patient_profile_id', 'treatment_package_id',
                'subject', 'status', 'last_message_at', 'created_at', 'updated_at',
            ]),
            'business' => $this->whenLoaded('business', fn ($business) => $business->only('id', 'name')),
            'branch' => $this->whenLoaded('branch', fn ($branch) => $branch->only('id', 'name')),
            'patient_profile' => $this->whenLoaded('patientProfile', fn ($patient) => $patient->only('id', 'user_id', 'first_name', 'last_name')),
            'treatment_package' => $this->whenLoaded('treatmentPackage', fn ($package) => $package->only('id', 'name')),
        ];
    }
}
