<?php

namespace App\Http\Middleware;

use App\Services\PinRecoveryChallengeService;
use App\Services\PinService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBorrowerPin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'borrower') {
            return $next($request);
        }

        $hasPin = app(PinService::class)->hasPin($user);
        $hasRecovery = app(PinRecoveryChallengeService::class)->hasEnrolledAnswers($user);

        if ($hasPin && $hasRecovery) {
            return $next($request);
        }

        if ($request->routeIs(
            'site.borrower.setup-pin',
            'site.borrower.setup-pin.post',
            'site.logout',
            'site.membership.*',
        )) {
            return $next($request);
        }

        $message = ! $hasPin
            ? __('site.auth.pin_recovery.middleware_need_pin')
            : __('site.auth.pin_recovery.middleware_need_recovery');

        return redirect()->route('site.borrower.setup-pin')->with('status', $message);
    }
}
