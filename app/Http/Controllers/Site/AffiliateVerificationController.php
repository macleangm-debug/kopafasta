<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CustomerAsset;
use App\Models\Vendor;
use Illuminate\View\View;

class AffiliateVerificationController extends Controller
{
    public function show(string $code): View
    {
        $affiliate = Vendor::query()
            ->where('category', 'affiliate')
            ->where(function ($q) use ($code): void {
                $q->where('affiliate_code', strtoupper(trim($code)))
                    ->orWhere('partner_number', strtoupper(trim($code)));
            })
            ->first();

        $requireKyc = app(\App\Services\AffiliateSettingsService::class)->requireKycForVerification();

        $verified = $affiliate
            && $affiliate->status === 'active'
            && (! $requireKyc || in_array($affiliate->affiliate_kyc_status, ['verified', 'approved'], true));

        $notice = $affiliate
            ? app(\App\Services\AffiliateService::class)->renderMessage($affiliate, 'verification_notice')
            : null;

        return view('site.public.affiliate-verify', [
            'affiliate'  => $affiliate,
            'code'       => strtoupper(trim($code)),
            'verified'   => $verified,
            'notice'     => $notice,
            'verify_url' => $affiliate
                ? route('site.affiliate.verify', $affiliate->affiliate_code ?? $code)
                : null,
        ]);
    }
}
