<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\SocialMediaAccount;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SocialMediaAccountController extends Controller
{
    public function index(
        Branch $branch
    ): JsonResponse {
        $accounts = $branch->status === 'active' && $branch->business?->allowsCrmAccess()
            ? $branch->socialMediaAccounts()->where('is_active', true)->orderBy('id')->get()
                ->filter(fn (SocialMediaAccount $account) => $account->hasWebProfile())->values()
            : [];

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    public function manage(Branch $branch): JsonResponse
    {
        $accounts = $branch->socialMediaAccounts()->orderBy('id')
            ->get(['id', 'platform', 'username', 'page_name', 'profile_url', 'is_active', 'sort_order']);

        return response()->json(['success' => true, 'data' => $accounts])->header('Cache-Control', 'private, no-store');
    }

    public function store(
        Request $request,
        Branch $branch
    ): JsonResponse {
        $validated = $request->validate([
            'platform' => [
                'required',
                'string',
                'max:50',
            ],
            'username' => [
                'nullable',
                'string',
                'max:255',
            ],
            'page_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'profile_url' => [
                'required',
                'url:http,https',
                'max:2048',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
        ]);

        $platform = strtolower(
            trim($validated['platform'])
        );

        $account = $branch->socialMediaAccounts()->firstOrCreate(['platform' => $platform], [
            'username' => $validated['username'] ?? null,
            'page_name' => $validated['page_name'] ?? null,
            'profile_url' => $validated['profile_url'],
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        if (! $account->wasRecentlyCreated) {
            return $this->duplicatePlatform();
        }

        return response()->json([
            'success' => true,
            'data' => $account->fresh([
                'branch:id,name',
            ]),
        ], 201);
    }

    public function show(
        Branch $branch,
        SocialMediaAccount $socialMediaAccount
    ): JsonResponse {
        if (
            $socialMediaAccount->branch_id
            !== $branch->id
            || ! $socialMediaAccount->is_active
            || ! $socialMediaAccount->hasWebProfile()
            || $branch->status !== 'active'
            || ! $branch->business?->allowsCrmAccess()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu şube için yayımlanmış sosyal medya bağlantısı bulunamadı.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $socialMediaAccount->load([
                'branch:id,name',
            ]),
        ]);
    }

    public function update(
        Request $request,
        Branch $branch,
        SocialMediaAccount $socialMediaAccount
    ): JsonResponse {
        if (
            $socialMediaAccount->branch_id
            !== $branch->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu sosyal medya hesabı belirtilen merkeze ait değil.',
            ], 404);
        }

        $validated = $request->validate([
            'platform' => [
                'sometimes',
                'string',
                'max:50',
            ],
            'username' => [
                'nullable',
                'string',
                'max:255',
            ],
            'page_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'profile_url' => [
                'sometimes',
                'url:http,https',
                'max:2048',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
        ]);

        if (array_key_exists('platform', $validated)) {
            $validated['platform'] = strtolower(
                trim($validated['platform'])
            );
        }

        if (($validated['is_active'] ?? false) && ! isset($validated['profile_url']) && ! $socialMediaAccount->hasWebProfile()) {
            throw ValidationException::withMessages(['profile_url' => 'Yayımlamak için http veya https ile başlayan bir bağlantı girin.']);
        }

        try {
            $socialMediaAccount->update($validated);
        } catch (UniqueConstraintViolationException) {
            return $this->duplicatePlatform();
        }

        return response()->json([
            'success' => true,
            'data' => $socialMediaAccount->fresh([
                'branch:id,name',
            ]),
        ]);
    }

    public function deactivate(
        Branch $branch,
        SocialMediaAccount $socialMediaAccount
    ): JsonResponse {
        if (
            $socialMediaAccount->branch_id
            !== $branch->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu sosyal medya hesabı belirtilen merkeze ait değil.',
            ], 404);
        }

        $socialMediaAccount->update([
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => $socialMediaAccount->fresh(),
        ]);
    }

    private function duplicatePlatform(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Bu merkez için bu sosyal medya platformu zaten eklenmiş.',
        ], 422);
    }
}
