<?php

namespace App\Http\Middleware;

use App\Services\PinService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartnerPin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'vendor') {
            return $next($request);
        }

        if (app(PinService::class)->hasPin($user)) {
            return $next($request);
        }

        if ($request->routeIs(
            'site.partner.setup-pin',
            'site.partner.setup-pin.post',
            'site.logout',
            'auth.two-factor.*',
        )) {
            return $next($request);
        }

        return redirect()->route('site.partner.setup-pin')
            ->with('status', 'Create your 4-digit PIN to secure your partner account.');
    }
}
