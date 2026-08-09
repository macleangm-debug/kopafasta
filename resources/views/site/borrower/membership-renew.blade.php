<x-site.borrower-layout :title="brand_title($isFirstTime ? __('borrower.membership.membership_fee') : __('borrower.membership.renewal_fee'))" active="dashboard">
    @php
        $cashDue = (int) ($isFirstTime && $feeQuote
            ? ($feeQuote['cash_due'] ?? $feeQuote['after_discount'] ?? $feeAmount)
            : $feeAmount);
        $baseAmount = (int) $feeAmount;
        $hasDiscount = $isFirstTime && $feeQuote && $cashDue < $baseAmount;
        $promoAllows = $isFirstTime;
        $promoCode = old('promo_code', request('promo_code'));
        $promoValid = (bool) ($feeQuote['promo_valid'] ?? false);
        $promoAttempted = $isFirstTime && filled($promoCode);
        $codeKind = $feeQuote['code_kind'] ?? ($promoValid ? 'promo' : ($promoAttempted ? 'invalid' : null));
        $promoFeedbackTitle = match ($codeKind) {
            'affiliate' => __('borrower.membership.affiliate_ok_title'),
            'promo' => __('borrower.membership.promo_ok_title'),
            default => __('borrower.membership.promo_bad_title'),
        };
        $promoFeedbackMessage = match ($codeKind) {
            'affiliate', 'promo' => '',
            default => __('borrower.membership.promo_invalid'),
        };
    @endphp

    @if ($errors->any())
        <div class="mb-5 max-w-xl mx-auto rounded-2xl bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-800">
            <p class="font-semibold">{{ __('borrower.membership.payment_error_title') }}</p>
            <ul class="mt-1 list-disc pl-5 space-y-0.5">
                @foreach (collect($errors->all())->unique() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="max-w-xl mx-auto space-y-5">
        <div>
            <p class="text-xs uppercase tracking-widest text-brand mb-1">{{ $isFirstTime ? __('borrower.membership.first_time') : __('borrower.membership.renewal') }}</p>
            <h1 class="text-2xl font-bold text-gray-900">
                {{ $isFirstTime ? __('borrower.membership.membership_title') : __('borrower.membership.renew_title') }}
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ __('borrower.membership.duration_short', ['days' => $config['duration_days']]) }}
            </p>
            <p class="text-sm text-gray-600 mt-3">{{ __('borrower.membership.open_gate_hint') }}</p>
        </div>

        <div class="rounded-3xl overflow-hidden bg-gradient-to-br from-brand via-brand to-brand-light text-white shadow-lg shadow-brand/20">
            <div class="px-6 py-7">
                <p class="text-[10px] uppercase tracking-[0.2em] text-white/70 font-semibold">
                    {{ $isFirstTime ? __('borrower.membership.membership_fee') : __('borrower.membership.renewal_fee') }}
                </p>
                <p class="mt-3 text-4xl font-extrabold tabular-nums tracking-tight">
                    {{ $config['currency'] }} {{ format_number((float) $cashDue) }}
                </p>
                @if ($paymentReference)
                    <p class="mt-4 text-xs text-white/70">{{ __('borrower.membership.payment_reference_label') }}</p>
                    <p class="mt-1 font-mono text-sm bg-white/15 inline-block px-3 py-1.5 rounded-lg">{{ $paymentReference }}</p>
                @endif
            </div>
            @if ($hasDiscount)
                <div class="px-6 py-4 bg-white/5 border-t border-white/10 text-sm space-y-1.5">
                    <div class="flex justify-between gap-3 text-white/80">
                        <span>{{ __('borrower.membership.membership_fee') }}</span>
                        <span class="tabular-nums">{{ $config['currency'] }} {{ format_number($baseAmount) }}</span>
                    </div>
                    <div class="flex justify-between gap-3 text-brand-gold">
                        <span>{{ __('borrower.membership.promo_discount_label') }}</span>
                        <span class="tabular-nums">− {{ $config['currency'] }} {{ format_number($baseAmount - $cashDue) }}</span>
                    </div>
                    <div class="flex justify-between gap-3 font-semibold text-white pt-1 border-t border-white/10">
                        <span>{{ __('borrower.membership.amount_due') }}</span>
                        <span class="tabular-nums">{{ $config['currency'] }} {{ format_number($cashDue) }}</span>
                    </div>
                </div>
            @endif
        </div>

        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 p-5 sm:p-6 space-y-5">
            @if ($promoAllows)
                @if ($promoAttempted && ! $promoValid)
                    <div
                        x-data
                        x-init="
                            const detail = {
                                tone: 'error',
                                title: @js($promoFeedbackTitle),
                                message: @js($promoFeedbackMessage),
                            };
                            const fire = () => {
                                if (typeof window.showBorrowerFeedback === 'function') {
                                    window.showBorrowerFeedback(detail);
                                } else {
                                    window.dispatchEvent(new CustomEvent('open-feedback-default', { detail }));
                                }
                            };
                            setTimeout(fire, 50);
                        "
                        class="hidden"
                        aria-hidden="true"
                    ></div>
                @elseif ($promoAttempted && $promoValid)
                    <div
                        x-data
                        x-init="
                            const detail = {
                                tone: 'success',
                                title: @js($promoFeedbackTitle),
                                message: '',
                            };
                            const fire = () => {
                                if (typeof window.showBorrowerFeedback === 'function') {
                                    window.showBorrowerFeedback(detail);
                                } else {
                                    window.dispatchEvent(new CustomEvent('open-feedback-default', { detail }));
                                }
                            };
                            setTimeout(fire, 50);
                        "
                        class="hidden"
                        aria-hidden="true"
                    ></div>
                @endif
                <form method="GET" action="{{ route('site.membership.renew') }}" class="space-y-3"
                      x-data="{ applying: false }" @submit="applying = true">
                    <label class="block text-sm font-semibold text-gray-900">{{ __('borrower.membership.promo_inline_label') }}</label>
                    <p class="text-xs text-gray-500">{{ __('borrower.membership.promo_or_reward_hint') }}</p>
                    <div class="flex gap-2">
                        <input type="text" name="promo_code" value="{{ $promoCode }}" maxlength="40"
                               class="flex-1 rounded-xl border-gray-200 text-sm font-mono uppercase @error('promo_code') border-rose-400 @enderror"
                               placeholder="{{ __('borrower.membership.promo_code_placeholder') }}"
                               autocomplete="off">
                        <button type="submit" class="shrink-0 rounded-xl bg-brand-gold text-brand font-bold text-sm px-4 py-2.5 disabled:opacity-60"
                                :disabled="applying">
                            <span x-show="!applying">{{ __('borrower.membership.apply_promo') }}</span>
                            <span x-cloak x-show="applying">{{ __('borrower.membership.applying_promo') }}</span>
                        </button>
                    </div>
                </form>
                <div class="border-t border-gray-100"></div>
            @endif

            @if ($isFirstTime && ($feeQuote['wallet_allowed'] ?? false) && ($feeQuote['wallet_usable'] ?? 0) > 0)
                <form method="POST" action="{{ route('site.membership.renew.post') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="channel" value="mobile_money">
                    <input type="hidden" name="payment_phone" value="{{ old('payment_phone', $customer->phone ?? '') }}">
                    @if ($promoAllows && $promoValid && filled($promoCode))
                        <input type="hidden" name="promo_code" value="{{ $promoCode }}">
                    @endif
                    <label class="flex items-start gap-3 cursor-pointer text-sm text-brand">
                        <input type="checkbox" name="use_wallet" value="1" @checked(old('use_wallet')) class="mt-1 rounded border-brand/30 text-brand focus:ring-brand">
                        <span>{{ __('borrower.membership.use_wallet_label', [
                            'balance' => number_format(wallet_balance_as_points((float) ($feeQuote['wallet_usable'] ?? 0))),
                            'percent' => rtrim(rtrim(format_number($referralSettings['wallet_max_fee_percent'] ?? 0, 2), '0'), '.'),
                        ]) }}</span>
                    </label>
                    <button type="submit" class="w-full rounded-xl bg-brand hover:bg-brand-light text-white font-semibold px-5 py-3 text-sm shadow-sm">
                        {{ __('borrower.membership.continue_to_payment') }}
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('site.membership.renew.post') }}">
                    @csrf
                    <input type="hidden" name="channel" value="mobile_money">
                    <input type="hidden" name="payment_phone" value="{{ old('payment_phone', $customer->phone ?? '') }}">
                    @if ($promoAllows && $promoValid && filled($promoCode))
                        <input type="hidden" name="promo_code" value="{{ $promoCode }}">
                    @endif
                    <button type="submit" class="w-full rounded-xl bg-brand hover:bg-brand-light text-white font-semibold px-5 py-3 text-sm shadow-sm">
                        {{ __('borrower.membership.continue_to_payment') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
</x-site.borrower-layout>
