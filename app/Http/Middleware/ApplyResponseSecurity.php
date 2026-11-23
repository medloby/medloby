<?php

namespace App\Http\Middleware;

use App\Services\PublicWebsite;
use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApplyResponseSecurity
{
    public function __construct(private readonly PublicWebsite $site, private readonly Vite $vite) {}

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = $this->vite->isRunningHot() && ! app()->isProduction() ? null : $this->vite->useCspNonce();
        $response = $next($request);
        $private = $request->is('api', 'api/*') || $request->routeIs('package-offers', 'platform-admin', 'password.*')
            || $request->headers->has('Authorization');

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', $private ? 'no-referrer' : 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=(), payment=()');
        $response->headers->set('X-Frame-Options', 'DENY');

        if ($private) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }
        if ($private || ! $response->isSuccessful() || ! $this->site->canIndex($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }
        if (app()->isProduction() && $request->isSecure() && $this->site->hasSecureOrigin()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        if ($nonce !== null) {
            $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-src 'self' blob:; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
        }

        return $response;
    }
}
