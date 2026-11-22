<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Offer;
use App\Models\OfferTravelPlan;
use App\Models\User;
use App\Notifications\OfferTravelPlanNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OfferTravelPlanController extends Controller
{
    public function show(Request $request, Offer $offer): JsonResponse
    {
        $included = $this->includedServices($offer);
        $user = $request->user();
        $canManage = $this->canManage($user, $offer);
        $isPatient = $user->hasVerifiedEmail() && $offer->patientProfile()->where('user_id', $user->id)->exists();
        abort_unless($canManage || $isPatient, 404, 'Seyahat planı bulunamadı.');

        return $this->respond($offer->travelPlan, $included, $canManage);
    }

    public function update(Request $request, Offer $offer): JsonResponse
    {
        return DB::transaction(function () use ($request, $offer): JsonResponse {
            $offer = Offer::query()->lockForUpdate()->findOrFail($offer->id);
            abort_unless($this->canManage($request->user(), $offer), 403, 'Seyahat planını düzenleme yetkiniz yok.');
            $included = $this->includedServices($offer);
            $fields = $request->validate([
                'revision' => ['required', 'integer', 'min:0'],
                'hotel' => [Rule::prohibitedIf(! $included['hotel_included']), 'nullable', 'array:name,address,check_in,check_out'],
                'hotel.name' => ['required_with:hotel', 'string', 'max:180'],
                'hotel.address' => ['nullable', 'string', 'max:500'],
                'hotel.check_in' => ['required_with:hotel', 'date_format:Y-m-d'],
                'hotel.check_out' => ['required_with:hotel', 'date_format:Y-m-d', 'after:hotel.check_in'],
                'transfers' => [Rule::prohibitedIf(! $included['transfer_included']), 'sometimes', 'array', 'list', 'max:12'],
                'transfers.*' => ['required', 'array:starts_at,from,to'],
                'transfers.*.starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
                'transfers.*.from' => ['required', 'string', 'max:240'],
                'transfers.*.to' => ['required', 'string', 'max:240'],
            ]);

            $hotel = ($fields['hotel'] ?? []) ?: null;
            $transfers = $fields['transfers'] ?? [];
            usort($transfers, fn (array $a, array $b): int => $a['starts_at'] <=> $b['starts_at']);
            $plan = $offer->travelPlan;
            if ($plan?->hotel == $hotel && ($plan?->transfers ?? []) == $transfers) {
                return $this->respond($plan, $included, true);
            }

            $revision = $plan?->revision ?? 0;
            abort_unless((int) $fields['revision'] === $revision, 409,
                'Seyahat planı başka bir oturumda değişti. Devam etmek için planı yeniden yükleyin.');

            $plan = $offer->travelPlan()->updateOrCreate([], ['hotel' => $hotel, 'transfers' => $transfers, 'revision' => $revision + 1]);
            $offer->patientProfile?->user?->notify(new OfferTravelPlanNotification($offer));

            return $this->respond($plan, $included, true);
        }, attempts: 3);
    }

    /** @return array{hotel_included: bool, transfer_included: bool} */
    private function includedServices(Offer $offer): array
    {
        $hotel = in_array($offer->package_snapshot['includes_hotel'] ?? false, [true, 1], true);
        $transfer = in_array($offer->package_snapshot['includes_transfer'] ?? false, [true, 1], true);
        abort_unless(($hotel || $transfer) && $offer->status === 'accepted'
            && $offer->conversation()->where('business_id', $offer->business_id)->where('patient_profile_id', $offer->patient_profile_id)->exists(),
            404, 'Seyahat planı bulunamadı.');

        return ['hotel_included' => $hotel, 'transfer_included' => $transfer];
    }

    private function canManage(User $user, Offer $offer): bool
    {
        if (! $offer->hasActiveClinic() || ! $offer->conversation()->accessibleToClinic($user)->exists()) {
            return false;
        }

        $branches = $offer->requiredBookingBranchIds();
        if ($branches === []) {
            return $offer->business?->allowsOwnerManagement($user) ?? false;
        }

        $branch = count($branches) === 1
            ? Branch::query()->where('business_id', $offer->business_id)->where('status', 'active')->find($branches[0])
            : null;

        return $branch !== null && $user->canManageTravelPlan($branch);
    }

    private function respond(?OfferTravelPlan $plan, array $included, bool $canManage): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $included + [
            'can_manage' => $canManage,
            'revision' => $plan?->revision ?? 0,
            'hotel' => $included['hotel_included'] ? $plan?->hotel : null,
            'transfers' => $included['transfer_included'] ? ($plan?->transfers ?? []) : [],
        ]])->header('Cache-Control', 'private, no-store');
    }
}
