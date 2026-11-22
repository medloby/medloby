<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Symfony\Component\HttpFoundation\Response;

class EnsureMedlobyAccountVerified extends EnsureEmailIsVerified
{
    public function handle($request, Closure $next, $redirectToRoute = null): Response
    {
        if ($request->user()?->staff_business_id !== null) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
