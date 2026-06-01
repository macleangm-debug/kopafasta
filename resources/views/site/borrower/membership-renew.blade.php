<x-site.borrower-layout :title="brand_title($isFirstTime ? __('borrower.membership.registration_fee') : __('borrower.membership.renewal_fee'))" active="membership">
    <div class="max-w-2xl mx-auto" x-data="{ channel: '{{ old('channel', 'mobile_money') }}', phone: '{{ old('payment_phone', $customer->phone ?? '') }}', useWallet: {{ old('use_wallet') ? 'true' : 'false' }} }">
        <div class="mb-6">
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">{{ $isFirstTime ? __('borrower.membership.first_time') : __('borrower.membership.renewal') }}</p>
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

        <div class="rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 text-white p-6 shadow-lg mb-6">
            <p class="text-[10px] uppercase tracking-widest text-white/80">{{ $isFirstTime ? __('borrower.membership.registration_fee') : __('borrower.membership.renewal_fee') }}</p>
            @if ($isFirstTime && $feeQuote)
                <p class="mt-1 text-sm text-white/80 line-through">{{ $config['currency'] }} {{ number_format($feeQuote['base']) }}</p>
                <p class="mt-1 text-3xl font-extrabold">{{ $config['currency'] }} {{ number_format($feeQuote['after_discount']) }}</p>
                @if ($feeQuote['has_referrer'])
                    <p class="mt-2 text-xs text-white/90">Referral discount applied ({{ rtrim(rtrim(number_format($referralSettings['discount_percent'], 2), '0'), '.') }}%)</p>
                @endif
            @else
                <p class="mt-1 text-3xl font-extrabold">{{ $config['currency'] }} {{ number_format($feeAmount) }}</p>
            @endif
            <p class="mt-3 text-xs text-white/90">Payment reference (auto-generated)</p>
            <p class="mt-1 font-mono text-sm bg-white/15 inline-block px-3 py-1 rounded-lg">{{ $paymentReference }}</p>
        </div>

        @if ($isFirstTime && $feeQuote && ($referralWallet->balance ?? 0) > 0)
            <div class="mb-6 rounded-xl bg-indigo-50 ring-1 ring-indigo-200 px-4 py-4 text-sm text-indigo-900">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="use_wallet" value="1" x-model="useWallet" form="membership-renew-form" class="mt-1 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                    <span>
                        Use referral wallet balance (<strong>{{ $config['currency'] }} {{ number_format($referralWallet->balance) }}</strong>).
                        Up to {{ rtrim(rtrim(number_format($referralSettings['wallet_max_fee_percent'], 2), '0'), '.') }}% of this fee can be paid from your wallet.
                    </span>
                </label>
                @if ($feeQuote['wallet_applied'] > 0)
                    <p class="mt-3 text-xs">Wallet credit: <strong>{{ $config['currency'] }} {{ number_format($feeQuote['wallet_applied']) }}</strong> · Cash due: <strong>{{ $config['currency'] }} {{ number_format($feeQuote['cash_due']) }}</strong></p>
                @endif
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-700">
                @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form id="membership-renew-form" method="POST" action="{{ route('site.membership.renew.post') }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-5">
            @csrf

            <div>
                <p class="text-xs uppercase tracking-wider text-gray-500 mb-3">{{ __('borrower.membership.payment_method') }}</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="cursor-pointer">
                        <input type="radio" name="channel" value="mobile_money" x-model="channel" class="sr-only peer">
                        <div class="rounded-xl border-2 border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 p-4">
                            <p class="font-semibold text-sm">{{ __('borrower.membership.mobile_money') }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.membership.mobile_hint') }}</p>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="channel" value="bank" x-model="channel" class="sr-only peer">
                        <div class="rounded-xl border-2 border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 p-4">
                            <p class="font-semibold text-sm">{{ __('borrower.membership.bank') }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.membership.bank_hint') }}</p>
                        </div>
                    </label>
                </div>
            </div>

            <div x-show="channel === 'mobile_money'" x-transition>
                <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">Mobile money number</label>
                <input type="tel" name="payment_phone" x-model="phone" required
                       class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                       placeholder="+255 7XX XXX XXX">
                <p class="mt-2 text-xs text-gray-500">You will receive a USSD push. Confirm on your phone — membership activates instantly.</p>
            </div>

            <div x-show="channel === 'bank'" x-cloak x-transition class="rounded-xl bg-sky-50 border border-sky-200 p-4 text-sm">
                <p class="font-semibold text-sky-900 mb-2">Banking instructions</p>
                <p class="text-sky-800 text-xs mb-3">Use reference <span class="font-mono font-bold">{{ $paymentReference }}</span> when paying. Activation after our team verifies the transfer.</p>
                @foreach ($bankAccounts as $acct)
                    <div class="mb-2 last:mb-0">
                        <p class="font-medium">{{ $acct['bank'] }}</p>
                        <p class="text-xs text-sky-800">{{ $acct['account_name'] }} · {{ $acct['account_number'] }} · {{ $acct['branch'] ?? '' }}</p>
                    </div>
                @endforeach
                <p class="mt-3 text-xs text-amber-800 font-medium">⏳ Waiting for verification after you submit.</p>
            </div>

            <button type="submit" class="w-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-3 rounded-full text-sm">
                <span x-text="channel === 'mobile_money' ? @js(__('borrower.membership.pay_now')) : @js(__('borrower.membership.submit_bank'))"></span>
            </button>
        </form>
    </div>
</x-site.borrower-layout>
