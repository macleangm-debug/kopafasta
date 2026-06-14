<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\PartnerActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PartnerActivationController extends Controller
{
    public function show(Request $request, Vendor $vendor, PartnerActivationService $activation): View|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'This activation link has expired.');
        }

        $token = (string) $request->query('token', '');
        if (! $activation->verifyToken($vendor, $token)) {
            abort(403, 'Invalid activation link.');
        }

        if ($vendor->activated_at && $vendor->user_id) {
            return redirect()->route('site.vendor.dashboard')
                ->with('status', 'Your partner account is already active.');
        }

        return view('site.partner.activate', compact('vendor', 'token'));
    }

    public function store(Request $request, Vendor $vendor, PartnerActivationService $activation): RedirectResponse
    {
        $token = (string) $request->input('token', '');

        $user = $activation->activate($vendor, $token, $request->all());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('site.vendor.dashboard')
            ->with('status', 'Partner account activated. Complete your profile and payment details.');
    }
}
