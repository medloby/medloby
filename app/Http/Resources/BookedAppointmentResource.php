<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class BookedAppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...Arr::only($this->resource->attributesToArray(), [
                'id', 'business_id', 'branch_id', 'patient_profile_id', 'doctor_id', 'treatment_id', 'offer_id',
                'starts_at', 'ends_at', 'status', 'source', 'patient_name', 'notes', 'cancellation_reason',
                'cancelled_at', 'rescheduled_from_appointment_id', 'rescheduled_at', 'created_at', 'updated_at',
            ]),
            'business' => $this->whenLoaded('business', fn ($business) => $business->only('id', 'name')),
            'branch' => $this->whenLoaded('branch', fn ($branch) => $branch->only('id', 'name')),
            'patient_profile' => $this->whenLoaded('patientProfile', fn ($patient) => $patient->only('id', 'first_name', 'last_name')),
            'doctor' => $this->whenLoaded('doctor', fn ($doctor) => $doctor->only('id', 'specialty')),
            'treatment' => $this->whenLoaded('treatment', fn ($treatment) => $treatment->only('id', 'name')),
        ];
    }
}
