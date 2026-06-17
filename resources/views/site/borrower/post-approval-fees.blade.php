<x-site.borrower-layout :title="brand_title(__('borrower.post_approval_fees.page_title'))" active="loans" content-width="wide">
    <div x-data="{ channel: @js(old('channel', 'mobile_money')) }">
        <div class="mb-6">
            <a href="{{ route('site.borrower.application', $application) }}" class="text-sm text-amber-700 hover:text-amber-800">&larr; {{ __('borrower.post_approval_fees.back') }}</a>
            <h1 class="text-2xl font-bold mt-2">{{ __('borrower.post_approval_fees.page_title') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $application->product?->name }}</p>
        </div>

        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
                <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-gray-500">{{ __('borrower.post_approval_fees.loan_amount') }}</dt>
                        <dd class="font-semibold mt-0.5">{{ format_money($loanAmount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('borrower.post_approval_fees.application_ref') }}</dt>
                        <dd class="font-mono text-xs mt-0.5">{{ $application->application_number }}</dd>
                    </div>
                </dl>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($feeLines as $line)
                    <li class="px-5 py-4 flex items-center justify-between gap-3">
                        <div>
                            <p class="font-medium text-gray-900">{{ $line['name'] }}</p>
                            @if ($line['rate_label'])
                                <p class="text-xs text-gray-500">{{ __('borrower.post_approval_fees.fee_rate') }}: {{ $line['rate_label'] }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <p class="font-semibold">{{ format_money($line['amount']) }}</p>
                            <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ $line['paid'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $line['paid'] ? __('borrower.post_approval_fees.paid') : __('borrower.post_approval_fees.due') }}
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="px-5 py-4 bg-gray-50 space-y-2">
                <x-site.payment-gate-breakdown
                    :label="__('borrower.post_approval_fees.total_due')"
                    currency="TZS"
                    :quote="$feeQuote"
                    variant="light"
                    class="mb-0"
                />
            </div>
        </div>

        @if ($application->postApprovalFees->contains(fn ($f) => ! $f->isPaid()))
            @if (payment_gateway_is_dummy())
                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900 mb-4">
                    {{ __('borrower.apply.application_fee.dummy_banner') }}
                </div>
            @endif

            @if ($wallet->balance > 0 && ($maxWalletQuote['wallet_usable'] ?? 0) > 0)
                <div class="mb-6 rounded-xl bg-indigo-50 ring-1 ring-indigo-200 px-4 py-4 text-sm text-indigo-900">
                    <p>{{ __('borrower.post_approval_fees.wallet_balance', ['balance' => format_money($wallet->balance)]) }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('site.borrower.application.post-approval-fees.pay', $application) }}" enctype="multipart/form-data" class="space-y-4 bg-white rounded-2xl border border-gray-200 p-6">
                @csrf

                <div class="rounded-2xl bg-gradient-to-br from-amber-500 to-amber-600 text-white p-5">
                    <x-site.payment-gate-breakdown
                        :label="__('borrower.post_approval_fees.page_title')"
                        currency="TZS"
                        :quote="$feeQuote"
                        class="mb-2"
                    />
                    <p class="mt-2 text-xs text-white/90">{{ __('borrower.membership.payment_reference') }}: <span class="font-mono">{{ $paymentReference }}</span></p>
                </div>

                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3 text-sm">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">{{ __('borrower.post_approval_fees.promo_code_label') }}</label>
                    <input type="text" name="promo_code" value="{{ old('promo_code') }}" maxlength="40" class="w-full rounded-lg border-gray-300 text-sm font-mono uppercase" placeholder="{{ __('borrower.post_approval_fees.promo_code_placeholder') }}">
                </div>

                <div>
                    <p class="text-xs uppercase tracking-wider text-gray-500 mb-2">{{ __('borrower.membership.payment_method') }}</p>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="channel" value="mobile_money" x-model="channel" class="sr-only peer" @checked(old('channel', 'mobile_money') === 'mobile_money')>
                            <div class="rounded-xl border-2 border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 p-3 text-sm">
                                <p class="font-semibold">{{ __('borrower.membership.mobile_money') }}</p>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="channel" value="bank" x-model="channel" class="sr-only peer" @checked(old('channel') === 'bank')>
                            <div class="rounded-xl border-2 border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 p-3 text-sm">
                                <p class="font-semibold">{{ __('borrower.membership.bank') }}</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div x-show="channel === 'mobile_money'" x-cloak>
                    @if (! empty($mobileDetails))
                        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4 text-sm space-y-1 mb-3">
                            <p class="font-semibold">{{ $mobileDetails['provider'] ?? 'Mobile money' }}</p>
                            <p class="font-mono">{{ $mobileDetails['paybill'] ?? $mobileDetails['number'] ?? '—' }}</p>
                        </div>
                    @endif
                    <label class="block">
                        <span class="text-xs font-medium text-gray-600">{{ __('borrower.membership.mobile_number') }}</span>
                        <input type="text" name="mobile_number" value="{{ old('mobile_number', $customer->phone) }}" class="mt-1 w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm" placeholder="255712345678">
                    </label>
                </div>

                <div x-show="channel === 'bank'" x-cloak class="space-y-3">
                    @foreach ($bankAccounts as $account)
                        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4 text-sm">
                            <p class="font-semibold">{{ $account['bank_name'] ?? $account['bank'] ?? 'Bank' }}</p>
                            <p class="font-mono text-xs mt-1">{{ $account['account_number'] ?? '—' }}</p>
                            <p class="text-xs text-gray-600 mt-1">{{ $account['account_name'] ?? '' }}</p>
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

                @if (($feeQuote['wallet_allowed'] ?? false) && $wallet->balance > 0 && ($maxWalletQuote['wallet_usable'] ?? 0) > 0)
                    <label class="flex items-start gap-3 rounded-xl bg-indigo-50 ring-1 ring-indigo-200 px-4 py-3 text-sm cursor-pointer">
                        <input type="checkbox" name="use_wallet" value="1" class="mt-1 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                        <span>{{ __('borrower.post_approval_fees.use_wallet', ['amount' => format_money(min($wallet->balance, $maxWalletQuote['wallet_usable'] ?? 0))]) }}</span>
                    </label>
                @endif

                @if ($errors->any())
                    <div class="rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-full text-sm w-full sm:w-auto">
                    {{ __('borrower.post_approval_fees.pay_now') }}
                </button>
            </form>
        @else
            <p class="text-sm text-emerald-700 font-semibold">{{ __('borrower.post_approval_fees.all_paid') }}</p>
        @endif
    </div>
</x-site.borrower-layout>
