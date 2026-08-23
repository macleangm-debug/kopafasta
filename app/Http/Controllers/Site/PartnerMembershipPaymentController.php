<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CustomerPayment;
use App\Models\Vendor;
use App\Services\CustomerPaymentService;
use App\Services\PartnerMembershipPaymentService;
use App\Services\PaymentAccountService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PartnerMembershipPaymentController extends Controller
{
    protected function vendor(): Vendor
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'vendor', 403);
        $vendor = Vendor::query()->where('user_id', $user->id)->first();
        abort_unless($vendor, 403);

        return $vendor;
    }

    public function pay(Request $request, CustomerPayment $payment, CustomerPaymentService $payments): RedirectResponse
    {
        $vendor = $this->vendor();
        app(PartnerMembershipPaymentService::class)->authorize($payment, $vendor);
        abort_unless($payment->awaitsCollection() || $payment->status === 'awaiting_payment', 422);

        $data = $request->validate([
            'payment_method' => ['nullable', 'in:mobile_money,bank_transfer'],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'mobile_number_local' => ['nullable', 'string', 'max:20'],
            'operator' => ['nullable', 'string', 'in:mpesa,airtel,tigopesa,halopesa'],
        ]);

        $method = $data['payment_method'] ?? 'mobile_money';
        $showRoute = $vendor->isAffiliate() ? 'site.affiliate.membership.pay' : 'site.partner.membership.pay';

        if ($method === 'bank_transfer') {
            try {
                $payments->switchToBankTransfer($payment);
            } catch (\Throwable $e) {
                return redirect()->route($showRoute)
                    ->with('error', $e->getMessage() ?: __('borrower.payment_waiting.bank_unavailable'));
            }

            return redirect()->route($showRoute)
                ->with('status', __('borrower.payment_waiting.switched_to_bank'));
        }

        $mobileNumber = PhoneNumber::fromRequest($request, 'mobile_number', 'TZ')
            ?: ($data['mobile_number'] ?? null);

        if ($method === 'mobile_money' && ! filled($mobileNumber)) {
            return redirect()->route($showRoute)
                ->withErrors(['mobile_number' => __('borrower.payments.mobile_number_required')]);
        }

        try {
            $payments->initiateCollection($payment, $mobileNumber, $data['operator'] ?? null);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $raw = collect($e->errors())->flatten()->first();
            $fresh = $payment->fresh();
            $attempted = $fresh->mobile_number ?: data_get($fresh->provider_meta, 'attempted_phone');
            $message = CustomerPaymentService::localizeProviderMessage($raw, $attempted);

            return redirect()->route($showRoute)
                ->with('collect_error', $message)
                ->with('show_collect_failed', true)
                ->withErrors(['mobile_number' => $message]);
        }

        return redirect()->route($showRoute);
    }

    public function status(CustomerPayment $payment, CustomerPaymentService $payments): JsonResponse
    {
        $vendor = $this->vendor();
        app(PartnerMembershipPaymentService::class)->authorize($payment, $vendor);

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
            $redirect = app(PartnerMembershipPaymentService::class)->dashboardUrl($vendor);
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

    public function retry(Request $request, CustomerPayment $payment, CustomerPaymentService $payments): RedirectResponse
    {
        $vendor = $this->vendor();
        app(PartnerMembershipPaymentService::class)->authorize($payment, $vendor);
        $showRoute = $vendor->isAffiliate() ? 'site.affiliate.membership.pay' : 'site.partner.membership.pay';

        try {
            $payment = $payments->returnToPaymentGate($payment);
        } catch (\Throwable $e) {
            return redirect()->route($showRoute)
                ->with('error', $e->getMessage() ?: __('borrower.payment_waiting.cannot_retry'));
        }

        $phone = $payment->mobile_number
            ?: data_get($payment->provider_meta, 'attempted_phone')
            ?: data_get($payment->provider_meta, 'phone')
            ?: $vendor->phone;

        if (! filled($phone)) {
            return redirect()->route($showRoute)
                ->with('error', __('borrower.payments.mobile_number_required'));
        }

        try {
            $payments->initiateCollection($payment, $phone, $request->input('operator'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            $raw = collect($e->errors())->flatten()->first();
            $fresh = $payment->fresh();
            $attempted = $fresh->mobile_number ?: data_get($fresh->provider_meta, 'attempted_phone');
            $message = CustomerPaymentService::localizeProviderMessage($raw, $attempted);

            return redirect()->route($showRoute)
                ->with('collect_error', $message)
                ->with('show_collect_failed', true);
        }

        return redirect()->route($showRoute);
    }

    public function returnToGate(CustomerPayment $payment, CustomerPaymentService $payments): RedirectResponse
    {
        $vendor = $this->vendor();
        app(PartnerMembershipPaymentService::class)->authorize($payment, $vendor);
        $showRoute = $vendor->isAffiliate() ? 'site.affiliate.membership.pay' : 'site.partner.membership.pay';

        try {
            $payments->returnToPaymentGate($payment);
        } catch (\Throwable $e) {
            return redirect()->route($showRoute)
                ->with('error', $e->getMessage() ?: __('borrower.payment_waiting.cannot_retry'));
        }

        return redirect()->route($showRoute);
    }
}
