<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureActiveMembership — blocks restricted actions (loan applications, withdrawals,
 * premium services) when the authenticated borrower's membership is expired.
 *
 * Allowed even when expired: dashboard, history, renewal payment.
 *
 * Usage in routes: ->middleware('membership.active')
 */
class EnsureActiveMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        // Compulsory borrower membership is retired. Historical fee records remain;
        // this middleware must never block credit. Partner/affiliate membership is unchanged.
        return $next($request);

        $user = $request->user();

        // No user, or non-borrower roles, are unaffected.
        if (! $user || ! method_exists($user, 'customer')) {
            return $next($request);
        }

        $customer = $user->customer; // relation expected on User

        // No linked customer (e.g. admin/vendor/investor) — pass through.
        if (! $customer) {
            return $next($request);
        }

        if ($customer->isMembershipActive() || $customer->isMembershipInGrace()) {
            return $next($request);
        }

        // Expired / never issued.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Membership expired. Please renew to continue.',
                'code'    => 'membership_expired',
                'renew_url' => route('site.membership.renew'),
            ], 403);
        }

        return redirect()->route('site.membership.renew')
            ->with('warning', 'Your membership has expired. Renew to access this feature.');
    }
}
