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
                    ->orWhere('vendor_number', strtoupper(trim($code)));
            })
            ->first();

        $verified = $affiliate
            && $affiliate->status === 'active'
            && in_array($affiliate->affiliate_kyc_status, ['verified', 'approved'], true);

        return view('site.public.affiliate-verify', [
            'affiliate' => $affiliate,
            'code'      => strtoupper(trim($code)),
            'verified'  => $verified,
        ]);
    }
}
