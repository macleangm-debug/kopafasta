<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MembershipController extends Controller
{
    public function show(Request $request): View
    {
        $customer = $this->resolveCustomer($request);
        $cfg = MembershipService::config();

        return view('site.borrower.membership', [
            'customer' => $customer,
            'config'   => $cfg,
            'history'  => $customer?->membershipHistories()->latest()->limit(20)->get() ?? collect(),
        ]);
    }

    public function renewForm(Request $request): View|RedirectResponse
    {
        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return redirect()->route('site.borrower.dashboard');
        }

        return view('site.borrower.membership-renew', [
            'customer' => $customer,
            'config'   => MembershipService::config(),
        ]);
    }

    public function renew(Request $request, MembershipService $service): RedirectResponse
    {
        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'max:80'],
            'channel'           => ['required', 'in:mobile_money,bank,cash,wallet'],
        ]);

        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return redirect()->route('site.borrower.dashboard')
                ->with('error', 'Membership renewal requires a customer profile.');
        }

        // NOTE: Actual payment verification (M-Pesa/Airtel/etc) happens via the
        // payment gateway webhook; this endpoint trusts that a verified reference
        // has already been posted. For self-service mobile money flow, the gateway
        // callback should call $service->renew(...) directly instead.
        $service->renew($customer, $data['payment_reference'], $data['channel'], $request->user()?->id);

        return redirect()->route('site.membership.show')
            ->with('confetti', true)
            ->with('status', 'Membership renewed successfully!');
    }

    private function resolveCustomer(Request $request): ?\App\Models\Customer
    {
        $user = $request->user();
        return $user?->customer;
    }
}
