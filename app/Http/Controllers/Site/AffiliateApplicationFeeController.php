<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CustomerPayment;
use App\Services\AffiliateApplicationFeePaymentService;
use App\Services\CustomerPaymentService;
use App\Services\PaymentAccountService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliateApplicationFeeController extends Controller
{
    public function show(string $token, AffiliateApplicationFeePaymentService $fees, PaymentAccountService $accounts): View|RedirectResponse
    {
        $payment = $fees->findByToken($token);
        abort_unless($payment, 404);
        $fees->authorize($payment, $token);

        if ($payment->isVerified() || in_array($payment->status, ['paid', 'verified'], true)) {
            return redirect()
                ->route('site.partners.apply.tracking', [
                    'phone' => data_get($payment->source?->phone) ?? data_get($payment->provider_meta, 'applicant_phone'),
                ])
                ->with('partner_submitted', true)
                ->with('status', __('site.affiliate_apply.fee_paid'));
        }

        $bankAccounts = $accounts->bankAccountsForDisplay('affiliate_application_fee', $payment->reference);
        $canSwitchToBank = (bool) $accounts->resolveBankAccount('affiliate_application_fee');
        $application = $payment->source;

        return view('site.affiliate.apply-fee-pay', [
            'payment' => $payment,
            'application' => $application,
            'bankAccounts' => $bankAccounts,
            'canSwitchToBank' => $canSwitchToBank,
            'payUrl' => route('site.affiliate.apply.pay.post', ['token' => $token]),
            'statusUrl' => route('site.affiliate.apply.pay.status', ['token' => $token]),
            'retryUrl' => route('site.affiliate.apply.pay.retry', ['token' => $token]),
            'gateUrl' => route('site.affiliate.apply.pay.gate', ['token' => $token]),
            'successUrl' => route('site.partners.apply.tracking', [
                'phone' => $application?->phone,
            ]),
            'defaultPhone' => old('mobile_number', $application?->phone),
        ]);
    }

    public function pay(Request $request, string $token, AffiliateApplicationFeePaymentService $fees, CustomerPaymentService $payments): RedirectResponse
    {
        $payment = $fees->findByToken($token);
        abort_unless($payment, 404);
        $fees->authorize($payment, $token);
        abort_unless($payment->awaitsCollection() || $payment->status === 'awaiting_payment', 422);

        $data = $request->validate([
            'payment_method' => ['nullable', 'in:mobile_money,bank_transfer'],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'mobile_number_local' => ['nullable', 'string', 'max:20'],
            'operator' => ['nullable', 'string', 'in:mpesa,airtel,tigopesa,halopesa'],
        ]);

        $method = $data['payment_method'] ?? 'mobile_money';
        $showRoute = route('site.affiliate.apply.pay', ['token' => $token]);

        if ($method === 'bank_transfer') {
            try {
                $payments->switchToBankTransfer($payment);
            } catch (\Throwable $e) {
                return redirect($showRoute)
                    ->with('error', $e->getMessage() ?: __('borrower.payment_waiting.bank_unavailable'));
            }

            return redirect($showRoute)
                ->with('status', __('borrower.payment_waiting.switched_to_bank'));
        }

        $mobileNumber = PhoneNumber::fromRequest($request, 'mobile_number', 'TZ')
            ?: ($data['mobile_number'] ?? null);

        if ($method === 'mobile_money' && ! filled($mobileNumber)) {
            return redirect($showRoute)
                ->withErrors(['mobile_number' => __('borrower.payments.mobile_number_required')]);
        }

        try {
            $payments->initiateCollection($payment, $mobileNumber, $data['operator'] ?? null);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $raw = collect($e->errors())->flatten()->first();
            $fresh = $payment->fresh();
            $attempted = $fresh->mobile_number ?: data_get($fresh->provider_meta, 'attempted_phone');
            $message = CustomerPaymentService::localizeProviderMessage($raw, $attempted);

            return redirect($showRoute)
                ->with('collect_error', $message)
                ->with('show_collect_failed', true)
                ->withErrors(['mobile_number' => $message]);
        }

        return redirect($showRoute);
    }

    public function status(string $token, AffiliateApplicationFeePaymentService $fees, CustomerPaymentService $payments): JsonResponse
    {
        $payment = $fees->findByToken($token);
        abort_unless($payment, 404);
        $fees->authorize($payment, $token);

        if ($payment->status === 'processing' && $payment->provider === 'payin') {
            $payment = $payments->refreshFromProvider($payment);
        } else {
            $payment = $payment->fresh();
        }

        $state = match (true) {
            $payment->isVerified() || in_array($payment->status, ['paid', 'verified'], true) => 'paid',
            $payment->status === 'rejected' => 'failed',
            $payment->status === 'processing' => 'waiting',
            $payment->awaitsCollection() => 'ready',
            default => 'pending',
        };

        $redirect = null;
        $celebration = null;
        if ($state === 'paid') {
            $phone = $payment->source?->phone;
            $redirect = route('site.partners.apply.tracking', ['phone' => $phone]);
            $celebration = $payments->celebrationCopy($payment);
        }

        return response()->json([
            'ok' => true,
            'state' => $state,
            'status' => $payment->status,
            'reference' => $payment->reference,
            'title' => $celebration['title'] ?? null,
            'message' => $celebration['message'] ?? null,
            'redirect' => $redirect,
        ]);
    }

    public function retry(Request $request, string $token, AffiliateApplicationFeePaymentService $fees, CustomerPaymentService $payments): RedirectResponse
    {
        $payment = $fees->findByToken($token);
        abort_unless($payment, 404);
        $fees->authorize($payment, $token);
        $showRoute = route('site.affiliate.apply.pay', ['token' => $token]);

        try {
            $payment = $payments->returnToPaymentGate($payment);
        } catch (\Throwable $e) {
            return redirect($showRoute)
                ->with('error', $e->getMessage() ?: __('borrower.payment_waiting.cannot_retry'));
        }

        $phone = $payment->mobile_number
            ?: data_get($payment->provider_meta, 'attempted_phone')
            ?: $payment->source?->phone;

        if (! filled($phone)) {
            return redirect($showRoute)
                ->with('error', __('borrower.payments.mobile_number_required'));
        }

        try {
            $payments->initiateCollection($payment, $phone, $request->input('operator'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            $raw = collect($e->errors())->flatten()->first();
            $fresh = $payment->fresh();
            $attempted = $fresh->mobile_number ?: data_get($fresh->provider_meta, 'attempted_phone');
            $message = CustomerPaymentService::localizeProviderMessage($raw, $attempted);

            return redirect($showRoute)
                ->with('collect_error', $message)
                ->with('show_collect_failed', true);
        }

        return redirect($showRoute);
    }

    public function returnToGate(string $token, AffiliateApplicationFeePaymentService $fees, CustomerPaymentService $payments): RedirectResponse
    {
        $payment = $fees->findByToken($token);
        abort_unless($payment, 404);
        $fees->authorize($payment, $token);
        $showRoute = route('site.affiliate.apply.pay', ['token' => $token]);

        try {
            $payments->returnToPaymentGate($payment);
        } catch (\Throwable $e) {
            return redirect($showRoute)
                ->with('error', $e->getMessage() ?: __('borrower.payment_waiting.cannot_retry'));
        }

        return redirect($showRoute);
    }
}
