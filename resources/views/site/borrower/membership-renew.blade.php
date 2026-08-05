<x-site.borrower-layout :title="brand_title($isFirstTime ? __('borrower.membership.membership_fee') : __('borrower.membership.renewal_fee'))" active="dashboard" content-width="wide">
    @php
        $cashDue = (int) ($isFirstTime && $feeQuote
            ? ($feeQuote['cash_due'] ?? $feeQuote['after_discount'] ?? $feeAmount)
            : $feeAmount);
        $baseAmount = (int) $feeAmount;
        $hasDiscount = $isFirstTime && $feeQuote && $cashDue < $baseAmount;
        $promoAllows = $isFirstTime; // membership first-time gate supports promo/affiliate codes
    @endphp

    @if ($errors->any())
        <div class="mb-5 rounded-2xl bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-800">
            <p class="font-semibold">{{ __('borrower.membership.payment_error_title') }}</p>
            <ul class="mt-1 list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-brand mb-1">{{ $isFirstTime ? __('borrower.membership.first_time') : __('borrower.membership.renewal') }}</p>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">
            {{ $isFirstTime ? __('borrower.membership.membership_title') : __('borrower.membership.renew_title') }}
        </h1>
        <p class="text-sm text-gray-500 mt-1">
            @if ($isFirstTime)
                {{ __('borrower.membership.duration_first', ['days' => $config['duration_days']]) }}
            @else
                {{ __('borrower.membership.duration_renew', ['days' => $config['duration_days']]) }}
            @endif
        </p>
    </div>

    <div class="grid lg:grid-cols-5 gap-6 items-start">
        <div class="lg:col-span-3 space-y-5">
            <div class="rounded-3xl overflow-hidden bg-gradient-to-br from-brand via-brand to-brand-light text-white shadow-lg shadow-brand/20">
                <div class="px-6 sm:px-8 py-7">
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
                    <p class="mt-4 text-xs text-white/80">{{ __('borrower.membership.payment_reference_label') }}</p>
                    <p class="mt-1 font-mono text-sm bg-white/15 inline-block px-3 py-1.5 rounded-lg">{{ $paymentReference }}</p>
                </div>
                @if ($isFirstTime && $feeQuote)
                    <div class="px-6 sm:px-8 py-4 bg-white/5 border-t border-white/10">
                        <x-site.payment-gate-breakdown
                            :label="__('borrower.membership.membership_fee')"
                            :currency="$config['currency']"
                            :quote="$feeQuote"
                            variant="dark"
                        />
                    </div>
                @endif
            </div>

            <form id="membership-renew-form" method="POST" action="{{ route('site.membership.renew.post') }}" class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 p-6 space-y-5">
                @csrf
                @if ($isFirstTime && filled(old('promo_code', request('promo_code'))))
                    <input type="hidden" name="promo_code" value="{{ old('promo_code', request('promo_code')) }}">
                @endif

                <x-site.payment-method-picker
                    :amount="$cashDue"
                    form-id="membership-renew-form"
                    method-field="channel"
                    mobile-field="payment_phone"
                    mobile-value="mobile_money"
                    bank-value="bank"
                    :default-method="old('channel', 'mobile_money')"
                    :mobile-details="$mobileDetails ?? []"
                    :bank-accounts="$bankAccounts ?? []"
                    :bank-reference="$paymentReference"
                    :mobile-input-value="old('payment_phone', $customer->phone ?? '')"
                />
                @error('payment_phone')
                    <p class="text-sm text-rose-700">{{ $message }}</p>
                @enderror
                @error('mobile_number')
                    <p class="text-sm text-rose-700">{{ $message }}</p>
                @enderror
                @error('channel')
                    <p class="text-sm text-rose-700">{{ $message }}</p>
                @enderror

                <button type="submit" class="w-full bg-brand hover:bg-brand-light text-white font-semibold px-5 py-3.5 rounded-xl text-sm shadow-sm">
                    {{ __('borrower.membership.pay_now') }}
                </button>
            </form>
        </div>

        <div class="lg:col-span-2 space-y-5">
            @if ($promoAllows)
                <div class="rounded-2xl bg-white shadow-sm ring-1 ring-brand/15 p-5 space-y-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.membership.promo_section_eyebrow') }}</p>
                        <h2 class="mt-1 text-base font-bold text-gray-900">{{ __('borrower.membership.promo_section_title') }}</h2>
                        <p class="mt-1 text-xs text-gray-500">{{ __('borrower.membership.promo_section_hint') }}</p>
                    </div>
                    <form method="GET" action="{{ route('site.membership.renew') }}" class="space-y-3">
                        <label class="block text-xs font-semibold text-gray-600">{{ __('borrower.apply.application_fee.promo_label') }}</label>
                        <div class="flex gap-2">
                            <input type="text" name="promo_code" value="{{ old('promo_code', request('promo_code')) }}" maxlength="40"
                                   class="flex-1 rounded-xl border-gray-200 text-sm font-mono uppercase"
                                   placeholder="{{ __('borrower.membership.promo_code_placeholder') }}"
                                   autocomplete="off">
                            <button type="submit" class="shrink-0 rounded-xl bg-brand-gold text-brand font-bold text-sm px-4 py-2.5">
                                {{ __('borrower.membership.apply_promo') }}
                            </button>
                        </div>
                        @if ($feeQuote && filled($feeQuote['promo_code'] ?? null))
                            <p @class(['text-xs font-medium', ($feeQuote['promo_valid'] ?? false) ? 'text-emerald-700' : 'text-rose-700'])>
                                @if ($feeQuote['promo_valid'] ?? false)
                                    {{ __('borrower.membership.promo_applied', ['code' => $feeQuote['promo_code']]) }}
                                @else
                                    {{ __('borrower.membership.promo_invalid') }}
                                @endif
                            </p>
                        @elseif ($hasDiscount && blank(request('promo_code')))
                            <p class="text-xs text-emerald-700 font-medium">{{ __('borrower.membership.campaign_applied') }}</p>
                        @endif
                    </form>
                </div>
            @endif

            @if ($isFirstTime && $feeQuote && ($referralWallet->balance ?? 0) > 0)
                <div class="rounded-2xl bg-brand-muted/40 ring-1 ring-brand/15 px-5 py-4 text-sm text-brand">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="use_wallet" value="1" form="membership-renew-form" @checked(old('use_wallet')) class="mt-1 rounded border-brand/30 text-brand focus:ring-brand">
                        <span>
                            {{ __('borrower.membership.use_wallet_label', [
                                'balance' => $config['currency'].' '.format_number($referralWallet->balance),
                                'percent' => rtrim(rtrim(format_number($referralSettings['wallet_max_fee_percent'], 2), '0'), '.'),
                            ]) }}
                        </span>
                    </label>
                </div>
            @endif

            <div class="rounded-2xl bg-gray-50 ring-1 ring-gray-200 px-5 py-4 text-sm text-gray-600 space-y-2">
                <p class="font-semibold text-gray-900">{{ __('borrower.membership.why_pay_title') }}</p>
                <p>{{ __('borrower.membership.why_pay_body') }}</p>
            </div>
        </div>
    </div>
</x-site.borrower-layout>
