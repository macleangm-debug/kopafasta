<x-site.vendor-layout :title="brand_title(__('site.partner_portal.membership_pay'))" active="dashboard">
    <x-site.borrower-page-header
        :eyebrow="__('site.partner_portal.membership_pay')"
        :title="__('site.partner_portal.membership_pay')"
        :subtitle="__('site.partner_portal.membership_pay_subtitle')"
    />

    <div class="max-w-xl mx-auto">
        @include('site.borrower.payments._show_body', [
            'payment' => $payment,
            'bankAccounts' => $bankAccounts,
            'canSwitchToBank' => $canSwitchToBank,
            'bankDetails' => null,
            'mobileDetails' => [],
            'payUrl' => $payUrl,
            'statusUrl' => $statusUrl,
            'retryUrl' => $retryUrl,
            'gateUrl' => $gateUrl,
            'successUrl' => $successUrl,
            'defaultPhone' => old('mobile_number', $vendor->phone),
            'showPromo' => false,
        ])
    </div>
</x-site.vendor-layout>
