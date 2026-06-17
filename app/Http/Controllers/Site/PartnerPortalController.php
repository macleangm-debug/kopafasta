<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\PartnerActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerPortalController extends Controller
{
    public function start(): View
    {
        return view('site.partner.start');
    }

    public function lookup(Request $request, PartnerActivationService $activation): RedirectResponse|View
    {
        $data = $request->validate([
            'partner_code' => ['required', 'string', 'max:50'],
            'phone'        => ['required', 'string', 'max:30'],
        ]);

        $vendor = Vendor::query()
            ->where('vendor_number', strtoupper(trim($data['partner_code'])))
            ->where('phone', $data['phone'])
            ->first();

        if (! $vendor) {
            return back()->withInput()->withErrors([
                'partner_code' => 'No partner account matches these details.',
            ]);
        }

        if ($vendor->activated_at && $vendor->user_id) {
            return redirect()->route('site.login', ['portal' => 'partner'])
                ->with('status', 'Your partner account is already active. Sign in with your phone and PIN.');
        }

        $activation->sendActivationInvite($vendor);

        return view('site.partner.invite-sent', compact('vendor'));
    }
}
