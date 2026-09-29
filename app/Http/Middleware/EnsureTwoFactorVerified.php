<?php

namespace App\Http\Middleware;

use App\Services\WebTwoFactorAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next, string $context): Response
    {
        if ($context === 'partner'
            && app(\App\Services\AdminRoleViewService::class)->isActive()
            && (app(\App\Services\AdminRoleViewService::class)->active()['subject_type'] ?? null) === 'partner') {
            return $next($request);
        }

        $twoFactor = app(WebTwoFactorAuthService::class);

        $user = $request->user('admin') ?? $request->user();
        if (! $user) {
            return $next($request);
        }

        if ($context === 'partner' && $user->role !== 'vendor') {
            return $next($request);
        }

        $roles = app(\App\Services\RoleService::class);
        $effectiveContext = $context;
        if ($context === 'admin' && $roles->isStaffUser($user) && ! $roles->hasConsoleAccess($user)) {
            $effectiveContext = 'staff';
        }

        if (! $twoFactor->isRequired($effectiveContext)) {
            return $next($request);
        }

        if ($twoFactor->mustEnroll($user, $effectiveContext)) {
            return redirect()->route('auth.two-factor.setup', ['context' => $effectiveContext]);
        }

        if ($twoFactor->needsChallenge($user, $request, $effectiveContext)) {
            return redirect()->route('auth.two-factor.challenge', ['context' => $effectiveContext]);
        }

        return $next($request);
    }
}
