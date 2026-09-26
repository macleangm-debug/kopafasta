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
        $affiliate = $affiliates->resolveByPublicCode($code);
        if (! $affiliate) {
            $normalized = strtoupper(trim($code));
            $raw = \App\Models\Vendor::query()
                ->where('category', 'affiliate')
                ->where(function ($query) use ($normalized) {
                    $query->where('affiliate_code', $normalized)
                        ->orWhere('partner_number', $normalized)
                        ->orWhere('metadata->referral_token', $normalized)
                        ->orWhere('metadata->legacy_partner_number', $normalized);
                })
                ->first();

            $message = $raw
                ? __('site.affiliate_portal.link_not_verified')
                : __('site.affiliate_portal.link_not_recognized');

            return redirect()->route('site.register.borrower')
                ->with('warning', $message);
        }

        $affiliates->trackClick($affiliate, $request);
        $token = $affiliates->ensureReferralToken($affiliate);
        app(AffiliateAttributionService::class)->establishClaim($request, $affiliate, 'link', $token);

        if ($user = Auth::user()) {
            $customer = Customer::query()->where('user_id', $user->id)->first();
            if ($customer) {
                $outcome = $affiliates->connectMember($customer, $affiliate, $request, 'link');

                return redirect()
                    ->route('site.borrower.dashboard')
                    ->with($this->flashForOutcome($outcome, $affiliate));
            }
        }

        return redirect()
            ->route('site.register.borrower', ['aff' => $token]);
    }

    /** @return array<string, mixed> */
    private function flashForOutcome(string $outcome, \App\Models\Vendor $affiliate): array
    {
        $name = $affiliate->name;
        $benefits = app(AffiliateService::class)->configuredBenefitItems($affiliate);

        return [
            'affiliate_referral_outcome' => $outcome,
            'affiliate_referral_name' => $name,
            'affiliate_referral_number' => $affiliate->partner_number ?: $affiliate->vendor_number,
            'affiliate_referral_benefits' => $benefits,
            'status' => match ($outcome) {
                'attached' => __('site.affiliate_portal.referral_connected_body', ['name' => $name]),
                'already' => __('site.affiliate_portal.referral_already_body', ['name' => $name]),
                'protected' => __('site.affiliate_portal.referral_protected_body'),
                'not_allowed' => __('site.affiliate_portal.referral_not_allowed_body'),
                default => __('site.affiliate_portal.referral_unavailable_body'),
            },
        ];
    }
}
