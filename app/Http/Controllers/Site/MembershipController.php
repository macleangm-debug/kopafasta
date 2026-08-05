<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Services\AffiliateService;
use App\Services\GuarantorOnboardingService;
use App\Services\MembershipService;
use App\Services\PaymentAccountService;
use App\Services\ReferralService;
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
        $feeQuote = $isFirstTime
            ? app(\App\Services\PaymentGateService::class)->quote($customer, (float) $baseFee, 'registration_fee', $useWallet, $promoCode)
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
            'channel'       => ['required', 'in:mobile_money,bank'],
            'payment_phone' => ['required_if:channel,mobile_money', 'nullable', 'string', 'max:20'],
            'use_wallet'    => ['nullable', 'boolean'],
            'promo_code'    => ['nullable', 'string', 'max:40'],
        ]);

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
        $cashDue = $isFirstTime
            ? (int) ($gate->quote($customer, (float) $baseFee, 'registration_fee', $useWallet, $promoCode)['cash_due'] ?? $baseFee)
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
            $quote = $gate->quote($customer, (float) $baseFee, 'registration_fee', $useWallet, $promoCode);
            $paymentBreakdown = $quote;
        }

        if ($data['channel'] === 'mobile_money') {
            try {
                $payment = app(\App\Services\CustomerPaymentService::class)->create([
                    'customer'       => $customer,
                    'payment_type'   => 'registration_fee',
                    'payment_method' => 'mobile_money',
                    'amount'         => $paymentBreakdown['cash_due'] ?? $paymentBreakdown['after_discount'] ?? $baseFee,
                    'reference'      => $paymentReference,
                    'mobile_number'  => $data['payment_phone'] ?? null,
                    'auto_verify'    => payment_gateway_is_dummy(),
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

            // Only settle wallet / commissions once payment is confirmed (dummy/instant path).
            // Live PayIn stays in processing until webhook/poll verifies — settle then in finalize.
            if ($isFirstTime && $payment->status !== 'processing' && is_array($paymentBreakdown)) {
                if ($referrals->referrer($customer)) {
                    $referrals->settleFee(
                        $customer,
                        (float) $baseFee,
                        $useWallet,
                        'registration_fee',
                        \App\Models\MembershipHistory::class,
                        null,
                    );
                } else {
                    app(AffiliateService::class)->accrueCommission(
                        $customer,
                        (float) $baseFee,
                        'registration_fee',
                        \App\Models\MembershipHistory::class,
                        null,
                    );
                    if ($useWallet && ($paymentBreakdown['wallet_applied'] ?? 0) > 0) {
                        $referrals->debit(
                            $customer,
                            $paymentBreakdown['wallet_applied'],
                            'Applied to membership fee',
                            \App\Models\MembershipHistory::class,
                            null,
                        );
                    }
                }
            }

            if ($payment->status === 'processing') {
                return redirect()
                    ->route('site.borrower.payments.show', $payment)
                    ->with('status', __('borrower.payment_waiting.prompt'));
            }

            // Dummy / already-verified payments activate membership in CustomerPaymentService::finalizePayment.
            if ($isFirstTime) {
                $message = 'Membership fee received. Your membership is now active!';
            } else {
                $message = 'Membership renewed successfully!';
            }

            $this->auditBorrower($isFirstTime ? 'membership.issued' : 'membership.renewed', $customer, [
                'channel'   => 'mobile_money',
                'reference' => $paymentReference,
                'referral'  => $paymentBreakdown,
            ]);

            $redirect = redirect()->route('site.borrower.dashboard')
                ->with('status', $isFirstTime
                    ? __('borrower.membership.activated_start_loan')
                    : $message)
                ->with(\App\Support\Celebration::SESSION_KEY, [$isFirstTime ? 'membership' : 'payment']);

            if ($next = app(\App\Services\PortalOnboardingResumeService::class)->redirectIfPending($request, $customer->fresh())) {
                return $next->with(\App\Support\Celebration::SESSION_KEY, [$isFirstTime ? 'membership' : 'payment']);
            }

            return $redirect;
        }

        $service->recordPendingPayment($customer, $paymentReference, 'bank', $request->user()?->id, $paymentBreakdown);

        $this->auditBorrower('membership.payment_pending', $customer, [
            'channel'   => 'bank',
            'reference' => $paymentReference,
            'referral'  => $paymentBreakdown,
        ]);

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

    public function downloadCard(Request $request, ReferralService $referrals): \Symfony\Component\HttpFoundation\Response
    {
        $customer = $this->resolveCustomer($request);
        abort_unless($customer && $customer->hasMembership(), 404);

        $memberNo = \App\Support\MemberNumberFormatter::raw($customer->member_no);
        $verifyUrl = $memberNo
            ? rtrim($referrals->appBaseUrl(), '/').'/verify/member/'.urlencode($memberNo)
            : null;

        $facePhoto = app(\App\Services\FaceVerificationService::class)->latestByAngle($customer)->get('front');
        $photoPath = null;
        if ($facePhoto?->file_path) {
            $absolute = public_path('storage/'.$facePhoto->file_path);
            if (is_file($absolute)) {
                $photoPath = $absolute;
            }
        }

        $logoPath = public_path('images/brand/kopafasta-logo.png');
        if (! is_file($logoPath)) {
            $logoPath = null;
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.membership-card', compact('customer', 'verifyUrl', 'photoPath', 'logoPath'))
            ->setPaper([0, 0, 297.64, 419.53], 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('dpi', 150);

        $filename = 'membership-'.($memberNo ?: $customer->id).'.pdf';

        return $pdf->download($filename);
    }
}
