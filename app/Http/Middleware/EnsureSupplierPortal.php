<?php

namespace App\Http\Middleware;

use App\Models\Vendor;
use App\Services\PartnerPortalRedirectService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupplierPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $vendor = Vendor::query()->where('user_id', $user->id)->first();
        if ($vendor && ! $vendor->isSupplier()) {
            return redirect()
                ->to(app(PartnerPortalRedirectService::class)->homeUrl($user))
                ->with('warning', __('site.partner_portal.redirect_from_supplier'));
        }

        return $next($request);
    }
}
