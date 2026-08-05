<x-site.borrower-layout :title="brand_title($isFirstTime ? __('borrower.membership.registration_fee') : __('borrower.membership.renewal_fee'))" active="profile" content-width="wide">
    @php
        $cashDue = (int) ($isFirstTime && $feeQuote
            ? ($feeQuote['cash_due'] ?? $feeQuote['after_discount'] ?? $feeAmount)
            : $feeAmount);
    @endphp

    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-brand mb-1">{{ $isFirstTime ? __('borrower.membership.first_time') : __('borrower.membership.renewal') }}</p>
        <h1 class="text-2xl sm:text-3xl font-bold">
            {{ $isFirstTime ? __('borrower.membership.registration_title') : __('borrower.membership.renew_title') }}
        </h1>
        <p class="text-sm text-gray-500 mt-1">
            @if ($isFirstTime)
                {{ __('borrower.membership.duration_first', ['days' => $config['duration_days']]) }}
            @else
                {{ __('borrower.membership.duration_renew', ['days' => $config['duration_days']]) }}
            @endif
        </p>
    </div>

    <div class="glass-card overflow-hidden ring-1 ring-brand/15 mb-6">
        <div class="bg-gradient-to-br from-brand to-brand-light text-white px-6 py-5">
            <p class="text-[10px] uppercase tracking-widest text-white/80">{{ $isFirstTime ? __('borrower.membership.registration_fee') : __('borrower.membership.renewal_fee') }}</p>
            <p class="mt-2 text-3xl font-extrabold tabular-nums">{{ $config['currency'] }} {{ format_number($cashDue) }}</p>
            <p class="mt-3 text-xs text-white/90">{{ __('borrower.membership.payment_reference_label') }}</p>
            <p class="mt-1 font-mono text-sm bg-white/15 inline-block px-3 py-1 rounded-lg">{{ $paymentReference }}</p>
        </div>
        @if ($isFirstTime && $feeQuote)
            <div class="px-6 py-4 bg-white border-t border-gray-100">
                <x-site.payment-gate-breakdown
                    :label="$isFirstTime ? __('borrower.membership.registration_fee') : __('borrower.membership.renewal_fee')"
                    :currency="$config['currency']"
                    :quote="$feeQuote"
                    variant="light"
                />
            </div>
        @endif
    </div>

    @if ($isFirstTime)
        <x-site.promo-code-toggle
            :action="route('site.membership.renew')"
            :value="old('promo_code', request('promo_code'))"
            :quote="$feeQuote"
        />
    @endif

    @if ($isFirstTime && $feeQuote && ($referralWallet->balance ?? 0) > 0)
        <div class="mb-6 rounded-xl bg-brand-muted/40 ring-1 ring-brand/15 px-4 py-4 text-sm text-brand">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="use_wallet" value="1" form="membership-renew-form" @checked(old('use_wallet')) class="mt-1 rounded border-brand/30 text-brand focus:ring-brand">
                <span>
                    {{ __('borrower.membership.use_wallet_label', [
                        'balance' => $config['currency'].' '.format_number($referralWallet->balance),
                        'percent' => rtrim(rtrim(format_number($referralSettings['wallet_max_fee_percent'], 2), '0'), '.'),
                    ]) }}
                </span>
            </label>
            @if (($feeQuote['wallet_applied'] ?? 0) > 0)
                <p class="mt-3 text-xs">{{ __('borrower.membership.wallet_credit_hint', [
                    'wallet' => $config['currency'].' '.format_number($feeQuote['wallet_applied']),
                    'cash' => $config['currency'].' '.format_number($feeQuote['cash_due']),
                ]) }}</p>
            @endif
        </div>
    @endif

    <form id="membership-renew-form" method="POST" action="{{ route('site.membership.renew.post') }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-5">
        @csrf
        @if ($isFirstTime && filled(request('promo_code')))
            <input type="hidden" name="promo_code" value="{{ request('promo_code') }}">
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

        <button type="submit" class="w-full bg-brand hover:bg-brand-light text-white font-semibold px-5 py-3 rounded-xl text-sm">
            {{ __('borrower.membership.pay_now') }}
        </button>
    </form>
</x-site.borrower-layout>
