<x-site.borrower-layout :title="brand_title(__('borrower.payments_page.create.title'))" active="payments" content-width="wide">

    <div class="mb-6">
        <a href="{{ route('site.borrower.payments') }}" class="text-sm font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.payments_page.back_history') }}</a>
    </div>

    <h1 class="text-2xl font-bold mb-1">{{ __('borrower.payments_page.create.title') }}</h1>
    <p class="text-sm text-gray-500 mb-6">{{ __('borrower.payments_page.create.subtitle') }}</p>

    @if ($loans->isEmpty())
        <x-site.empty-state
            icon="💳"
            :title="__('borrower.payments_page.create.no_loans_title')"
            :description="__('borrower.payments_page.create.no_loans_desc')"
            :action-label="__('borrower.payments_page.create.no_loans_action')"
            :action-url="route('site.borrower.loans')"
        />
    @else
        @php
            $paymentThreshold = config('payments.mobile_money_threshold', 3_000_000);
            $accounts = app(\App\Services\PaymentAccountService::class);
            $bankResolved = $accounts->resolve('loan_repayment', 'bank_transfer', $selectedLoan?->product);
            $mobileResolved = $accounts->resolve('loan_repayment', 'mobile_money', $selectedLoan?->product);
            $bankDetails = $accounts->bankTransferDetails($bankResolved['bank_account'], 'PAY-XXXXXX');
            $mobileDetails = $accounts->mobileMoneyDetails($mobileResolved['mobile_money_account']);
            $bankOnlyMessage = __('borrower.payments_page.create.bank_only', ['threshold' => format_money($paymentThreshold)]);
        @endphp

        <div class="grid lg:grid-cols-3 gap-6" x-data="{
            amount: {{ (int) old('amount', 0) }},
            method: @js(old('payment_method', 'mobile_money')),
            threshold: {{ $paymentThreshold }},
            get mobileAllowed() { return this.amount <= this.threshold; },
        }">
            <form method="POST" action="{{ route('site.borrower.payments.store') }}"
                  enctype="multipart/form-data"
                  class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-6 space-y-5"
                  @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.payments_page.create.submit_confirm_title')), message: @js(__('borrower.payments_page.create.submit_confirm_message')), confirmLabel: @js(__('borrower.payments_page.create.submit_confirm_label')), confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900' })">
                @csrf

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.payments_page.create.loan_label') }}</label>
                    <select name="loan_id" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm">
                        @foreach ($loans as $loan)
                            <option value="{{ $loan->id }}" @selected(($selectedLoan?->id ?? null) === $loan->id)>
                                {{ $loan->loan_number }} — {{ __('borrower.payments_page.create.outstanding_suffix', ['balance' => format_money($loan->outstanding_balance)]) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.payments_page.create.amount_label') }}</label>
                    <input type="number" name="amount" min="100" step="100" required x-model.number="amount"
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm">
                    <p class="text-xs mt-2" :class="mobileAllowed ? 'text-emerald-700' : 'text-amber-700'"
                       x-text="mobileAllowed ? @js(__('borrower.payments_page.create.mobile_allowed')) : @js($bankOnlyMessage)"></p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-2">{{ __('borrower.payments_page.create.method_label') }}</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="cursor-pointer" x-show="mobileAllowed">
                            <input type="radio" name="payment_method" value="mobile_money" class="sr-only peer" x-model="method" required>
                            <div class="rounded-xl border-2 border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 px-3 py-3 text-center text-xs font-medium transition">
                                {{ __('borrower.payments_page.create.mobile_money') }}
                            </div>
                        </label>
                        <label class="cursor-pointer" :class="!mobileAllowed && 'col-span-2'">
                            <input type="radio" name="payment_method" value="bank_transfer" class="sr-only peer" x-model="method" required>
                            <div class="rounded-xl border-2 border-gray-200 peer-checked:border-amber-500 peer-checked:bg-amber-50 px-3 py-3 text-center text-xs font-medium transition">
                                {{ __('borrower.payments_page.create.bank_transfer') }}
                            </div>
                        </label>
                    </div>
                </div>

                <div x-show="method === 'mobile_money'" x-cloak>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.payments_page.create.mobile_number_label') }}</label>
                    <input type="text" name="mobile_number" placeholder="255712345678"
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm font-mono">
                    <p class="text-xs text-gray-500 mt-1">{{ __('borrower.payments_page.create.mobile_number_hint') }}</p>
                    @error('mobile_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    @if ($mobileDetails['number'])
                        <div class="mt-3 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-xs text-sky-900">
                            <p class="font-semibold">{{ __('borrower.payments_page.create.pay_to', ['provider' => $mobileDetails['provider'] ?? __('borrower.payments_page.create.mobile_money')]) }}</p>
                            <p class="font-mono mt-1">{{ $mobileDetails['number'] }}</p>
                            <p class="mt-2 text-sky-800">{{ __('borrower.payments_page.create.reference_hint') }}</p>
                        </div>
                    @endif
                </div>

                <div x-show="method === 'bank_transfer'" x-cloak class="space-y-4">
                    <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-4 text-sm text-sky-900">
                        <p class="text-xs uppercase tracking-widest text-sky-700 mb-2">{{ __('borrower.payments_page.create.bank_details_heading') }}</p>
                        <dl class="space-y-2 text-xs">
                            <div class="flex justify-between gap-4"><dt class="text-sky-700">{{ __('borrower.payments_page.create.bank_label') }}</dt><dd class="font-medium">{{ $bankDetails['bank_name'] }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-sky-700">{{ __('borrower.payments_page.create.account_name') }}</dt><dd class="font-medium">{{ $bankDetails['account_name'] }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-sky-700">{{ __('borrower.payments_page.create.account_number') }}</dt><dd class="font-mono font-medium">{{ $bankDetails['account_number'] }}</dd></div>
                        </dl>
                        <p class="text-xs text-sky-800 mt-3">{{ __('borrower.payments_page.create.reference_hint') }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.payments_page.create.payment_date') }}</label>
                        <input type="date" name="payment_date" max="{{ now()->toDateString() }}"
                               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2.5 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.payments_page.create.upload_proof') }}</label>
                        <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf"
                               class="w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-amber-900">
                        <p class="text-[11px] text-gray-400 mt-1">{{ __('borrower.payments_page.create.upload_proof_hint') }}</p>
                    </div>
                </div>

                <button type="submit"
                        class="w-full sm:w-auto bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-full text-sm">
                    {{ __('borrower.payments_page.create.submit') }}
                </button>
            </form>

            <div class="space-y-3">
                <h2 class="text-sm font-semibold text-gray-700">{{ __('borrower.payments_page.create.guidance_heading') }}</h2>
                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-xs text-amber-900">
                    {{ __('borrower.payments_page.create.guidance_mobile_limit', ['threshold' => format_money($paymentThreshold)]) }}
                </div>
                <div class="bg-white rounded-2xl border border-gray-200 p-4 text-xs text-gray-600 space-y-2">
                    <p>{{ __('borrower.payments_page.create.guidance_bank') }}</p>
                    <p>{{ __('borrower.payments_page.create.guidance_mobile_format') }}</p>
                </div>
            </div>
        </div>
    @endif

</x-site.borrower-layout>
