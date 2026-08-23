<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\PartnerActivationService;
use App\Support\Celebration;
use App\Support\PhoneNumber;
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
            'phone_local'  => ['nullable', 'string', 'max:20'],
        ]);

        $code = strtoupper(trim($data['partner_code']));
        $phone = PhoneNumber::fromRequest($request, 'phone')
            ?? PhoneNumber::normalizeForCountry($data['phone'], null)
            ?? trim($data['phone']);

        $vendor = Vendor::query()
            ->whereRaw('UPPER(partner_number) = ?', [$code])
            ->where(function ($q) use ($phone) {
                PhoneNumber::constrain($q, 'phone', $phone);
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

        $user = $activation->activateWithPartnerCode($vendor, $phone);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('site.partner.setup-pin')
            ->with('status', __('site.auth.partner_activated'));
    }

    public function showSetupPin(): View|RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'vendor', 403);

        if (app(\App\Services\PinService::class)->hasPin($user)) {
            return redirect()->route('site.partner.dashboard');
        }

        return view('site.partner.setup-pin');
    }

    public function storeSetupPin(Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'vendor', 403);

        $data = $request->validate([
            'pin' => ['required', 'string', new \App\Rules\FourDigitPin, 'confirmed'],
        ]);

        app(\App\Services\PinService::class)->setPin($user, $data['pin']);

        return redirect()
            ->route('site.partner.dashboard')
            ->with('status', 'PIN created. Welcome to your partner portal.')
            ->with(Celebration::SESSION_KEY, ['registration']);
    }
}
