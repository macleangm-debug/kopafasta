@php
    $channel = old('payment_method', 'mobile_money') === 'bank_transfer' ? 'bank_transfer' : 'mobile_money';
    $feeQuote = $step === 'deposit' ? ($depositQuote ?? null) : ($reservationFeeQuote ?? null);
    $cfg = \App\Services\MembershipService::config();
@endphp

@if ($paymentGatewayDummy ?? false)
    <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900 mb-4">
        {{ __('borrower.apply.application_fee.dummy_banner') }}
    </div>
@endif

<form method="POST" action="{{ route('site.borrower.marketplace.reservation.pay', $assetId) }}" enctype="multipart/form-data" class="space-y-4 mt-4" x-data="{ channel: @js($channel), useWallet: {{ old('use_wallet') ? 'true' : 'false' }} }">
    @csrf
    <input type="hidden" name="step" value="{{ $step }}">

    <div class="rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 text-white p-5">
        @if ($feeQuote && ($feeQuote['base'] ?? 0) > 0)
            <x-site.payment-gate-breakdown
                :label="$step === 'deposit' ? __('borrower.marketplace.deposit') : __('borrower.marketplace.fees.application')"
                :currency="$cfg['currency'] ?? 'TZS'"
                :quote="$feeQuote"
                class="mb-3"
            />
        @else
            <p class="text-[10px] uppercase tracking-widest text-white/80">{{ $step === 'deposit' ? __('borrower.marketplace.deposit') : __('borrower.marketplace.fees.application') }}</p>
            <p class="mt-1 text-2xl font-extrabold">TZS {{ format_number($amount) }}</p>
        @endif
        @if (! empty($paymentReference))
            <p class="mt-2 text-xs text-white/90">{{ __('borrower.membership.payment_reference') }}: <span class="font-mono">{{ $paymentReference }}</span></p>
        @endif
    </div>

    <div class="rounded-xl bg-white ring-1 ring-gray-200 px-4 py-3 text-sm">
        <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('borrower.marketplace.promo_code_label') }}</label>
        <input type="text" name="promo_code" value="{{ old('promo_code') }}" maxlength="40" class="w-full rounded-lg border-gray-300 text-sm font-mono uppercase" placeholder="{{ __('borrower.marketplace.promo_code_placeholder') }}">
    </div>

    @if (($feeQuote['wallet_allowed'] ?? false) && ($referralWallet->balance ?? 0) > 0)
        <label class="flex items-start gap-3 rounded-xl bg-indigo-50 ring-1 ring-indigo-200 px-4 py-3 text-sm cursor-pointer">
            <input type="checkbox" name="use_wallet" value="1" x-model="useWallet" class="mt-1 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
            <span>{{ __('borrower.marketplace.use_referral_wallet', ['balance' => format_money($referralWallet->balance)]) }}</span>
        </label>
    @endif

    <div>
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">{{ __('borrower.marketplace.payment_method') }}</p>
        <div class="grid sm:grid-cols-2 gap-3">
            <label class="cursor-pointer">
                <input type="radio" name="payment_method" value="mobile_money" x-model="channel" class="sr-only peer" @checked($channel === 'mobile_money')>
                <div class="rounded-xl border-2 border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 p-3 text-sm">
                    <p class="font-semibold">{{ __('borrower.marketplace.payment_mobile') }}</p>
                </div>
            </label>
            <label class="cursor-pointer">
                <input type="radio" name="payment_method" value="bank_transfer" x-model="channel" class="sr-only peer" @checked($channel === 'bank_transfer')>
                <div class="rounded-xl border-2 border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 p-3 text-sm">
                    <p class="font-semibold">{{ __('borrower.marketplace.payment_bank') }}</p>
                </div>
            </label>
        </div>
    </div>

    <div x-show="channel === 'mobile_money'" x-cloak>
        @if ($mobileDetails ?? null)
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4 text-sm space-y-1">
                <p class="font-semibold">{{ $mobileDetails['provider'] ?? 'Mobile money' }}</p>
                <p class="font-mono">{{ $mobileDetails['paybill'] ?? $mobileDetails['number'] ?? '—' }}</p>
                @if (! empty($mobileDetails['instructions']))
                    <p class="text-xs text-gray-600">{{ $mobileDetails['instructions'] }}</p>
                @endif
            </div>
        @endif
        <label class="block mt-3">
            <span class="text-xs font-medium text-gray-600">{{ __('borrower.membership.mobile_number') }}</span>
            <input type="text" name="mobile_number" value="{{ old('mobile_number') }}" class="mt-1 w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm" placeholder="255712345678">
        </label>
    </div>

    <div x-show="channel === 'bank_transfer'" x-cloak class="space-y-3">
        @foreach ($bankAccounts ?? [] as $account)
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4 text-sm">
                <p class="font-semibold">{{ $account['bank'] ?? $account['bank_name'] ?? 'Bank' }}</p>
                <p class="font-mono text-xs mt-1">{{ $account['account_number'] ?? '—' }}</p>
                <p class="text-xs text-gray-600 mt-1">{{ $account['account_name'] ?? '' }}</p>
                @if (! empty($account['reference']))
                    <p class="text-xs text-gray-500 mt-1">{{ __('borrower.membership.payment_reference') }}: <span class="font-mono">{{ $account['reference'] }}</span></p>
                @endif
            </div>
        @endforeach
        <label class="block">
            <span class="text-xs font-medium text-gray-600">{{ __('borrower.membership.payment_date') }}</span>
            <input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" class="mt-1 w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm">
        </label>
        <label class="block">
            <span class="text-xs font-medium text-gray-600">{{ __('borrower.membership.proof_optional') }}</span>
            <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 w-full text-sm">
        </label>
    </div>

    @if ($errors->any())
        <div class="rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
        {{ __('borrower.marketplace.pay_now') }}
    </button>
</form>
