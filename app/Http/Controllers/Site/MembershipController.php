<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Services\AffiliateService;
use App\Services\GuarantorOnboardingService;
use App\Services\MembershipService;
use App\Services\PaymentAccountService;
use App\Services\ReferralService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MembershipController extends Controller
{
    use AuditsActions;

    public function show(Request $request): RedirectResponse
    {
        return redirect()->route('site.borrower.profile', ['section' => 'membership']);
    }

    public function renewForm(Request $request, MembershipService $service, ReferralService $referrals): View|RedirectResponse
    {
        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return redirect()->route('site.borrower.dashboard');
        }

        if ($redirect = app(\App\Services\PortalOnboardingResumeService::class)->redirectIfPending($request, $customer)) {
            return $redirect;
        }

        return redirect()->route('site.borrower.dashboard')
            ->with('status', __('borrower.membership.compulsory_retired'));

        if ($customer->isMembershipActive() && ! $customer->isMembershipExpiringSoon(30)) {
            return redirect()->route('site.borrower.profile', ['section' => 'membership'])
                ->with('status', 'Your membership is active. Renewal opens within 30 days of expiry.');
        }

        $cfg = MembershipService::config();
        $isFirstTime = ! $customer->hasMembership();
        $paymentReference = $service->generatePaymentReference($customer);
        $request->session()->put('membership_payment_ref', $paymentReference);

        $baseFee = $isFirstTime ? $cfg['registration_fee'] : $cfg['renewal_fee'];
        $useWallet = (bool) old('use_wallet', false);
        $promoCode = old('promo_code', $request->query('promo_code'));
        [$resolvedPromo, $resolvedAffiliate] = app(\App\Services\ApplicationFeePaymentService::class)
            ->resolvePromoOrAffiliate($promoCode);
        $feeQuote = $isFirstTime
            ? app(\App\Services\PaymentGateService::class)->quote(
                $customer,
                (float) $baseFee,
                'registration_fee',
                $useWallet,
                $resolvedPromo,
                $resolvedAffiliate
            )
            : null;
        $referralWallet = $referrals->wallet($customer);
        $referralSettings = $referrals->settings();

        $accounts = app(PaymentAccountService::class);
        $bankAccounts = $accounts->bankAccountsForDisplay('registration_fee', $paymentReference);
        $mobileResolved = $accounts->resolve('registration_fee', 'mobile_money');
        $mobileDetails = $accounts->mobileMoneyDetails($mobileResolved['mobile_money_account'], $paymentReference);

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
            'mobileDetails'    => $mobileDetails,
        ]);
    }

    public function renew(Request $request, MembershipService $service, ReferralService $referrals): RedirectResponse
    {
        $data = $request->validate([
            'channel'       => ['nullable', 'in:mobile_money,bank'],
            'payment_phone' => ['nullable', 'string', 'max:20'],
            'payment_phone_local' => ['nullable', 'string', 'max:20'],
            'use_wallet'    => ['nullable', 'boolean'],
            'promo_code'    => ['nullable', 'string', 'max:40'],
        ]);
        $data['channel'] = $data['channel'] ?? 'mobile_money';

        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return redirect()->route('site.borrower.dashboard')
                ->with('error', 'Membership payment requires a customer profile.');
        }

        $isFirstTime = ! $customer->hasMembership();
        $cfg = MembershipService::config();
        $baseFee = $isFirstTime ? $cfg['registration_fee'] : $cfg['renewal_fee'];
        $useWallet = $isFirstTime && $request->boolean('use_wallet');
        $promoCode = $data['promo_code'] ?? null;
        $gate = app(\App\Services\PaymentGateService::class);
        [$resolvedPromo, $resolvedAffiliate] = app(\App\Services\ApplicationFeePaymentService::class)
            ->resolvePromoOrAffiliate($promoCode);
        $cashDue = $isFirstTime
            ? (int) ($gate->quote($customer, (float) $baseFee, 'registration_fee', $useWallet, $resolvedPromo, $resolvedAffiliate)['cash_due'] ?? $baseFee)
            : (int) $baseFee;

        if ($data['channel'] === 'mobile_money' && ! payment_channels_for_amount($cashDue)['mobile_money_allowed']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'channel' => [__('borrower.payments_page.create.bank_only', [
                    'threshold' => format_money(payment_mobile_money_threshold()),
                ])],
            ]);
        }

        $paymentReference = $request->session()->pull('membership_payment_ref')
            ?? $service->generatePaymentReference($customer);

        $paymentBreakdown = null;

        if ($isFirstTime) {
            $quote = $gate->quote($customer, (float) $baseFee, 'registration_fee', $useWallet, $resolvedPromo, $resolvedAffiliate);
            $paymentBreakdown = $quote;
        }

        $membershipContext = [
            'is_first_time' => $isFirstTime,
            'base_fee' => (float) $baseFee,
            'use_wallet' => $useWallet,
            'promo_code' => $resolvedPromo,
            'affiliate_code' => $resolvedAffiliate,
            'quote' => $paymentBreakdown,
            'settled' => false,
        ];

        if ($data['channel'] === 'mobile_money') {
            $paymentPhone = PhoneNumber::fromRequest($request, 'payment_phone', $customer->country_code ?? null)
                ?: $customer->phone;

            if (! filled($paymentPhone)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payment_phone' => [__('borrower.payments_page.create.mobile_number_hint')],
                ]);
            }

            $awaitsPsp = app(\App\Services\PayInService::class)->isConfigured()
                || app(\App\Services\PayInService::class)->isLiveCollectionEnabled()
                || ! payment_gateway_is_dummy();

            try {
                $payment = app(\App\Services\CustomerPaymentService::class)->create([
                    'customer'       => $customer,
                    'payment_type'   => 'registration_fee',
                    'payment_method' => 'mobile_money',
                    'amount'         => $paymentBreakdown['cash_due'] ?? $paymentBreakdown['after_discount'] ?? $baseFee,
                    'reference'      => $paymentReference,
                    'mobile_number'  => $paymentPhone,
                    'auto_verify'    => ! $awaitsPsp,
                    'membership_context' => $membershipContext,
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return back()->withInput()->withErrors($e->errors())->with('feedback', [
                    'tone' => 'error',
                    'title' => __('borrower.membership.payment_error_title'),
                    'message' => collect($e->errors())->flatten()->first() ?: 'Payment could not be started.',
                ]);
            } catch (\Throwable $e) {
                report($e);

                return back()->withInput()->withErrors([
                    'payment_phone' => [__('borrower.membership.payment_error_title')],
                ])->with('feedback', [
                    'tone' => 'error',
                    'title' => __('borrower.membership.payment_error_title'),
                    'message' => $e->getMessage() ?: 'Payment could not be started.',
                ]);
            }

            // Shared payments.show gate — PSP confirmation unlocks membership.
            if (in_array($payment->status, ['processing', 'awaiting_payment', 'pending_verification'], true)) {
                return redirect()
                    ->route('site.borrower.payments.show', $payment);
            }

            // Instant dummy path — stay on dashboard and surface the member card there.
            $this->auditBorrower($isFirstTime ? 'membership.issued' : 'membership.renewed', $customer, [
                'channel'   => 'mobile_money',
                'reference' => $paymentReference,
                'referral'  => $paymentBreakdown,
            ]);

            $redirect = redirect()->route('site.borrower.dashboard')
                ->with('status', $isFirstTime
                    ? __('borrower.membership.activated_start_loan')
                    : 'Membership renewed successfully!')
                ->with('show_membership_card', true)
                ->with(\App\Support\Celebration::SESSION_KEY, [$isFirstTime ? 'membership' : 'payment']);

            if ($next = app(\App\Services\PortalOnboardingResumeService::class)->redirectIfPending($request, $customer->fresh())) {
                return $next
                    ->with('show_membership_card', true)
                    ->with(\App\Support\Celebration::SESSION_KEY, [$isFirstTime ? 'membership' : 'payment']);
            }

            return $redirect;
        }

        // Bank: create pending CustomerPayment + history, then open shared payment gate.
        $service->recordPendingPayment($customer, $paymentReference, 'bank', $request->user()?->id, $paymentBreakdown);

        $payment = \App\Models\CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->where('reference', $paymentReference)
            ->where('payment_type', 'registration_fee')
            ->latest('id')
            ->first();

        if ($payment) {
            $meta = $payment->provider_meta ?? [];
            $meta['membership_context'] = $membershipContext;
            $payment->update(['provider_meta' => $meta]);
        }

        $this->auditBorrower('membership.payment_pending', $customer, [
            'channel'   => 'bank',
            'reference' => $paymentReference,
            'referral'  => $paymentBreakdown,
        ]);

        if ($payment) {
            return redirect()
                ->route('site.borrower.payments.show', $payment)
                ->with('warning', 'Bank payment submitted. We will activate your membership after verifying your transfer. Reference: '.$paymentReference);
        }

        return redirect()->route('site.borrower.dashboard')
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
