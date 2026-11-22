<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use App\Services\ClinicContactPublication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    private const BRANCH_SUMMARY = ['id', 'name', 'city', 'district', 'status'];

    public function __construct(private ClinicContactPublication $publication) {}

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

            $allowed = $section === 'clinic' ? ClinicContactPublication::CLINIC_FIELDS : ClinicContactPublication::BRANCH_FIELDS;
            $rules[$section.'.public_contact_fields'] = ['sometimes', 'array', 'list', 'max:'.count($allowed)];
            $rules[$section.'.public_contact_fields.*'] = ['required', 'string', 'distinct', Rule::in(array_keys($allowed))];
        }

        $validated = $request->validate($rules);
        $owner = $request->user();

        [$business, $branch] = DB::transaction(function () use ($request, $business, $owner, $validated): array {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $this->authorizeOwner($request, $business);
            $branch = $business->branches()->where('status', 'active')->lockForUpdate()->findOrFail($validated['branch_id']);
            $clinic = $validated['clinic'];
            if (array_key_exists('type', $clinic) && $clinic['type'] === null) {
                $clinic['type'] = 'clinic';
            }
            $this->validatePublication($business, $clinic);
            $this->validatePublication($branch, $validated['branch']);
            $business->update($clinic);
            $branch->update($validated['branch']);
            $owner->update(['name' => $validated['owner_name']]);

            return [$business, $branch];
        }, attempts: 3);

        return $this->profile($owner, $business, $branch);
    }

    private function validatePublication(Business|Branch $profile, array $changes): void
    {
        $isClinic = $profile instanceof Business;
        $fields = $isClinic ? ClinicContactPublication::CLINIC_FIELDS : ClinicContactPublication::BRANCH_FIELDS;
        foreach ($changes['public_contact_fields'] ?? [] as $field) {
            $value = array_key_exists($field, $changes) ? $changes[$field] : $profile->{$field};
            if ($this->publication->href($field, $value) === null) {
                $section = $isClinic ? 'clinic' : 'branch';
                throw ValidationException::withMessages([
                    $section.'.public_contact_fields' => 'Klinik sayfasında göstermek için “'.$fields[$field].'” alanına geçerli bir değer girin.',
                ]);
            }
        }
    }

    public function branches(Request $request, Business $business): JsonResponse
    {
        $this->authorizeOwner($request, $business);

        return response()->json(['success' => true, 'data' => [
            'branches' => $business->branches()->orderBy('name')->orderBy('id')->get(self::BRANCH_SUMMARY),
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function storeBranch(Request $request, Business $business): JsonResponse
    {
        $branch = DB::transaction(function () use ($request, $business): Branch {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $this->authorizeOwner($request, $business);
            $fields = array_intersect_key(self::CONTACT_FIELDS, array_flip(['name', 'city', 'district', 'address']));
            $validated = $request->validate($fields);

            return $business->branches()->create([
                ...$validated,
                'slug' => $business->uniqueBranchSlug($validated['name']),
                'country_code' => $business->country_code ?? 'TR',
                'status' => 'active',
            ]);
        }, attempts: 3);

        return response()->json(['success' => true, 'data' => $branch->only(self::BRANCH_SUMMARY)], 201)
            ->header('Cache-Control', 'private, no-store');
    }

    public function updateBranchStatus(Request $request, Business $business, Branch $branch): JsonResponse
    {
        $branch = DB::transaction(function () use ($request, $business, $branch): Branch {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $this->authorizeOwner($request, $business);
            $validated = $request->validate(['status' => ['required', 'in:active,inactive']]);

            $branch = $business->branches()->lockForUpdate()->findOrFail($branch->id);
            $branch->update($validated);

            return $branch;
        }, attempts: 3);

        return response()->json(['success' => true, 'data' => $branch->only(self::BRANCH_SUMMARY)])
            ->header('Cache-Control', 'private, no-store');
    }

    private function authorizeOwner(Request $request, Business $business): void
    {
        abort_unless($business->allowsCrmAccess() && $request->user()->businessMemberships()
            ->where('business_id', $business->id)->where('role', 'business_owner')->where('is_active', true)->exists(),
            403, 'Bu kliniği yönetme yetkiniz yok.');
    }

    private function profile(User $owner, Business $business, Branch $branch): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'owner_name' => $owner->name,
            'clinic' => $business->only(array_keys(self::CONTACT_FIELDS + self::CLINIC_FIELDS))
                + ['public_contact_fields' => $business->public_contact_fields ?? []],
            'branch' => $branch->only(array_keys(self::CONTACT_FIELDS))
                + ['public_contact_fields' => $branch->public_contact_fields ?? []],
        ]])->header('Cache-Control', 'private, no-store');
    }
}
