<x-site.borrower-layout :title="brand_title(__('borrower.payments_page.create.title'))" active="payments" content-width="wide">

    <div class="mb-5">
        <a href="{{ route('site.borrower.payments') }}" class="text-sm font-semibold text-brand hover:underline">{{ __('borrower.payments_page.back_history') }}</a>
    </div>

    @if ($loans->isEmpty())
        <x-site.borrower-page-header
            :title="__('borrower.payments_page.create.title')"
            :subtitle="__('borrower.payments_page.create.subtitle')"
        />
        <x-site.empty-state
            icon="◎"
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
            $initialAmount = (int) old('amount', $suggestedAmount ?? 0);
        @endphp

        <div class="mb-6">
            <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand">{{ __('borrower.nav.payments') }}</p>
            <h1 class="mt-1 text-2xl sm:text-3xl font-bold tracking-tight text-gray-900">{{ __('borrower.payments_page.create.title') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('borrower.payments_page.create.subtitle') }}</p>
        </div>

        <form id="repayment-form" method="POST" action="{{ route('site.borrower.payments.store') }}"
              enctype="multipart/form-data"
              class="max-w-2xl"
              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.payments_page.create.submit_confirm_title')), message: @js(__('borrower.payments_page.create.submit_confirm_message')), confirmLabel: @js(__('borrower.payments_page.create.submit_confirm_label')), confirmClass: 'bg-brand hover:bg-brand-light text-white' })"
              x-data="{
                  amount: {{ $initialAmount }},
                  method: @js(old('payment_method', 'mobile_money')),
                  threshold: {{ (int) $paymentThreshold }},
                  get mobileAllowed() { return this.amount <= this.threshold; },
              }">
            @csrf

            <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand via-brand to-brand/90 text-white shadow-lg shadow-brand/20 mb-6">
                <div class="absolute inset-0 opacity-[0.14]" style="background-image: radial-gradient(circle at 20% 20%, #fff 0, transparent 45%), radial-gradient(circle at 85% 0%, #fbbf24 0, transparent 35%);"></div>
                <div class="relative px-5 sm:px-7 py-6 sm:py-7">
                    <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('borrower.payments_page.create.amount_label') }}</p>
                    <p class="mt-1 text-3xl sm:text-4xl font-extrabold tabular-nums tracking-tight">
                        <span x-text="amount > 0 ? amount.toLocaleString() : '0'"></span>
                        <span class="text-base font-semibold text-white/65">TZS</span>
                    </p>
                    @if ($suggestedAmount)
                        <p class="mt-2 text-xs text-white/75">{{ __('borrower.payments_page.create.suggested_hint', ['amount' => format_money((float) $suggestedAmount)]) }}</p>
                    @endif
                </div>
            </section>

            <div class="space-y-5">
                <div class="rounded-2xl bg-brand-muted/30 ring-1 ring-brand/10 px-5 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.payments_page.create.guidance_heading') }}</p>
                    <p class="mt-2 text-xs text-gray-600 leading-relaxed">
                        {{ __('borrower.payments_page.create.guidance_mobile_limit', ['threshold' => format_money($paymentThreshold)]) }}
                    </p>
                </div>
                <div>
                    @php
                        $loanPayOptions = $loans->mapWithKeys(fn ($loan) => [
                            $loan->id => $loan->loan_number.' — '.__('borrower.payments_page.create.outstanding_suffix', ['balance' => format_money($loan->outstanding_balance)]),
                        ])->all();
                    @endphp
                    <x-site.profile-select
                        name="loan_id"
                        :label="__('borrower.payments_page.create.loan_label')"
                        :options="$loanPayOptions"
                        :value="$selectedLoan?->id"
                        :required="true"
                        select-class="w-full rounded-xl border-0 bg-white ring-1 ring-brand/15 focus:ring-2 focus:ring-brand px-4 py-3 text-sm"
                    />
                </div>

                <div>
                    <x-site.numeric-input name="amount" :label="__('borrower.payments_page.create.amount_label')" :value="old('amount', $suggestedAmount ?? 0)" :required="true" min="100" step="100" x-model.number="amount" />
                    <p class="text-xs mt-2" :class="mobileAllowed ? 'text-emerald-700' : 'text-amber-700'"
                       x-text="mobileAllowed ? @js(__('borrower.payments_page.create.mobile_allowed')) : @js(__('borrower.payments_page.create.bank_only', ['threshold' => format_money($paymentThreshold)]))"></p>
                </div>

                <div>
                    <label class="block text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-2">{{ __('borrower.payments_page.create.method_label') }}</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="cursor-pointer" x-show="mobileAllowed">
                            <input type="radio" name="payment_method" value="mobile_money" class="sr-only peer" x-model="method" required>
                            <div class="rounded-2xl ring-1 ring-brand/15 peer-checked:ring-2 peer-checked:ring-brand peer-checked:bg-brand-muted/40 px-4 py-4 text-center text-sm font-semibold text-gray-800 transition">
                                {{ __('borrower.payments_page.create.mobile_money') }}
                            </div>
                        </label>
                        <label class="cursor-pointer" :class="!mobileAllowed && 'col-span-2'">
                            <input type="radio" name="payment_method" value="bank_transfer" class="sr-only peer" x-model="method" required>
                            <div class="rounded-2xl ring-1 ring-brand/15 peer-checked:ring-2 peer-checked:ring-brand peer-checked:bg-brand-muted/40 px-4 py-4 text-center text-sm font-semibold text-gray-800 transition">
                                {{ __('borrower.payments_page.create.bank_transfer') }}
                            </div>
                        </label>
                    </div>
                </div>

                <div x-show="method === 'mobile_money'" x-cloak class="space-y-4">
                    @if ($mobileDetails['number'] ?? null)
                        <div class="rounded-2xl bg-gradient-to-b from-sky-50 to-white ring-1 ring-sky-200/80 px-5 py-4 text-sm text-sky-950">
                            <p class="text-[10px] uppercase tracking-widest text-sky-700 font-semibold">{{ __('borrower.payments_page.create.pay_to', ['provider' => $mobileDetails['provider'] ?? __('borrower.payments_page.create.mobile_money')]) }}</p>
                            <p class="font-mono text-lg font-bold mt-1">{{ $mobileDetails['number'] }}</p>
                            <p class="mt-2 text-xs text-sky-800">{{ __('borrower.payments_page.create.reference_hint') }}</p>
                        </div>
                    @endif
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-2">{{ __('borrower.payments_page.create.mobile_number_label') }}</label>
                        <input type="text" name="mobile_number" value="{{ old('mobile_number', $customer->phone) }}" placeholder="255712345678"
                               class="w-full rounded-xl border-0 bg-white ring-1 ring-brand/15 focus:ring-2 focus:ring-brand px-4 py-3 text-sm font-mono">
                        <p class="text-xs text-gray-500 mt-1.5">{{ __('borrower.payments_page.create.mobile_number_hint') }}</p>
                        @error('mobile_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div x-show="method === 'bank_transfer'" x-cloak class="space-y-4">
                    <div class="rounded-2xl bg-gradient-to-b from-sky-50 to-white ring-1 ring-sky-200/80 px-5 py-5 text-sm text-sky-950">
                        <p class="text-[10px] uppercase tracking-widest text-sky-700 font-semibold mb-3">{{ __('borrower.payments_page.create.bank_details_heading') }}</p>
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-sky-700">{{ __('borrower.payments_page.create.bank_label') }}</dt><dd class="font-semibold">{{ $bankDetails['bank_name'] }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-sky-700">{{ __('borrower.payments_page.create.account_name') }}</dt><dd class="font-semibold">{{ $bankDetails['account_name'] }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-sky-700">{{ __('borrower.payments_page.create.account_number') }}</dt><dd class="font-mono font-semibold">{{ $bankDetails['account_number'] }}</dd></div>
                        </dl>
                        <p class="text-xs text-sky-800 mt-4">{{ __('borrower.payments_page.create.reference_hint') }}</p>
                    </div>

                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-2">{{ __('borrower.payments_page.create.payment_date') }}</label>
                        <input type="date" name="payment_date" max="{{ now()->toDateString() }}"
                               class="w-full rounded-xl border-0 bg-white ring-1 ring-brand/15 focus:ring-2 focus:ring-brand px-4 py-3 text-sm">
                    </div>

                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-2">{{ __('borrower.payments_page.create.upload_proof') }}</label>
                        <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf"
                               class="w-full text-sm text-gray-600 file:mr-3 file:rounded-xl file:border-0 file:bg-brand-muted file:px-4 file:py-2.5 file:text-xs file:font-semibold file:text-brand">
                        <p class="text-[11px] text-gray-400 mt-1.5">{{ __('borrower.payments_page.create.upload_proof_hint') }}</p>
                    </div>
                </div>

                <p class="text-xs text-gray-500 leading-relaxed">
                    {{ __('borrower.payments_page.create.guidance_bank') }}
                    {{ __('borrower.payments_page.create.guidance_mobile_format') }}
                </p>

                <button type="submit"
                        class="w-full sm:w-auto bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-8 py-3.5 rounded-full text-sm shadow-sm transition">
                    {{ __('borrower.payments_page.create.submit') }}
                </button>
            </div>
        </form>
    @endif

</x-site.borrower-layout>
