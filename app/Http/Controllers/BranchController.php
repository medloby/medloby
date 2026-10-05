<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    /**
     * Kullanıcının aktif işletme üyeliğini kontrol eder.
     */
    private function ensureBusinessMembership(
        Request $request,
        int $businessId
    ): void {
        $isMember = $request->user()
            ->businessMemberships()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->exists();

        if (! $isMember) {
            abort(
                403,
                'Bu işletmeye erişim yetkiniz yok.'
            );
        }
    }

    /**
     * İşletme sahibinin veya ilgili izne sahip kullanıcının
     * şube yönetimi yapabilmesini kontrol eder.
     */
    private function ensureBranchManagementPermission(
        Request $request,
        int $businessId
    ): void {
        $user = $request->user();

        $membership = $user->businessMemberships()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->first();

        if (! $membership) {
            abort(
                403,
                'Bu işletmeye erişim yetkiniz yok.'
            );
        }

        if ($membership->role === 'business_owner') {
            return;
        }

        if (! $user->hasBusinessPermission(
            $businessId,
            'branches.manage'
        )) {
            abort(
                403,
                'Şube yönetimi yetkiniz bulunmuyor.'
            );
        }
    }

    /**
     * İşletmenin erişilebilir şubelerini listeler.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_id' => [
                'required',
                'integer',
                'exists:businesses,id',
            ],
            'status' => [
                'nullable',
                'string',
                'in:active,inactive',
            ],
        ]);

        $businessId = (int) $validated['business_id'];

        $this->ensureBusinessMembership(
            $request,
            $businessId
        );

        $branchIds = $request->user()
            ->accessibleBranchIds($businessId);

        $query = Branch::query()
            ->where('business_id', $businessId)
            ->whereIn('id', $branchIds)
            ->orderBy('name');

        if (isset($validated['status'])) {
            $query->where(
                'status',
                $validated['status']
            );
        }

        $branches = $query->get();

        return response()->json([
            'success' => true,
            'data' => $branches,
        ]);
    }

    /**
     * Yeni şube oluşturur.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_id' => [
                'required',
                'integer',
                'exists:businesses,id',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches', 'slug')
                    ->where(
                        fn ($query) => $query->where(
                            'business_id',
                            $request->input('business_id')
                        )
                    ),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'country_code' => [
                'nullable',
                'string',
                'size:2',
            ],
            'city' => [
                'nullable',
                'string',
                'max:255',
            ],
            'district' => [
                'nullable',
                'string',
                'max:255',
            ],
            'address' => [
                'nullable',
                'string',
            ],
            'postal_code' => [
                'nullable',
                'string',
                'max:20',
            ],
            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
            'status' => [
                'nullable',
                'string',
                'in:active,inactive',
            ],
        ]);

        $businessId = (int) $validated['business_id'];

        $this->ensureBranchManagementPermission(
            $request,
            $businessId
        );

        $branch = Branch::create([
            ...$validated,
            'country_code' => $validated['country_code'] ?? 'TR',
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Şube başarıyla oluşturuldu.',
            'data' => $branch,
        ], 201);
    }

    /**
     * Şube detayını gösterir.
     */
    public function show(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $this->ensureBusinessMembership(
            $request,
            (int) $branch->business_id
        );

        if (! $request->user()->hasBusinessBranchAccess(
            (int) $branch->business_id,
            (int) $branch->id
        )) {
            abort(
                403,
                'Bu şubeye erişim yetkiniz yok.'
            );
        }

        $branch->load([
            'business',
            'businessUsers',
            'doctors.person',
            'treatments',
        ]);

        return response()->json([
            'success' => true,
            'data' => $branch,
        ]);
    }

    /**
     * Şube bilgilerini günceller.
     */
    public function update(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $businessId = (int) $branch->business_id;

        $this->ensureBranchManagementPermission(
            $request,
            $businessId
        );

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('branches', 'slug')
                    ->where(
                        fn ($query) => $query->where(
                            'business_id',
                            $businessId
                        )
                    )
                    ->ignore($branch->id),
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
            ],
            'country_code' => [
                'sometimes',
                'nullable',
                'string',
                'size:2',
            ],
            'city' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'district' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'address' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'postal_code' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],
            'latitude' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                'sometimes',
                'nullable',
                'numeric',
                'between:-180,180',
            ],
            'status' => [
                'sometimes',
                'required',
                'string',
                'in:active,inactive',
            ],
        ]);

        $branch->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Şube başarıyla güncellendi.',
            'data' => $branch->fresh(),
        ]);
    }

    /**
     * Şubeyi pasifleştirir.
     *
     * Fiziksel kaydı silmez.
     */
    public function deactivate(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $businessId = (int) $branch->business_id;

        $this->ensureBranchManagementPermission(
            $request,
            $businessId
        );

        $branch->update([
            'status' => 'inactive',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Şube pasifleştirildi.',
            'data' => $branch->fresh(),
        ]);
    }
}