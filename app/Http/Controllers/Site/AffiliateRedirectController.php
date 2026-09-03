<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\AffiliateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AffiliateRedirectController extends Controller
{
    public function __invoke(string $code, Request $request, AffiliateService $affiliates): RedirectResponse
    {
        $affiliate = $affiliates->findByCode($code);
        if (! $affiliate) {
            // Distinguish unknown vs unverified so we do not silently credit scammers.
            $raw = \App\Models\Vendor::query()
                ->where('category', 'affiliate')
                ->where('affiliate_code', strtoupper(trim($code)))
                ->first();

            $message = $raw && ! app(\App\Services\AffiliateEligibilityService::class)->canSharePromo($raw)
                ? __('site.affiliate_portal.link_not_verified')
                : __('site.affiliate_portal.link_not_recognized');

            return redirect()->route('site.register.borrower')
                ->with('warning', $message);
        }

        $affiliates->trackClick($affiliate, $request);
        session(['affiliate_code' => $affiliate->affiliate_code]);

        return redirect()
            ->route('site.register.borrower', ['aff' => $affiliate->affiliate_code])
            ->with('status', __('site.affiliate_portal.link_welcome'));
    }
}
