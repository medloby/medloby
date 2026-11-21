<?php

namespace App\Http\Controllers;

use App\Models\PlatformContract;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminPlatformContractController extends Controller
{
    /**
     * Platform sözleşmelerini listeler.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'contract_type' => ['sometimes', 'required', 'string', 'max:50'],
        ]);

        $contracts = PlatformContract::query()
            ->when(isset($filters['contract_type']), fn ($query) => $query->where('contract_type', $filters['contract_type']))
            ->with([
                'createdBy:id,name,email',
                'updatedBy:id,name,email',
            ])
            ->latest()
            ->orderByDesc('id')
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

        $data = $platformContract->toArray();
        if ($platformContract->status === 'draft') {
            $data['draft_revision'] = $this->draftRevision($platformContract);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
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

        $contract = PlatformContract::query()->createOrFirst([
            'contract_type' => $validated['contract_type'],
            'version' => $validated['version'],
        ], [
            'title' => $validated['title'],
            'content' => $validated['content'],
            'status' => 'draft',
            'is_required' => $validated['is_required'] ?? true,
            'effective_at' => $validated['effective_at'] ?? null,
            'published_at' => null,
            'expires_at' => $validated['expires_at'] ?? null,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        if (! $contract->wasRecentlyCreated) {
            throw $this->duplicateVersion();
        }

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
        try {
            $platformContract = DB::transaction(function () use ($request, $platformContract) {
                $platformContract = PlatformContract::query()->lockForUpdate()->findOrFail($platformContract->getKey());

                if ($platformContract->status !== 'draft') {
                    throw ValidationException::withMessages([
                        'contract' => [
                            'Yalnızca taslak durumundaki sözleşmeler güncellenebilir.',
                        ],
                    ]);
                }

                $validated = $request->validate([
                    'draft_revision' => ['sometimes', 'required', 'string', 'size:64'],
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

                $revision = Arr::pull($validated, 'draft_revision');
                if ($revision !== null && ! hash_equals($this->draftRevision($platformContract), $revision)) {
                    abort(409, 'Bu taslak başka bir oturumda güncellendi. Değişikliklerinizi kopyalayıp güncel taslağı yeniden açın.');
                }

                $validated['updated_by_user_id'] = $request->user()->id;
                $platformContract->update($validated);

                return $platformContract->load([
                    'createdBy:id,name,email',
                    'updatedBy:id,name,email',
                ]);
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicateVersion();
        }

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
            $platformContract = PlatformContract::query()->lockForUpdate()->findOrFail($platformContract->getKey());

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
        }, attempts: 3);

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
            $platformContract = PlatformContract::query()->lockForUpdate()->findOrFail($platformContract->getKey());

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
        }, attempts: 3);

        return response()->json([
            'success' => true,
            'message' => 'Platform sözleşmesi pasifleştirildi.',
            'data' => $result,
        ]);
    }

    private function draftRevision(PlatformContract $contract): string
    {
        return hash('sha256', json_encode($contract->getRawOriginal(), JSON_THROW_ON_ERROR));
    }

    private function duplicateVersion(): ValidationException
    {
        return ValidationException::withMessages([
            'version' => ['Bu sözleşme türü için bu versiyon zaten mevcut.'],
        ]);
    }
}
