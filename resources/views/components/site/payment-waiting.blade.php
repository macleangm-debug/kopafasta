@props([
    'payment',
    'statusUrl' => null,
    'successUrl' => null,
])

@php
    $statusUrl = $statusUrl ?? route('site.borrower.payments.status', $payment);
    $successUrl = $successUrl ?? app(\App\Services\CustomerPaymentService::class)->successRedirectUrl($payment);
@endphp

<div
    x-data="{
        state: 'waiting',
        message: @js(__('borrower.payment_waiting.waiting')),
        attempts: 0,
        maxAttempts: 60,
        timer: null,
        statusUrl: @js($statusUrl),
        successUrl: @js($successUrl),
        async poll() {
            this.attempts++;
            try {
                const res = await fetch(this.statusUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (! res.ok || ! data.ok) throw new Error(data.message || 'Status check failed');
                this.state = data.state;
                this.message = data.message || this.message;
                if (data.state === 'paid') {
                    clearInterval(this.timer);
                    window.location.href = data.redirect_url || this.successUrl;
                    return;
                }
                if (data.state === 'failed') {
                    clearInterval(this.timer);
                    return;
                }
            } catch (e) {
                this.message = @js(__('borrower.payment_waiting.retry'));
            }
            if (this.attempts >= this.maxAttempts) {
                clearInterval(this.timer);
                this.state = 'timeout';
                this.message = @js(__('borrower.payment_waiting.timeout'));
            }
        },
        start() {
            this.poll();
            this.timer = setInterval(() => this.poll(), 5000);
        },
    }"
    x-init="start()"
    class="rounded-3xl bg-gradient-to-br from-brand via-brand to-brand/90 text-white shadow-lg shadow-brand/20 overflow-hidden"
>
    <div class="relative px-5 sm:px-8 py-8 sm:py-10 text-center">
        <div class="absolute inset-0 opacity-[0.14]" style="background-image: radial-gradient(circle at 18% 20%, #fff 0, transparent 42%), radial-gradient(circle at 88% 0%, #fbbf24 0, transparent 38%);"></div>
        <div class="relative">
            <div x-show="state === 'waiting'" class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/30">
                <svg class="h-7 w-7 animate-spin text-brand-gold" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </div>
            <div x-show="state === 'failed' || state === 'timeout'" x-cloak class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-rose-500/20 ring-1 ring-rose-300/40">
                <span class="text-2xl font-bold text-rose-100">!</span>
            </div>

            <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-white/70">{{ __('borrower.payment_waiting.eyebrow') }}</p>
            <h2 class="mt-2 text-2xl sm:text-3xl font-extrabold tracking-tight" x-text="state === 'failed' ? @js(__('borrower.payment_waiting.failed_title')) : (state === 'timeout' ? @js(__('borrower.payment_waiting.timeout_title')) : @js(__('borrower.payment_waiting.title')))"></h2>
            <p class="mt-3 text-sm text-white/85 max-w-md mx-auto" x-text="message"></p>

            <div class="mt-6 rounded-2xl bg-white/10 ring-1 ring-white/20 px-4 py-4 text-left max-w-sm mx-auto space-y-2">
                <div class="flex justify-between gap-3 text-sm">
                    <span class="text-white/70">{{ __('borrower.payment_waiting.amount') }}</span>
                    <span class="font-bold tabular-nums text-brand-gold">{{ format_money((float) $payment->amount) }}</span>
                </div>
                @if ($payment->mobile_number)
                    <div class="flex justify-between gap-3 text-sm">
                        <span class="text-white/70">{{ __('borrower.payment_waiting.phone') }}</span>
                        <span class="font-mono font-semibold">{{ $payment->mobile_number }}</span>
                    </div>
                @endif
                <div class="flex justify-between gap-3 text-sm">
                    <span class="text-white/70">{{ __('borrower.payment_waiting.reference') }}</span>
                    <span class="font-mono font-semibold">{{ $payment->reference }}</span>
                </div>
            </div>

            <ol class="mt-6 text-left max-w-sm mx-auto space-y-2 text-sm text-white/90" x-show="state === 'waiting'">
                <li>1. {{ __('borrower.payment_waiting.step_ussd') }}</li>
                <li>2. {{ __('borrower.payment_waiting.step_pin') }}</li>
                <li>3. {{ __('borrower.payment_waiting.step_auto') }}</li>
            </ol>

            <div class="mt-8 flex flex-wrap justify-center gap-3" x-show="state === 'failed' || state === 'timeout'" x-cloak>
                <a href="{{ route('site.borrower.payments') }}" class="rounded-xl bg-white text-brand text-sm font-bold px-5 py-2.5">{{ __('borrower.payment_waiting.back_payments') }}</a>
                <button type="button" @click="state = 'waiting'; attempts = 0; message = @js(__('borrower.payment_waiting.waiting')); start()"
                        class="rounded-xl bg-brand-gold text-brand text-sm font-bold px-5 py-2.5">
                    {{ __('borrower.payment_waiting.check_again') }}
                </button>
            </div>
        </div>
    </div>
</div>
