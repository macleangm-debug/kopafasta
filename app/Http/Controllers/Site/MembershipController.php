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

    public function renewForm(Request $request, MembershipService $service): View|RedirectResponse
    {
        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return redirect()->route('site.borrower.dashboard');
        }

        if ($customer->isMembershipActive() && ! $customer->isMembershipExpiringSoon(30)) {
            return redirect()->route('site.membership.show')
                ->with('status', 'Your membership is active. Renewal opens within 30 days of expiry.');
        }

        $cfg = MembershipService::config();
        $isFirstTime = ! $customer->hasMembership();
        $paymentReference = $service->generatePaymentReference($customer);
        $request->session()->put('membership_payment_ref', $paymentReference);

        $bankAccounts = config('site.membership_bank_accounts', [
            ['bank' => 'CRDB Bank', 'account_name' => 'Kopafasta Microfinance Ltd', 'account_number' => '0150-XXXXX-00', 'branch' => 'Dar es Salaam'],
        ]);

        return view('site.borrower.membership-renew', [
            'customer'         => $customer,
            'config'           => $cfg,
            'isFirstTime'      => $isFirstTime,
            'paymentReference' => $paymentReference,
            'feeAmount'        => $isFirstTime ? $cfg['registration_fee'] : $cfg['renewal_fee'],
            'bankAccounts'     => $bankAccounts,
        ]);
    }

    public function renew(Request $request, MembershipService $service): RedirectResponse
    {
        $data = $request->validate([
            'channel'      => ['required', 'in:mobile_money,bank'],
            'payment_phone'=> ['required_if:channel,mobile_money', 'nullable', 'string', 'max:20'],
        ]);

        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return redirect()->route('site.borrower.dashboard')
                ->with('error', 'Membership payment requires a customer profile.');
        }

        $paymentReference = $request->session()->pull('membership_payment_ref')
            ?? $service->generatePaymentReference($customer);

        $isFirstTime = ! $customer->hasMembership();

        if ($data['channel'] === 'mobile_money') {
            if ($isFirstTime) {
                $service->issue($customer, null, $paymentReference, $request->user()?->id);
                $message = 'Registration fee received. Your membership is now active!';
            } else {
                $service->renew($customer, $paymentReference, 'mobile_money', $request->user()?->id);
                $message = 'Membership renewed successfully!';
            }

            return redirect()->route('site.membership.show')
                ->with('confetti', true)
                ->with('status', $message);
        }

        $service->recordPendingPayment($customer, $paymentReference, 'bank', $request->user()?->id);

        return redirect()->route('site.membership.show')
            ->with('warning', 'Bank payment submitted. We will activate your membership after verifying your transfer. Reference: '.$paymentReference);
    }

    private function resolveCustomer(Request $request): ?\App\Models\Customer
    {
        $user = $request->user();

        return $user?->customer;
    }
}
