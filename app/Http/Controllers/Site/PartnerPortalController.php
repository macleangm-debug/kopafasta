<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\PartnerActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'pin'          => ['required', 'digits:4'],
        ]);

        $code = strtoupper(trim($data['partner_code']));
        $normalizedPhone = preg_replace('/\D+/', '', $data['phone']) ?: $data['phone'];

        $vendor = Vendor::query()
            ->where('partner_number', $code)
            ->where(function ($q) use ($data, $normalizedPhone) {
                $q->where('phone', $data['phone'])
                    ->orWhere('phone', 'like', '%'.$normalizedPhone)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ['%'.$normalizedPhone]);
            })
            ->first();

        if (! $vendor) {
            return back()->withInput()->withErrors([
                'partner_code' => __('site.auth.partner_lookup_failed'),
            ]);
        }

        if ($vendor->activated_at && $vendor->user_id) {
            return redirect()->route('site.login', ['portal' => 'partner'])
                ->with('status', __('site.auth.partner_already_active'));
        }

        $user = $activation->activateWithPartnerCode($vendor, $data['phone'], $data['pin']);
        Auth::login($user);

        return redirect()
            ->route('site.partner.dashboard')
            ->with('status', __('site.auth.partner_activated'));
    }
}
