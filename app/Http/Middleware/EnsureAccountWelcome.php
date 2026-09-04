<?php

namespace App\Http\Middleware;

use App\Services\AccountWelcomeService;
use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountWelcome
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if ($request->routeIs(
            'site.account-welcome.show',
            'site.account-welcome.complete',
            'site.logout',
            'site.locale.update',
            'site.country.update',
            'site.borrower.setup-pin',
            'site.borrower.setup-pin.post',
            'site.borrower.setup-pin.swap',
            'site.partner.setup-pin',
            'site.partner.setup-pin.post',
            'auth.two-factor.*',
            'site.borrower.payments.*',
            '*.membership.checkout.*',
            '*.membership.pay',
            '*.membership.pay.post',
        )) {
            return $next($request);
        }

        // PIN / recovery setup must complete before pre-shell welcome.
        if ($user->role === 'borrower') {
            $pins = app(PinService::class);
            $recovery = app(PinRecoveryChallengeService::class);
            if (! $pins->hasPin($user) || ! $recovery->hasEnrolledAnswers($user)) {
                return $next($request);
            }
        }

        if (in_array($user->role, ['vendor', 'partner'], true) && ! app(PinService::class)->hasPin($user)) {
            return $next($request);
        }

        $payload = app(AccountWelcomeService::class)->forUser($user);
        if (! $payload) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => false,
                'redirect' => route('site.account-welcome.show'),
                'message' => __('account_welcome.kicker'),
            ], 409);
        }

        return redirect()->route('site.account-welcome.show');
    }
}
