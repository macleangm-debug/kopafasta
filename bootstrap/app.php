<?php

use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\EnsureActiveMembership;
use App\Models\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'webhooks/payin',
        ]);

        $middleware->alias([
            'membership.active' => EnsureActiveMembership::class,
            'borrower.pin' => \App\Http\Middleware\EnsureBorrowerPin::class,
            'partner.pin' => \App\Http\Middleware\EnsurePartnerPin::class,
            'supplier.portal' => \App\Http\Middleware\EnsureSupplierPortal::class,
            'role' => EnsureUserRole::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
            'staff' => \App\Http\Middleware\EnsureStaffUser::class,
            'console' => \App\Http\Middleware\EnsureConsoleAccess::class,
            'two_factor' => \App\Http\Middleware\EnsureTwoFactorVerified::class,
        ]);

        $middleware->appendToGroup('web', \App\Http\Middleware\QuietBrowserNotifications::class);

        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('staff', 'staff/*')) {
                return route('staff.login');
            }

            if ($request->is('borrower*', 'apply*', 'login', 'register*')) {
                return route('site.login');
            }

            return route('admin.login');
        });

        $middleware->redirectUsersTo(function ($request) {
            if ($request->is('staff', 'staff/*')) {
                return route('staff.dashboard');
            }

            if ($request->is('admin', 'admin/*')) {
                return route('admin.dashboard');
            }

            $user = Auth::guard('web')->user();

            if ($user) {
                return match ($user->role) {
                    'borrower' => route('site.borrower.dashboard'),
                    'vendor'   => route('site.partner.dashboard'),
                    'investor' => route('site.investor.dashboard'),
                    default    => route('admin.dashboard'),
                };
            }

            return route('site.home');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->stopIgnoring(AuthorizationException::class);

        $exceptions->render(function (PostTooLargeException $e, $request) {
            $message = __('borrower.profile.upload_too_large');

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            return redirect()->back()->withErrors(['upload' => $message]);
        });

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
