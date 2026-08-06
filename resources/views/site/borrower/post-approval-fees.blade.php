<x-site.borrower-layout :title="brand_title(__('borrower.post_approval_fees.page_title'))" active="loans" content-width="wide">
    <div>
        <div class="mb-6">
            <a href="{{ route('site.borrower.application', $application) }}" class="text-sm text-brand hover:text-brand-light">&larr; {{ __('borrower.post_approval_fees.back') }}</a>
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

                <x-site.promo-code-toggle
                    name="promo_code"
                    :value="old('promo_code')"
                    :quote="$feeQuote"
                    :inline="true"
                />

                <x-site.payment-method-picker
                    :amount="(int) ($feeQuote['cash_due'] ?? $feeQuote['after_discount'] ?? 0)"
                    method-field="channel"
                    mobile-field="mobile_number"
                    mobile-value="mobile_money"
                    bank-value="bank"
                    :default-method="old('channel', 'mobile_money')"
                    :mobile-details="$mobileDetails ?? []"
                    :bank-accounts="collect($bankAccounts ?? [])->map(fn ($a) => [
                        'bank' => $a['bank_name'] ?? $a['bank'] ?? 'Bank',
                        'account_name' => $a['account_name'] ?? '',
                        'account_number' => $a['account_number'] ?? '',
                        'branch' => $a['branch'] ?? '',
                    ])->all()"
                    :bank-reference="$paymentReference"
                    :mobile-input-value="old('mobile_number', $customer->phone)"
                    :country-code="$customer->country_code ?? 'TZ'"
                >
                    <div x-show="method === 'bank'" x-cloak class="space-y-3">
                        <x-site.date-input
                            name="payment_date"
                            :label="__('borrower.membership.payment_date')"
                            :value="old('payment_date', now()->toDateString())"
                            :max="now()->toDateString()"
                            input-class="mt-1 w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-base"
                        />
                        <label class="block">
                            <span class="text-xs font-medium text-gray-600">{{ __('borrower.membership.proof_optional') }}</span>
                            <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 w-full text-sm">
                        </label>
                    </div>
                </x-site.payment-method-picker>

                @if (($feeQuote['wallet_allowed'] ?? false) && $wallet->balance > 0 && ($maxWalletQuote['wallet_usable'] ?? 0) > 0)
                    <label class="flex items-start gap-3 rounded-xl bg-indigo-50 ring-1 ring-indigo-200 px-4 py-3 text-sm cursor-pointer">
                        <input type="checkbox" name="use_wallet" value="1" class="mt-1 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                        <span>{{ __('borrower.post_approval_fees.use_wallet', ['amount' => format_money(min($wallet->balance, $maxWalletQuote['wallet_usable'] ?? 0))]) }}</span>
                    </label>
                @endif

                <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-3 rounded-xl text-sm w-full sm:w-auto">
                    {{ __('borrower.post_approval_fees.pay_now') }}
                </button>
            </form>
        @else
            <p class="text-sm text-emerald-700 font-semibold">{{ __('borrower.post_approval_fees.all_paid') }}</p>
        @endif
    </div>
</x-site.borrower-layout>
