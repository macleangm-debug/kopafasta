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
            return redirect()->route('site.register.borrower')
                ->with('warning', 'Affiliate link not recognized. You can still register normally.');
        }

        $affiliates->trackClick($affiliate, $request);
        session(['affiliate_code' => $affiliate->affiliate_code]);

        return redirect()
            ->route('site.register.borrower', ['aff' => $affiliate->affiliate_code])
            ->with('status', 'Welcome! Complete registration to continue with your affiliate offer.');
    }
}
