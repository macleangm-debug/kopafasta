@props([
    'payment',
    'statusUrl' => null,
    'successUrl' => null,
    'retryUrl' => null,
    'gateUrl' => null,
    'initialState' => 'waiting',
    'errorMessage' => null,
    'canSwitchToBank' => false,
])

@php
    $statusUrl = $statusUrl ?? route('site.borrower.payments.status', $payment);
    $successUrl = $successUrl ?? app(\App\Services\CustomerPaymentService::class)->successRedirectUrl($payment);
    $retryUrl = $retryUrl ?? route('site.borrower.payments.pay', $payment);
    $gateUrl = $gateUrl ?? route('site.borrower.payments.show', ['payment' => $payment, 'edit_phone' => 1]);
    $switchBankUrl = route('site.borrower.payments.switch-bank', $payment);
    $celebration = app(\App\Services\CustomerPaymentService::class)->celebrationCopy($payment);
    $waitingMessage = $payment->mobile_number
        ? __('borrower.payment_waiting.waiting_phone', ['phone' => $payment->mobile_number])
        : __('borrower.payment_waiting.waiting');
    $failedMessage = \App\Services\CustomerPaymentService::localizeProviderMessage(
        $errorMessage ?: __('borrower.payment_waiting.failed')
    );
    $paidTitle = $celebration['title'];
    $paidMessage = $celebration['message'];
    $phone = $payment->mobile_number;
    $startFailed = $initialState === 'failed';
@endphp

<div
    x-data="{
        state: @js($startFailed ? 'failed' : 'waiting'),
        panel: @js($startFailed ? 'help' : 'waiting'),
        message: @js($startFailed ? $failedMessage : $waitingMessage),
        paidTitle: @js($paidTitle),
        attempts: 0,
        maxAttempts: 36,
        noPromptAfterMs: 75000,
        startedAt: Date.now(),
        elapsedSec: 0,
        showNoPrompt: @js($startFailed),
        celebrated: false,
        timer: null,
        tickTimer: null,
        noPromptTimer: null,
        statusUrl: @js($statusUrl),
        successUrl: @js($successUrl),
        elapsedLabel() {
            const m = Math.floor(this.elapsedSec / 60);
            const s = String(this.elapsedSec % 60).padStart(2, '0');
            return m + ':' + s;
        },
        async poll() {
            if (this.state === 'failed' && ! this.timer) return;
            this.attempts++;
            try {
                const res = await fetch(this.statusUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (! res.ok || ! data.ok) throw new Error(data.message || 'Status check failed');
                this.state = data.state === 'ready' ? 'failed' : data.state;
                if (data.state === 'waiting') {
                    this.message = @js($waitingMessage);
                } else if (data.state === 'paid') {
                    if (data.title) this.paidTitle = data.title;
                    this.message = data.message || @js($paidMessage);
                    this.panel = 'waiting';
                } else if (data.state === 'failed' || data.state === 'ready') {
                    this.message = data.message || @js($failedMessage);
                } else {
                    this.message = data.message || this.message;
                }
                if (data.state === 'paid') {
                    this.stopTimers();
                    this.burstConfetti();
                    return;
                }
                if (data.state === 'failed') {
                    this.stopTimers();
                    this.panel = 'help';
                    return;
                }
            } catch (e) {
                this.message = @js(__('borrower.payment_waiting.retry'));
            }
            if (this.attempts >= this.maxAttempts) {
                this.stopTimers();
                this.state = 'timeout';
                this.message = @js(__('borrower.payment_waiting.timeout'));
                this.showNoPrompt = true;
                this.panel = 'help';
            }
        },
        stopTimers() {
            if (this.timer) clearInterval(this.timer);
            if (this.tickTimer) clearInterval(this.tickTimer);
            if (this.noPromptTimer) clearTimeout(this.noPromptTimer);
            this.timer = null;
            this.tickTimer = null;
            this.noPromptTimer = null;
        },
        burstConfetti() {
            if (this.celebrated) return;
            this.celebrated = true;
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            const confettiLayer = document.createElement('div');
            confettiLayer.setAttribute('aria-hidden', 'true');
            confettiLayer.style.cssText = 'position:fixed;inset:0;z-index:10120;pointer-events:none;overflow:visible;';
            document.body.appendChild(confettiLayer);
            const colors = ['#f5c842', '#10b981', '#004d40', '#0d9488', '#fbbf24', '#34d399', '#ffffff'];
            const originX = window.innerWidth / 2;
            const originY = Math.min(220, window.innerHeight * 0.28);
            for (let i = 0; i < 160; i++) {
                const piece = document.createElement('div');
                const angle = Math.random() * Math.PI * 2;
                const velocity = 8 + Math.random() * 18;
                const driftX = Math.cos(angle) * velocity * (14 + Math.random() * 18);
                const driftY = Math.sin(angle) * velocity * (6 + Math.random() * 10) - (40 + Math.random() * 80);
                const delay = Math.random() * 280;
                const duration = 2600 + Math.random() * 1400;
                const size = 5 + Math.random() * 7;
                const isRound = Math.random() > 0.55;
                piece.style.cssText = [
                    'position:absolute',
                    'top:' + originY + 'px',
                    'left:' + originX + 'px',
                    'width:' + size + 'px',
                    'height:' + (isRound ? size : (size * (1.2 + Math.random()))) + 'px',
                    'background:' + colors[i % colors.length],
                    'opacity:1',
                    'border-radius:' + (isRound ? '999px' : '2px'),
                    'pointer-events:none',
                    'will-change:transform,opacity',
                    'transform:translate(-50%,-50%) rotate(' + (Math.random() * 360) + 'deg)',
                    'transition:transform ' + duration + 'ms cubic-bezier(0.15,0.75,0.25,1), opacity ' + duration + 'ms ease-out',
                ].join(';');
                confettiLayer.appendChild(piece);
                (function (el, dx, dy, dly, dur) {
                    setTimeout(function () {
                        el.style.transform = 'translate(calc(-50% + ' + dx + 'px), calc(-50% + ' + (dy + window.innerHeight * 0.55) + 'px)) rotate(' + (Math.random() * 720) + 'deg)';
                        el.style.opacity = '0';
                    }, dly);
                    setTimeout(function () { el.remove(); }, dly + dur + 80);
                })(piece, driftX, driftY, delay, duration);
            }
            setTimeout(function () { confettiLayer.remove(); }, 5200);
        },
        openHelp() {
            this.panel = 'help';
        },
        keepWaiting() {
            this.panel = 'waiting';
            if (this.state !== 'waiting') {
                this.state = 'waiting';
                this.attempts = 0;
                this.message = @js($waitingMessage);
                this.start();
            }
        },
        start() {
            this.stopTimers();
            this.startedAt = Date.now();
            this.elapsedSec = 0;
            this.showNoPrompt = false;
            this.celebrated = false;
            this.panel = 'waiting';
            this.tickTimer = setInterval(() => {
                this.elapsedSec = Math.floor((Date.now() - this.startedAt) / 1000);
            }, 1000);
            this.noPromptTimer = setTimeout(() => { this.showNoPrompt = true; }, this.noPromptAfterMs);
            this.poll();
            this.timer = setInterval(() => this.poll(), 5000);
        },
    }"
    x-init="if (! @js($startFailed)) { start(); }"
    class="max-w-xl mx-auto rounded-3xl bg-gradient-to-br from-brand via-brand to-brand/90 text-white shadow-lg shadow-brand/20 overflow-hidden"
