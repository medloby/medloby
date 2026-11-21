<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClinicCrmAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $branch = $request->route('branch');
        $membership = $branch instanceof Branch ? $request->user()->membershipForBranch($branch) : null;

        if (! $membership) {
            return response()->json([
                'success' => false,
                'message' => 'Bu şubeye erişim yetkiniz yok.',
            ], 403);
        }

        if ($branch->status !== 'active' || ! $branch->business?->allowsCrmAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'CRM erişimi için klinik onaylı ve şube aktif olmalıdır.',
            ], 403);
        }

        $request->attributes->set(self::class, $membership);

        return $next($request);
    }
}
