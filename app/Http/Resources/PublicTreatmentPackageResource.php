<?php

namespace App\Http\Resources;

use App\Models\PackageService;
use App\Models\Treatment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicTreatmentPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource->only([
                'id', 'business_id', 'branch_id', 'name', 'slug', 'package_type',
                'duration_days', 'includes_hotel', 'includes_transfer', 'is_offer_enabled',
                'is_active', 'valid_from', 'valid_until',
            ]),
            'business' => $this->business->only(['id', 'name']),
            'branch' => $this->branch?->only(['id', 'name']),
            'location' => $this->branch?->only(['city', 'district']) ?? ['city' => $this->business->city, 'district' => null],
            'treatments' => $this->treatments->map(fn (Treatment $treatment) => [
                'name' => $treatment->name,
                'quantity' => (int) $treatment->pivot->quantity,
            ]),
            'services' => $this->services
                ->filter(fn (PackageService $service) => $service->business_id === null || $service->business_id === $this->business_id)
                ->map(fn (PackageService $service) => [
                    'name' => $service->name,
                    'quantity' => (int) $service->pivot->quantity,
                ])->values(),
        ];
    }
}
