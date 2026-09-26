<x-site.borrower-layout :title="brand_title(__('plus.welcome.title'))" active="plus">
    <section class="kf-premium-panel rounded-2xl p-6 sm:p-8 space-y-4 text-center">
        <p class="relative text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">{{ __('plus.welcome.kicker') }} ✦</p>
        <h1 class="relative text-2xl sm:text-3xl font-extrabold tracking-tight">{{ __('plus.welcome.title') }}</h1>
        <p class="relative text-sm text-white/85 max-w-md mx-auto">{{ __('plus.welcome.body') }}</p>
        <div class="relative flex flex-wrap items-center justify-center gap-2">
            <a href="{{ route('site.borrower.plus.home') }}" class="inline-flex rounded-xl bg-brand-gold hover:brightness-95 text-brand px-6 py-3 font-bold shadow-sm ring-1 ring-brand-gold/40">{{ __('plus.welcome.open') }}</a>
            @if ($plusReceipt ?? null)
                <a href="{{ route('site.borrower.payments.show', $plusReceipt) }}" class="inline-flex rounded-xl bg-white/15 text-white ring-1 ring-white/30 hover:bg-white/25 px-6 py-3 font-bold">{{ __('plus.welcome.view_receipt') }}</a>
            @endif
        </div>
    </section>

    @if ($plusReceipt ?? null)
        <section class="mt-5 kf-receipt rounded-2xl bg-white ring-1 ring-gray-200 p-5 space-y-3">
            <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-gray-500">{{ __('plus.welcome.receipt') }}</p>
            <p class="text-2xl font-extrabold tabular-nums text-gray-900">{{ format_money((float) $plusReceipt->amount) }}</p>
            <p class="font-mono text-sm text-gray-600">{{ $plusReceipt->reference }}</p>
            <p class="text-sm text-gray-600">{{ $plusReceipt->statusLabel() }}@if ($plusReceipt->paid_at || $plusReceipt->created_at) · {{ ($plusReceipt->paid_at ?? $plusReceipt->created_at)->format('d M Y') }}@endif</p>
        </section>
    @endif
</x-site.borrower-layout>
