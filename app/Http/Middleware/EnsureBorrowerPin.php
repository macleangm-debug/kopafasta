<?php

namespace App\Http\Middleware;

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

        if (app(PinService::class)->hasPin($user)) {
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

        return redirect()->route('site.borrower.setup-pin')
            ->with('status', 'Set your 4-digit PIN to secure your account.');
    }
}
