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

        $redirect = app(PartnerPortalRedirectService::class);
        $vendor = Vendor::query()->where('user_id', $user->id)->first();

        if (! $vendor) {
            return redirect()->to(
                $user->role === 'borrower' && $request->routeIs('*.supplier.profile')
                    ? route('site.borrower.profile')
                    : $redirect->homeUrl($user)
            );
        }

        if (! $vendor->isSupplier()) {
            return redirect()
                ->to($redirect->homeUrl($user))
                ->with('warning', __('site.partner_portal.redirect_from_supplier'));
        }

        return $next($request);
    }
}
