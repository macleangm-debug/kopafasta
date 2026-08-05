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
        </div>

        <div class="rounded-3xl overflow-hidden bg-gradient-to-br from-brand via-brand to-brand-light text-white shadow-lg shadow-brand/20">
            <div class="px-6 py-7">
                <p class="text-[10px] uppercase tracking-[0.2em] text-white/70 font-semibold">{{ $isFirstTime ? __('borrower.membership.membership_fee') : __('borrower.membership.renewal_fee') }}</p>
                <div class="mt-3 flex flex-wrap items-end gap-3">
                    @if ($hasDiscount)
                        <p class="text-lg text-white/60 line-through tabular-nums">{{ $config['currency'] }} {{ format_number($baseAmount) }}</p>
                    @endif
                    <p class="text-4xl font-extrabold tabular-nums tracking-tight">{{ $config['currency'] }} {{ format_number($cashDue) }}</p>
                    @if ($hasDiscount)
                        <span class="mb-1.5 inline-flex rounded-full bg-brand-gold/20 text-brand-gold text-[10px] font-bold uppercase tracking-wide px-2.5 py-1">
                            {{ __('borrower.membership.you_save', ['amount' => $config['currency'].' '.format_number($baseAmount - $cashDue)]) }}
                        </span>
                    @endif
                </div>
                <p class="mt-4 text-xs text-white/70">{{ __('borrower.membership.payment_reference_label') }}</p>
                <p class="mt-1 font-mono text-sm bg-white/15 inline-block px-3 py-1.5 rounded-lg">{{ $paymentReference }}</p>
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
                <form method="GET" action="{{ route('site.membership.renew') }}" class="space-y-3"
                      x-data="{ applying: false }" @submit="applying = true">
                    <label class="block text-sm font-semibold text-gray-900">{{ __('borrower.membership.promo_inline_label') }}</label>
                    <div class="flex gap-2">
                        <input type="text" name="promo_code" value="{{ $promoCode }}" maxlength="40"
                               class="flex-1 rounded-xl border-gray-200 text-sm font-mono uppercase"
                               placeholder="{{ __('borrower.membership.promo_code_placeholder') }}"
                               autocomplete="off">
                        <button type="submit" class="shrink-0 rounded-xl bg-brand-gold text-brand font-bold text-sm px-4 py-2.5 disabled:opacity-60"
                                :disabled="applying">
                            <span x-show="!applying">{{ __('borrower.membership.apply_promo') }}</span>
                            <span x-cloak x-show="applying">{{ __('borrower.membership.applying_promo') }}</span>
                        </button>
                    </div>
                    @if ($promoAttempted)
                        <p @class(['text-xs font-medium', $promoValid ? 'text-emerald-700' : 'text-rose-700'])>
                            {{ $promoValid
                                ? __('borrower.membership.promo_applied', ['code' => strtoupper((string) $promoCode)])
                                : __('borrower.membership.promo_invalid') }}
                        </p>
                    @endif
                </form>
                <div class="border-t border-gray-100"></div>
            @endif

            <form method="POST" action="{{ route('site.membership.renew.post') }}" class="space-y-5"
                  x-data="{ paying: false }" @submit="paying = true">
                @csrf
                @if ($promoAllows && filled($promoCode))
                    <input type="hidden" name="promo_code" value="{{ $promoCode }}">
                @endif

                @if ($isFirstTime && $feeQuote && ($referralWallet->balance ?? 0) > 0)
                    <label class="flex items-start gap-3 cursor-pointer rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3 text-sm text-gray-700">
                        <input type="checkbox" name="use_wallet" value="1" @checked(old('use_wallet')) class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand">
                        <span>
                            {{ __('borrower.membership.use_wallet_label', [
                                'balance' => $config['currency'].' '.format_number($referralWallet->balance),
                                'percent' => rtrim(rtrim(format_number($referralSettings['wallet_max_fee_percent'], 2), '0'), '.'),
                            ]) }}
                        </span>
                    </label>
                @endif

                <x-site.payment-method-picker
                    :amount="$cashDue"
                    method-field="channel"
                    mobile-field="payment_phone"
                    mobile-value="mobile_money"
                    bank-value="bank"
                    :default-method="old('channel', 'mobile_money')"
                    :mobile-details="[]"
                    :bank-accounts="$bankAccounts ?? []"
                    :bank-reference="$paymentReference"
                    :mobile-input-value="old('payment_phone', $customer->phone ?? '')"
                />
                @error('payment_phone')
                    <p class="text-sm text-rose-700 -mt-2">{{ $message }}</p>
                @enderror
                @error('channel')
                    <p class="text-sm text-rose-700 -mt-2">{{ $message }}</p>
                @enderror

                <button type="submit" :disabled="paying"
                        class="w-full bg-brand hover:bg-brand-light text-white font-semibold px-5 py-3.5 rounded-xl text-sm shadow-sm disabled:opacity-70">
                    <span x-show="!paying">{{ __('borrower.membership.pay_now') }}</span>
                    <span x-cloak x-show="paying">{{ __('borrower.membership.paying') }}</span>
                </button>
            </form>
        </div>
    </div>
</x-site.borrower-layout>
