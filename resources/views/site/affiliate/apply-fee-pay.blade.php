<x-site.layout :title="brand_title(__('site.affiliate_apply.fee_title'))">
    <div class="max-w-xl mx-auto px-4 py-10">
        <x-site.borrower-page-header
            :eyebrow="__('site.affiliate_apply.fee_eyebrow')"
            :title="__('site.affiliate_apply.fee_title')"
            :subtitle="__('site.affiliate_apply.fee_subtitle', ['amount' => format_money($payment->amount)])"
        />

        <div class="mt-6">
            @include('site.borrower.payments._show_body', [
                'payment' => $payment,
                'bankAccounts' => $bankAccounts,
                'canSwitchToBank' => $canSwitchToBank ?? false,
                'bankDetails' => null,
                'mobileDetails' => [],
                'payUrl' => $payUrl,
                'statusUrl' => $statusUrl,
                'retryUrl' => $retryUrl,
                'gateUrl' => $gateUrl,
                'successUrl' => $successUrl,
                'defaultPhone' => $defaultPhone ?? old('mobile_number'),
                'showPromo' => false,
                'simulateUrl' => $simulateUrl ?? null,
            ])
        </div>
    </div>
</x-site.layout>
