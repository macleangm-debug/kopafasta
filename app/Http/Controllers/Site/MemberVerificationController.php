<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\MemberNumberFormatter;
use Illuminate\View\View;

class MemberVerificationController extends Controller
{
    public function show(string $memberNo): View
    {
        $normalized = MemberNumberFormatter::lookupKey($memberNo);
        $customer = $normalized
            ? Customer::query()->where('member_no', $normalized)->first()
            : null;

        return view('site.public.member-verify', [
            'customer' => $customer,
            'memberNo' => MemberNumberFormatter::display($customer?->member_no ?? $memberNo),
            'verified' => $customer && $customer->hasMembership() && ! $customer->isMembershipExpired(),
        ]);
    }
}
