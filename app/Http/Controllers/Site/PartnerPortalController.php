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
            'phone'        => ['nullable', 'string', 'max:30'],
            'email'        => ['nullable', 'email', 'max:150'],
        ]);

        if (blank($data['phone']) && blank($data['email'])) {
            return back()->withInput()->withErrors([
                'phone' => 'Enter the phone or email registered for this partner account.',
            ]);
        }

        $vendor = Vendor::query()
            ->where('vendor_number', strtoupper(trim($data['partner_code'])))
            ->when(filled($data['phone'] ?? null), fn ($q) => $q->where('phone', $data['phone']))
            ->when(filled($data['email'] ?? null), fn ($q) => $q->where('email', $data['email']))
            ->first();

        if (! $vendor) {
            return back()->withInput()->withErrors([
                'partner_code' => 'No partner account matches these details.',
            ]);
        }

        if ($vendor->activated_at && $vendor->user_id) {
            return redirect()->route('site.login', ['role' => 'vendor'])
                ->with('status', 'Your partner account is already active. Sign in with your phone and PIN.');
        }

        $activation->sendActivationInvite($vendor);

        return view('site.partner.invite-sent', compact('vendor'));
    }
}
