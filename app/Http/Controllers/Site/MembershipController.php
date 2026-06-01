<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Services\AffiliateService;
use App\Services\GuarantorOnboardingService;
use App\Services\MembershipService;
use App\Services\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MembershipController extends Controller
{
    use AuditsActions;

    public function show(Request $request, ReferralService $referrals): View
    {
        $customer = $this->resolveCustomer($request);
        $cfg = MembershipService::config();
        $referralLink = $customer ? $referrals->referralLink($customer) : null;
        $referralCode = $customer ? $referrals->ensureCode($customer) : null;
        $referralWallet = $customer ? $referrals->wallet($customer) : null;
        $referralSettings = $referrals->settings();

        return view('site.borrower.membership', [
            'customer'         => $customer,
            'config'           => $cfg,
            'history'          => $customer?->membershipHistories()->latest()->limit(20)->get() ?? collect(),
            'referralLink'     => $referralLink,
            'referralCode'     => $referralCode,
            'referralWallet'   => $referralWallet,
            'referralSettings' => $referralSettings,
        ]);
    }

    public function renewForm(Request $request, MembershipService $service, ReferralService $referrals): View|RedirectResponse
    {
        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return redirect()->route('site.borrower.dashboard');
        }

        if ($redirect = app(GuarantorOnboardingService::class)->redirectIfPending($request, $customer)) {
            return $redirect;
        }

        if ($customer->isMembershipActive() && ! $customer->isMembershipExpiringSoon(30)) {
            return redirect()->route('site.membership.show')
                ->with('status', 'Your membership is active. Renewal opens within 30 days of expiry.');
        }

        $cfg = MembershipService::config();
        $isFirstTime = ! $customer->hasMembership();
        $paymentReference = $service->generatePaymentReference($customer);
        $request->session()->put('membership_payment_ref', $paymentReference);

        $baseFee = $isFirstTime ? $cfg['registration_fee'] : $cfg['renewal_fee'];
        $useWallet = (bool) old('use_wallet', false);
        $feeQuote = $isFirstTime
            ? $this->membershipFeeQuote($customer, (float) $baseFee, $useWallet, $referrals)
            : null;
        $referralWallet = $referrals->wallet($customer);
        $referralSettings = $referrals->settings();

        $bankAccounts = config('site.membership_bank_accounts', [
            ['bank' => 'CRDB Bank', 'account_name' => 'Kopafasta Microfinance Ltd', 'account_number' => '0150-XXXXX-00', 'branch' => 'Dar es Salaam'],
        ]);

        return view('site.borrower.membership-renew', [
            'customer'         => $customer,
            'config'           => $cfg,
            'isFirstTime'      => $isFirstTime,
            'paymentReference' => $paymentReference,
            'feeAmount'        => $baseFee,
            'feeQuote'         => $feeQuote,
            'referralWallet'   => $referralWallet,
            'referralSettings' => $referralSettings,
            'bankAccounts'     => $bankAccounts,
        ]);
    }

    public function renew(Request $request, MembershipService $service, ReferralService $referrals): RedirectResponse
    {
        $data = $request->validate([
            'channel'       => ['required', 'in:mobile_money,bank'],
            'payment_phone' => ['required_if:channel,mobile_money', 'nullable', 'string', 'max:20'],
            'use_wallet'    => ['nullable', 'boolean'],
        ]);

        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return redirect()->route('site.borrower.dashboard')
                ->with('error', 'Membership payment requires a customer profile.');
        }

        $paymentReference = $request->session()->pull('membership_payment_ref')
            ?? $service->generatePaymentReference($customer);

        $isFirstTime = ! $customer->hasMembership();
        $cfg = MembershipService::config();
        $baseFee = $isFirstTime ? $cfg['registration_fee'] : $cfg['renewal_fee'];
        $useWallet = $isFirstTime && $request->boolean('use_wallet');
        $paymentBreakdown = null;

        if ($isFirstTime) {
            if ($data['channel'] === 'mobile_money') {
                if ($referrals->referrer($customer)) {
                    $paymentBreakdown = $referrals->settleFee(
                        $customer,
                        (float) $baseFee,
                        $useWallet,
                        'registration_fee',
                        \App\Models\MembershipHistory::class,
                        null,
                    );
                } else {
                    $affiliate = app(AffiliateService::class);
                    $quote = $affiliate->quoteFee($customer, (float) $baseFee, 'registration_fee');
                    $walletApplied = 0.0;

                    if ($useWallet && $referrals->canUseWalletFor('registration_fee')) {
                        $walletQuote = $referrals->quoteFee($customer, $quote['after_discount'], true, 'registration_fee', applyDiscount: false);
                        $walletApplied = $walletQuote['wallet_applied'];

                        if ($walletApplied > 0) {
                            $referrals->debit(
                                $customer,
                                $walletApplied,
                                'Applied to registration fee',
                                \App\Models\MembershipHistory::class,
                                null,
                            );
                        }
                    }

                    $affiliate->accrueCommission(
                        $customer,
                        (float) $baseFee,
                        'registration_fee',
                        \App\Models\MembershipHistory::class,
                        null,
                    );

                    $paymentBreakdown = array_merge($quote, [
                        'wallet_applied' => $walletApplied,
                        'cash_due'       => max(0, round($quote['after_discount'] - $walletApplied, 2)),
                    ]);
                }
            } else {
                $paymentBreakdown = $this->membershipFeeQuote($customer, (float) $baseFee, $useWallet, $referrals);
            }
        }

        if ($data['channel'] === 'mobile_money') {
            if ($isFirstTime) {
                $service->issue($customer, null, $paymentReference, $request->user()?->id, null, 'mobile_money', $paymentBreakdown);
                $message = 'Registration fee received. Your membership is now active!';
            } else {
                $service->renew($customer, $paymentReference, 'mobile_money', $request->user()?->id);
                $message = 'Membership renewed successfully!';
            }

            $this->auditBorrower($isFirstTime ? 'membership.issued' : 'membership.renewed', $customer, [
                'channel'   => 'mobile_money',
                'reference' => $paymentReference,
                'referral'  => $paymentBreakdown,
            ]);

            $redirect = redirect()->route('site.membership.show')
                ->with('confetti', true)
                ->with('status', $message);

            if ($next = app(GuarantorOnboardingService::class)->redirectIfPending($request, $customer->fresh())) {
                return $next;
            }

            return $redirect;
        }

        $service->recordPendingPayment($customer, $paymentReference, 'bank', $request->user()?->id, $paymentBreakdown);

        $this->auditBorrower('membership.payment_pending', $customer, [
            'channel'   => 'bank',
            'reference' => $paymentReference,
            'referral'  => $paymentBreakdown,
        ]);

        return redirect()->route('site.membership.show')
            ->with('warning', 'Bank payment submitted. We will activate your membership after verifying your transfer. Reference: '.$paymentReference);
    }

    private function resolveCustomer(Request $request): ?\App\Models\Customer
    {
        $user = $request->user();

        return $user?->customer;
    }

    /** @return array<string, mixed> */
    private function membershipFeeQuote(\App\Models\Customer $customer, float $baseFee, bool $useWallet, ReferralService $referrals): array
    {
        if ($referrals->referrer($customer)) {
            return $referrals->quoteFee($customer, $baseFee, $useWallet, 'registration_fee');
        }

        $affiliateQuote = app(AffiliateService::class)->quoteFee($customer, $baseFee, 'registration_fee');
        $walletQuote = $referrals->quoteFee($customer, $affiliateQuote['after_discount'], $useWallet, 'registration_fee', applyDiscount: false);

        return array_merge($affiliateQuote, [
            'wallet_usable'  => $walletQuote['wallet_usable'],
            'wallet_applied' => $walletQuote['wallet_applied'],
            'cash_due'       => max(0, round($affiliateQuote['after_discount'] - $walletQuote['wallet_applied'], 2)),
            'has_referrer'   => false,
            'referrer'       => null,
        ]);
    }
}
