<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClinicBranchOperationsAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $membership = $request->attributes->get(EnsureClinicCrmAccess::class);
        abort_unless($membership?->role === 'business_owner', 403, 'Şube operasyonlarını yalnızca klinik sahibi yönetebilir.');

        return $next($request);
    }
}
