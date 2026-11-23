<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminPatientAccountController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'verification' => ['sometimes', 'required', 'in:verified,pending'],
        ]);
        $search = trim($filters['search'] ?? '');
        $accountId = filter_var(ltrim($search, '#'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $accounts = User::query()->whereHas('patientProfile')->with('patientProfile:id,user_id,status')
            ->when($search !== '', fn (Builder $users) => $users->where(function (Builder $matches) use ($search, $accountId): void {
                $matches->whereLike('name', '%'.$search.'%')->orWhere('email', $search);
                if ($accountId !== false) {
                    $matches->orWhereKey($accountId);
                }
            }))
            ->when(($filters['verification'] ?? null) === 'verified', fn (Builder $users) => $users->whereNotNull('email_verified_at'))
            ->when(($filters['verification'] ?? null) === 'pending', fn (Builder $users) => $users->whereNull('email_verified_at'))
            ->latest('created_at')->latest('id')
            ->paginate(20, ['id', 'name', 'email_verified_at', 'created_at'])
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'verified' => $user->hasVerifiedEmail(),
                'profile_status' => $user->patientProfile?->status,
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => $accounts])->header('Cache-Control', 'private, no-store');
    }
}
