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
            return view('site.partner.activate', [
                'vendor' => $vendor,
                'token' => $token,
                'pinReset' => true,
            ]);
        }

        return view('site.partner.activate', compact('vendor', 'token'));
    }

    public function store(Request $request, Vendor $vendor, PartnerActivationService $activation): RedirectResponse
    {
        $token = (string) $request->input('token', '');
        if (! $request->boolean('pin_reset')) {
            $request->validate([
                'collection_conduct_accepted' => ['accepted'],
            ]);
        }

        $user = $activation->activate($vendor, $token, $request->all());

        Auth::login($user);
        $request->session()->regenerate();

        if ($vendor->fresh()?->activated_at && $request->boolean('pin_reset')) {
            return redirect()->route('site.partner.setup-pin')
                ->with('status', 'Create a new 4-digit PIN for your partner portal.');
        }

        return redirect()->route('site.partner.setup-pin')
            ->with('status', 'Partner account activated. Create your PIN to continue.');
    }
}
