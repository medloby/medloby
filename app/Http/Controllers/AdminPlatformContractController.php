<?php

namespace App\Http\Controllers;

use App\Models\PlatformContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminPlatformContractController extends Controller
{
    /**
     * Platform sözleşmelerini listeler.
     */
    public function index(Request $request): JsonResponse
    {
        $contracts = PlatformContract::query()
            ->with([
                'createdBy:id,name,email',
                'updatedBy:id,name,email',
            ])
            ->latest()
            ->paginate(
                min(
                    max((int) $request->input('per_page', 20), 1),
                    100
                )
            );

        return response()->json([
            'success' => true,
            'data' => $contracts,
        ]);
    }

    /**
     * Tek bir platform sözleşmesinin detayını gösterir.
     */
    public function show(PlatformContract $platformContract): JsonResponse
    {
        $platformContract->load([
            'createdBy:id,name,email',
            'updatedBy:id,name,email',
        ]);

        return response()->json([
            'success' => true,
            'data' => $platformContract,
        ]);
    }

    /**
     * Yeni platform sözleşmesi oluşturur.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contract_type' => [
                'required',
                'string',
                'max:50',
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'version' => [
                'required',
                'string',
                'max:30',
            ],
            'content' => [
                'required',
                'string',
            ],
            'is_required' => [
                'sometimes',
                'boolean',
            ],
            'effective_at' => [
                'nullable',
                'date',
            ],
            'expires_at' => [
                'nullable',
                'date',
                'after_or_equal:effective_at',
            ],
        ]);

        $admin = $request->user();

        $alreadyExists = PlatformContract::query()
            ->where('contract_type', $validated['contract_type'])
            ->where('version', $validated['version'])
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'version' => [
                    'Bu sözleşme türü için bu versiyon zaten mevcut.',
                ],
            ]);
        }

        $contract = PlatformContract::create([
            'contract_type' => $validated['contract_type'],
            'title' => $validated['title'],
            'version' => $validated['version'],
            'content' => $validated['content'],
            'status' => 'draft',
            'is_required' => $validated['is_required'] ?? true,
            'effective_at' => $validated['effective_at'] ?? null,
            'published_at' => null,
            'expires_at' => $validated['expires_at'] ?? null,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $contract->load([
            'createdBy:id,name,email',
            'updatedBy:id,name,email',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Platform sözleşmesi taslak olarak oluşturuldu.',
            'data' => $contract,
        ], 201);
    }

    /**
     * Taslak sözleşmeyi günceller.
     */
    public function update(
        Request $request,
        PlatformContract $platformContract
    ): JsonResponse {
        if ($platformContract->status !== 'draft') {
            throw ValidationException::withMessages([
                'contract' => [
                    'Yalnızca taslak durumundaki sözleşmeler güncellenebilir.',
                ],
            ]);
        }

        $validated = $request->validate([
            'contract_type' => [
                'sometimes',
                'string',
                'max:50',
            ],
            'title' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'version' => [
                'sometimes',
                'string',
                'max:30',
            ],
            'content' => [
                'sometimes',
                'string',
            ],
            'is_required' => [
                'sometimes',
                'boolean',
            ],
            'effective_at' => [
                'nullable',
                'date',
            ],
            'expires_at' => [
                'nullable',
                'date',
                'after_or_equal:effective_at',
            ],
        ]);

        $contractType = $validated['contract_type']
            ?? $platformContract->contract_type;

        $version = $validated['version']
            ?? $platformContract->version;

        $duplicate = PlatformContract::query()
            ->where('contract_type', $contractType)
            ->where('version', $version)
            ->whereKeyNot($platformContract->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'version' => [
                    'Bu sözleşme türü için bu versiyon zaten mevcut.',
                ],
            ]);
        }

        $validated['updated_by_user_id'] = $request->user()->id;

        $platformContract->update($validated);

        $platformContract->load([
            'createdBy:id,name,email',
            'updatedBy:id,name,email',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Platform sözleşmesi güncellendi.',
            'data' => $platformContract,
        ]);
    }

    /**
     * Taslak sözleşmeyi yayınlar.
     */
    public function publish(
        Request $request,
        PlatformContract $platformContract
    ): JsonResponse {
        $result = DB::transaction(function () use (
            $request,
            $platformContract
        ) {
            $platformContract->refresh();

            if ($platformContract->status !== 'draft') {
                throw ValidationException::withMessages([
                    'contract' => [
                        'Yalnızca taslak durumundaki sözleşmeler yayınlanabilir.',
                    ],
                ]);
            }

            $now = now();

            $platformContract->update([
                'status' => 'published',
                'published_at' => $now,
                'effective_at' => $platformContract->effective_at ?? $now,
                'updated_by_user_id' => $request->user()->id,
            ]);

            $platformContract->load([
                'createdBy:id,name,email',
                'updatedBy:id,name,email',
            ]);

            return $platformContract;
        });

        return response()->json([
            'success' => true,
            'message' => 'Platform sözleşmesi yayınlandı.',
            'data' => $result,
        ]);
    }

    /**
     * Yayınlanmış sözleşmeyi pasifleştirir.
     */
    public function deactivate(
        Request $request,
        PlatformContract $platformContract
    ): JsonResponse {
        $result = DB::transaction(function () use (
            $request,
            $platformContract
        ) {
            $platformContract->refresh();

            if ($platformContract->status !== 'published') {
                throw ValidationException::withMessages([
                    'contract' => [
                        'Yalnızca yayınlanmış sözleşmeler pasifleştirilebilir.',
                    ],
                ]);
            }

            $platformContract->update([
                'status' => 'inactive',
                'updated_by_user_id' => $request->user()->id,
            ]);

            $platformContract->load([
                'createdBy:id,name,email',
                'updatedBy:id,name,email',
            ]);

            return $platformContract;
        });

        return response()->json([
            'success' => true,
            'message' => 'Platform sözleşmesi pasifleştirildi.',
            'data' => $result,
        ]);
    }
}