<?php

namespace App\Http\Middleware;

use App\Services\ConsoleSecondFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consumes ConsoleSecondFactorService — never hard-forces TOTP independently.
 */
class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next, string $context): Response
    {
        if ($context === 'partner'
            && app(\App\Services\AdminRoleViewService::class)->isActive()
            && (app(\App\Services\AdminRoleViewService::class)->active()['subject_type'] ?? null) === 'partner') {
            return $next($request);
        }

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

        $second = app(ConsoleSecondFactorService::class);
        $policy = $second->effectivePolicy($user, $effectiveContext, $request);

        if (! $policy['required']) {
            return $next($request);
        }

        if ($policy['must_enroll'] && filled($policy['setup_url'])) {
            return redirect()->to($policy['setup_url']);
        }

        if ($policy['needs_challenge'] && filled($policy['challenge_url'])) {
            return redirect()->to($policy['challenge_url']);
        }

        return $next($request);
    }
}