>
    <div class="relative px-5 sm:px-7 py-7 sm:py-8 text-center">
        <div class="absolute inset-0 opacity-[0.14]" style="background-image: radial-gradient(circle at 18% 20%, #fff 0, transparent 42%), radial-gradient(circle at 88% 0%, #fbbf24 0, transparent 38%);"></div>
        <div class="relative">
            <div x-show="state === 'waiting' && panel === 'waiting'" class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/30">
                <svg class="h-6 w-6 animate-spin text-brand-gold" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </div>
            <div x-show="state === 'paid'" x-cloak class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-400/25 ring-1 ring-emerald-200/50">
                <svg class="h-7 w-7 text-brand-gold" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <div x-show="(state === 'failed' || state === 'timeout') && panel !== 'help'" x-cloak class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-rose-500/20 ring-1 ring-rose-300/40">
                <span class="text-xl font-bold text-rose-100">!</span>
            </div>
            <div x-show="panel === 'help' && state !== 'paid'" x-cloak class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/30">
                <span class="text-xl font-bold text-brand-gold">?</span>
            </div>

            <template x-if="panel === 'help' && state !== 'paid'">
                <div>
                    <h2 class="mt-2 text-xl sm:text-2xl font-extrabold tracking-tight"
                        x-text="state === 'failed'
                            ? @js(__('borrower.payment_waiting.operator_title'))
                            : @js(__('borrower.payment_waiting.help_title'))"></h2>
                    <p class="mt-3 text-sm text-white/85 max-w-sm mx-auto" x-text="message"></p>

                    <div class="mt-7 flex flex-wrap justify-center gap-3">
                        <form method="POST" action="{{ $retryUrl }}">
                            @csrf
                            <button type="submit" class="rounded-xl bg-brand-gold text-brand text-sm font-bold px-5 py-2.5">
                                {{ __('borrower.payment_waiting.try_again') }}
                            </button>
                        </form>
                        <a href="{{ $gateUrl }}"
                           class="rounded-xl bg-white/15 ring-1 ring-white/30 hover:bg-white/25 text-white text-sm font-bold px-5 py-2.5 transition inline-flex items-center">
                            {{ __('borrower.payment_waiting.change_phone') }}
                        </a>
                        @if ($canSwitchToBank)
                            <form method="POST" action="{{ $switchBankUrl }}">
                                @csrf
                                <button type="submit" class="rounded-xl bg-white/15 ring-1 ring-white/30 hover:bg-white/25 text-white text-sm font-bold px-5 py-2.5 transition">
                                    {{ __('borrower.payment_waiting.pay_by_bank') }}
                                </button>
                            </form>
                        @endif
                        <button type="button" @click="keepWaiting()"
                                x-show="state === 'timeout' || state === 'waiting'"
                                class="rounded-xl bg-white/15 ring-1 ring-white/30 hover:bg-white/25 text-white text-sm font-bold px-5 py-2.5 transition">
                            {{ __('borrower.payment_waiting.keep_waiting') }}
                        </button>
                    </div>
                </div>
            </template>

            <div x-show="panel !== 'help' || state === 'paid'">
                <h2 class="mt-2 text-xl sm:text-2xl font-extrabold tracking-tight"
                    x-text="state === 'paid'
                        ? paidTitle
                        : (state === 'failed'
                            ? @js(__('borrower.payment_waiting.failed_title'))
                            : (state === 'timeout'
                                ? @js(__('borrower.payment_waiting.timeout_title'))
                                : @js(__('borrower.payment_waiting.title'))))"></h2>
                <p class="mt-3 text-sm text-white/85 max-w-sm mx-auto" x-text="message"></p>

                <div class="mt-5 rounded-2xl bg-white/10 ring-1 ring-white/20 px-4 py-4 text-left max-w-sm mx-auto space-y-2">
                    <div class="flex justify-between gap-3 text-sm">
                        <span class="text-white/70">{{ __('borrower.payment_waiting.for') }}</span>
                        <span class="font-semibold text-right">{{ $payment->typeLabel() }}</span>
                    </div>
                    <div class="flex justify-between gap-3 text-sm">
                        <span class="text-white/70">{{ __('borrower.payment_waiting.amount') }}</span>
                        <span class="font-bold tabular-nums text-brand-gold">{{ format_money((float) $payment->amount) }}</span>
                    </div>
                    @if ($phone)
                        <div class="flex justify-between gap-3 text-sm">
                            <span class="text-white/70">{{ __('borrower.payment_waiting.phone') }}</span>
                            <span class="font-mono font-semibold">{{ $phone }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between gap-3 text-sm">
                        <span class="text-white/70">{{ __('borrower.payment_waiting.reference') }}</span>
                        <span class="font-mono font-semibold">{{ $payment->reference }}</span>
                    </div>
                </div>

                <div class="mt-4 max-w-sm mx-auto" x-show="state === 'waiting'" x-cloak>
                    <p class="text-xs text-white/70">{{ __('borrower.payment_waiting.wait_estimate') }}</p>
                    <p class="mt-1 text-sm font-semibold tabular-nums text-brand-gold">
                        <span x-text="elapsedLabel()"></span>
                        <span class="text-white/50 font-normal"> · {{ __('borrower.payment_waiting.elapsed_label') }}</span>
                    </p>
                </div>

                <div class="mt-6 flex flex-wrap justify-center gap-3" x-show="state === 'waiting' && showNoPrompt" x-cloak>
                    <button type="button" @click="openHelp()"
                            class="rounded-xl bg-white/15 ring-1 ring-white/30 hover:bg-white/25 text-white text-sm font-bold px-5 py-2.5 transition">
                        {{ __('borrower.payment_waiting.no_prompt') }}
                    </button>
                </div>

                <div class="mt-7 flex flex-wrap justify-center gap-3" x-show="state === 'paid'" x-cloak>
                    <a href="{{ $successUrl }}" class="rounded-xl bg-brand-gold text-brand text-sm font-bold px-5 py-2.5">
                        {{ __('borrower.celebration.cta_continue') }}
                    </a>
                </div>

                <div class="mt-7 flex flex-wrap justify-center gap-3" x-show="state === 'failed' || state === 'timeout'" x-cloak>
                    <a href="{{ $gateUrl }}"
                       class="rounded-xl bg-brand-gold text-brand text-sm font-bold px-5 py-2.5 inline-flex items-center">
                        {{ __('borrower.payment_waiting.change_phone') }}
                    </a>
                    <button type="button" @click="state = 'waiting'; attempts = 0; message = @js($waitingMessage); start()"
                            class="rounded-xl bg-white/15 ring-1 ring-white/30 text-white text-sm font-bold px-5 py-2.5">
                        {{ __('borrower.payment_waiting.check_again') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
