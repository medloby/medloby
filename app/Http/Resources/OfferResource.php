<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class OfferResource extends JsonResource
{
    public const array DISPLAY_RELATIONS = [
        'business:id,name', 'branch:id,name', 'patientProfile:id,first_name,last_name', 'treatment:id,name', 'creator:id,name',
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
                'id', 'conversation_id', 'business_id', 'branch_id', 'patient_profile_id', 'treatment_id',
                'treatment_package_id', 'package_snapshot', 'created_by', 'title', 'description', 'amount',
                'currency', 'valid_until', 'status', 'responded_at', 'rejection_reason', 'created_at', 'updated_at',
                'appointments_exists',
            ]),
            'business' => $this->whenLoaded('business', fn ($business) => $business->only('id', 'name')),
            'branch' => $this->whenLoaded('branch', fn ($branch) => $branch->only('id', 'name')),
            'patient_profile' => $this->whenLoaded('patientProfile', fn ($patient) => $patient->only('id', 'first_name', 'last_name')),
            'treatment' => $this->whenLoaded('treatment', fn ($treatment) => $treatment->only('id', 'name')),
            'creator' => $this->whenLoaded('creator', fn ($creator) => $creator->only('id', 'name')),
            'appointment' => $this->whenLoaded('appointment', fn ($appointment) => new BookedAppointmentResource($appointment)),
            'appointments' => $this->whenLoaded('appointments', fn ($appointments) => BookedAppointmentResource::collection($appointments)),
        ];
    }
}
