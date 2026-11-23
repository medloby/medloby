<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\ApplyResponseSecurity;
use App\Http\Middleware\EnsureMedlobyAccountVerified;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(static fn (): array => [
            '^'.preg_quote((string) parse_url(config('app.url'), PHP_URL_HOST)).'$',
        ], subdomains: false);
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
        $middleware->append(ApplyResponseSecurity::class);
        $middleware->trimStrings(except: ['new_password', 'new_password_confirmation']);
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'verified' => EnsureMedlobyAccountVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (QueryException $error): void {
            $code = (string) $error->getCode();
            Log::error('Veritabanı sorgusu başarısız oldu.', [
                'exception_type' => $error::class,
                'sqlstate' => preg_match('/\A[A-Z0-9]{5}\z/', $code) ? $code : null,
                'route' => request()->route()?->uri(),
            ]);
        })->stop();
        $exceptions->dontFlash(['new_password', 'new_password_confirmation']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
