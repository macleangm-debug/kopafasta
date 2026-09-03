<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\AffiliateAttributionService;
use App\Services\AffiliateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AffiliateRedirectController extends Controller
{
    public function __invoke(string $code, Request $request, AffiliateService $affiliates): RedirectResponse
    {
        $affiliate = $affiliates->findByCode($code);
        if (! $affiliate) {
            $raw = \App\Models\Vendor::query()
                ->where('category', 'affiliate')
                ->where(function ($query) use ($code) {
                    $normalized = strtoupper(trim($code));
                    $query->where('affiliate_code', $normalized)
                        ->orWhere('metadata', 'like', '%'.$normalized.'%');
                })
                ->first();

            $message = $raw && ! app(\App\Services\AffiliateEligibilityService::class)->canSharePromo($raw)
                ? __('site.affiliate_portal.link_not_verified')
                : __('site.affiliate_portal.link_not_recognized');

            return redirect()->route('site.register.borrower')
                ->with('warning', $message);
        }

        $affiliates->trackClick($affiliate, $request);
        app(AffiliateAttributionService::class)->establishClaim($request, $affiliate, 'link', $affiliate->affiliate_code);

        if ($user = Auth::user()) {
            $customer = Customer::query()->where('user_id', $user->id)->first();
            if ($customer) {
                $affiliates->attachAffiliate($customer, $affiliate->affiliate_code, $request);
            }
        }

        return redirect()
            ->route('site.register.borrower', ['aff' => $affiliate->affiliate_code])
            ->with('status', __('site.affiliate_portal.link_welcome'));
    }
}
