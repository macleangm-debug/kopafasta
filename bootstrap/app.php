<?php

use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\EnsureActiveMembership;
use App\Models\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'membership.active' => EnsureActiveMembership::class,
            'borrower.pin' => \App\Http\Middleware\EnsureBorrowerPin::class,
            'role' => EnsureUserRole::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
        ]);

        $middleware->redirectGuestsTo(function ($request) {
            return route('admin.login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->stopIgnoring(AuthorizationException::class);

        $exceptions->report(function (AuthorizationException $e): void {
            try {
                $request = app('request');
                if (! str_starts_with(ltrim($request->path(), '/'), 'api/')) {
                    return;
                }

                AuditLog::create([
                    'user_id' => Auth::id(),
                    'event' => 'authorization.denied',
                    'auditable_type' => null,
                    'auditable_id' => null,
                    'old_values' => null,
                    'new_values' => json_encode([
                        'method' => $request->method(),
                        'path' => $request->path(),
                        'route' => optional($request->route())->getName(),
                        'params' => $request->route() ? $request->route()->parameters() : [],
                        'message' => $e->getMessage(),
                    ]),
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                ]);
            } catch (\Throwable $t) {
                // Never let audit logging break request handling.
            }
        });
    })->create();
