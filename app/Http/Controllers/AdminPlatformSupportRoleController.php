<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPlatformSupportRoleController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, User $user): JsonResponse
    {
        $request->validate(['active' => ['required', 'boolean']]);
        $role = $request->boolean('active') ? User::PLATFORM_SUPPORT_ROLE : null;
        $actorId = $request->user()->id;

        DB::transaction(function () use ($user, $role, $actorId): void {
            $account = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_if($account->is_admin || ! in_array($account->platform_role, [null, User::PLATFORM_SUPPORT_ROLE], true),
                422, 'Bu hesabın platform yetkileri bu işlemden değiştirilemez.');
            abort_if($role !== null && ! $account->hasVerifiedEmail(), 422, 'Doğrulanmış bir hesap seçin.');
            if ($account->platform_role === $role) {
                return;
            }

            $account->forceFill(['platform_role' => $role])->save();
            $account->tokens()->delete();
            DB::table('platform_role_changes')->insert([
                'user_id' => $account->id, 'actor_user_id' => $actorId,
                'action' => $role === null ? 'support_revoked' : 'support_granted', 'created_at' => now(),
            ]);
        }, attempts: 3);

        return response()->json(['success' => true, 'data' => ['id' => $user->id, 'active' => $role !== null]]);
    }
}
