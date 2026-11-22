<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClinicProfileController extends Controller
{
    private const CONTACT_FIELDS = [
        'name' => ['required', 'string', 'max:255'],
        'phone' => ['nullable', 'string', 'max:30'],
        'email' => ['nullable', 'email', 'max:255'],
        'country_code' => ['required', 'alpha:ascii', 'size:2', 'uppercase'],
        'city' => ['nullable', 'string', 'max:100'],
        'district' => ['nullable', 'string', 'max:100'],
        'address' => ['nullable', 'string', 'max:2000'],
        'postal_code' => ['nullable', 'string', 'max:20'],
        'latitude' => ['nullable', 'numeric', 'between:-90,90'],
        'longitude' => ['nullable', 'numeric', 'between:-180,180'],
    ];

    private const CLINIC_FIELDS = [
        'description' => ['nullable', 'string', 'max:5000'],
        'website' => ['nullable', 'url:http,https', 'max:255'],
        'type' => ['nullable', 'string', 'max:50'],
    ];

    public function show(Request $request, Business $business): JsonResponse
    {
        $this->authorizeOwner($request, $business);
        $validated = $request->validate(['branch_id' => ['required', 'integer']]);
        $branch = $business->branches()->where('status', 'active')->findOrFail($validated['branch_id']);

        return $this->profile($request->user(), $business, $branch);
    }

    public function update(Request $request, Business $business): JsonResponse
    {
        $this->authorizeOwner($request, $business);
        $rules = [
            'branch_id' => ['required', 'integer'],
            'owner_name' => ['required', 'string', 'max:255'],
        ];

        foreach (['clinic' => self::CONTACT_FIELDS + self::CLINIC_FIELDS, 'branch' => self::CONTACT_FIELDS] as $section => $fields) {
            foreach ($fields as $field => $fieldRules) {
                $rules[$section.'.'.$field] = $fieldRules;
            }
        }

        $validated = $request->validate($rules);
        $owner = $request->user();

        [$business, $branch] = DB::transaction(function () use ($business, $owner, $validated): array {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            abort_unless($business->allowsCrmAccess(), 403, 'CRM erişimi için klinik onayı gereklidir.');
            $branch = $business->branches()->where('status', 'active')->lockForUpdate()->findOrFail($validated['branch_id']);
            $clinic = $validated['clinic'];
            if (array_key_exists('type', $clinic) && $clinic['type'] === null) {
                $clinic['type'] = 'clinic';
            }
            $business->update($clinic);
            $branch->update($validated['branch']);
            $owner->update(['name' => $validated['owner_name']]);

            return [$business, $branch];
        }, attempts: 3);

        return $this->profile($owner, $business, $branch);
    }

    private function authorizeOwner(Request $request, Business $business): void
    {
        abort_unless($business->allowsCrmAccess() && $request->user()->businessMemberships()
            ->where('business_id', $business->id)->where('role', 'business_owner')->where('is_active', true)->exists(),
            403, 'Klinik profilini yalnızca klinik sahibi düzenleyebilir.');
    }

    private function profile(User $owner, Business $business, Branch $branch): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'owner_name' => $owner->name,
            'clinic' => $business->only(array_keys(self::CONTACT_FIELDS + self::CLINIC_FIELDS)),
            'branch' => $branch->only(array_keys(self::CONTACT_FIELDS)),
        ]])->header('Cache-Control', 'private, no-store');
    }
}
