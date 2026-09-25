<x-site.layout :title="brand_title(__('site.affiliate_apply.fee_title'))">
    <div class="max-w-xl mx-auto px-4 py-10">
        <section class="relative overflow-hidden rounded-2xl kf-premium-panel mb-6">
            <div class="absolute -right-16 -top-16 h-44 w-44 rounded-full bg-brand-gold/10 pointer-events-none" aria-hidden="true"></div>
            <div class="relative px-5 sm:px-6 py-5 sm:py-6">
                <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.affiliate_apply.fee_eyebrow') }}</p>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white mt-1">{{ __('site.affiliate_apply.fee_title') }}</h1>
                <p class="mt-2 text-sm text-white/80 leading-relaxed">{{ __('site.affiliate_apply.fee_subtitle', ['amount' => format_money($payment->amount)]) }}</p>
            </div>
        </section>

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
