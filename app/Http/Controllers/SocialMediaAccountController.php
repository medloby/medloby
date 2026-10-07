<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\SocialMediaAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SocialMediaAccountController extends Controller
{
    public function index(
        Branch $branch
    ): JsonResponse {
        $accounts = $branch
            ->socialMediaAccounts()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
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
                'url',
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

        $exists = SocialMediaAccount::query()
            ->where('branch_id', $branch->id)
            ->where('platform', $platform)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Bu merkez için bu sosyal medya platformu zaten eklenmiş.',
            ], 422);
        }

        $account = SocialMediaAccount::create([
            'branch_id' => $branch->id,
            'platform' => $platform,
            'username' => $validated['username'] ?? null,
            'page_name' => $validated['page_name'] ?? null,
            'profile_url' => $validated['profile_url'],
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'data' => $account->fresh([
                'branch',
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
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bu sosyal medya hesabı belirtilen merkeze ait değil.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $socialMediaAccount->load([
                'branch',
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
                'url',
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

            $duplicate = SocialMediaAccount::query()
                ->where('branch_id', $branch->id)
                ->where(
                    'platform',
                    $validated['platform']
                )
                ->where(
                    'id',
                    '!=',
                    $socialMediaAccount->id
                )
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bu merkez için bu sosyal medya platformu zaten eklenmiş.',
                ], 422);
            }
        }

        $socialMediaAccount->update($validated);

        return response()->json([
            'success' => true,
            'data' => $socialMediaAccount->fresh([
                'branch',
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
}