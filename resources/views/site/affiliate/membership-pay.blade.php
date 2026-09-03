<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.membership_pay'))" active="profile">
    <x-site.borrower-page-header
        :eyebrow="__('site.affiliate_portal.membership_title')"
        :title="__('site.affiliate_portal.membership_pay')"
        :subtitle="__('site.affiliate_portal.membership_subtitle')"
    />

    <div class="max-w-xl mx-auto">
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
            'defaultPhone' => old('mobile_number', $vendor->phone),
            'showPromo' => false,
        ])
    </div>
</x-site.affiliate-layout>
