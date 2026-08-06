<x-site.borrower-layout :title="brand_title(__('borrower.payments_page.create.title'))" active="loans">

    <div class="mb-5 max-w-xl mx-auto">
        <a href="{{ $selectedLoan ? route('site.borrower.loans.show', $selectedLoan) : route('site.borrower.loans') }}"
           class="text-sm font-semibold text-brand hover:underline">
            ← {{ __('borrower.loans_page.view_loan') }}
        </a>
    </div>

    @if ($loans->isEmpty())
        <div class="max-w-xl mx-auto">
            <x-site.empty-state
                icon="◎"
                :title="__('borrower.payments_page.create.no_loans_title')"
                :description="__('borrower.payments_page.create.no_loans_desc')"
                :action-label="__('borrower.payments_page.create.no_loans_action')"
                :action-url="route('site.borrower.loans')"
            />
        </div>
    @else
        @php
            $paymentThreshold = payment_mobile_money_threshold();
            $accounts = app(\App\Services\PaymentAccountService::class);
            $bankAccounts = $accounts->bankAccountsForDisplay('loan_repayment', $paymentReference, $selectedLoan?->product);
            $initialAmount = (int) old('amount', $suggestedAmount ?? 0);
            $loanPayOptions = $loans->mapWithKeys(fn ($loan) => [
                $loan->id => $loan->loan_number.' — '.__('borrower.payments_page.create.outstanding_suffix', [
                    'balance' => format_money($loan->outstanding_balance),
                ]),
            ])->all();
        @endphp

        <div class="max-w-xl mx-auto space-y-5">
            <div>
                <p class="text-xs uppercase tracking-widest text-brand mb-1">{{ __('borrower.nav.payments') }}</p>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('borrower.payments_page.create.title') }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ __('borrower.payments_page.create.subtitle') }}</p>
            </div>

            <form method="POST" action="{{ route('site.borrower.payments.store') }}" enctype="multipart/form-data"
                  class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 p-5 sm:p-6 space-y-5"
                  x-data="{
                      amount: {{ $initialAmount }},
                      paying: false,
                      threshold: {{ (int) $paymentThreshold }},
                      get mobileAllowed() { return !this.threshold || this.amount <= this.threshold; },
                  }"
                  @submit="paying = true">
                @csrf

                <div class="rounded-3xl overflow-hidden bg-gradient-to-br from-brand via-brand to-brand-light text-white shadow-lg shadow-brand/20 -mx-1">
                    <div class="px-6 py-7">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-white/70 font-semibold">{{ __('borrower.payments_page.create.amount_label') }}</p>
                        <p class="mt-3 text-4xl font-extrabold tabular-nums tracking-tight">
                            <span x-text="Number(amount || 0).toLocaleString()">{{ format_number($initialAmount) }}</span>
                            <span class="text-base font-semibold text-white/70">TZS</span>
                        </p>
                        @if ($suggestedAmount)
                            <p class="mt-3 text-xs text-white/75">{{ __('borrower.payments_page.create.suggested_hint', ['amount' => format_money((float) $suggestedAmount)]) }}</p>
                        @endif
                        <p class="mt-4 text-xs text-white/70">{{ __('borrower.membership.payment_reference_label') }}</p>
                        <p class="mt-1 font-mono text-sm bg-white/15 inline-block px-3 py-1.5 rounded-lg">{{ $paymentReference }}</p>
                    </div>
                </div>

                @if ($loans->count() > 1)
                    <x-site.profile-select
                        name="loan_id"
                        :label="__('borrower.payments_page.create.loan_label')"
                        :options="$loanPayOptions"
                        :value="$selectedLoan?->id"
                        :required="true"
                        select-class="w-full rounded-xl border-gray-200 text-sm"
                    />
                @else
                    <input type="hidden" name="loan_id" value="{{ $selectedLoan->id }}">
                @endif

                <div>
                    <x-site.numeric-input
                        name="amount"
                        :label="__('borrower.payments_page.create.amount_label')"
                        :value="old('amount', $suggestedAmount ?? 0)"
                        :required="true"
                        min="100"
                        step="100"
                        x-model.number="amount"
                    />
                </div>

                <x-site.payment-method-picker
                    :amount="$initialAmount ?: 100"
                    method-field="payment_method"
                    mobile-field="mobile_number"
                    mobile-value="mobile_money"
                    bank-value="bank_transfer"
                    :default-method="old('payment_method', 'mobile_money')"
                    :mobile-details="[]"
                    :bank-accounts="$bankAccounts"
                    :bank-reference="$paymentReference"
                    :mobile-input-value="old('mobile_number', $customer->phone ?? '')"
                    :country-code="$customer->country_code ?? 'TZ'"
                >
                    <div class="space-y-3">
                        <x-site.date-input
                            name="payment_date"
                            :label="__('borrower.payments_page.create.payment_date')"
                            :max="now()->toDateString()"
                            input-class="w-full rounded-xl border-gray-200 text-sm"
                        />
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-2">{{ __('borrower.payments_page.create.upload_proof') }}</label>
                            <x-site.single-image-document-upload
                                name="proof"
                                facing="environment"
                                :required="false"
                                :labels="[
                                    'uploadImage' => __('borrower.payments_page.create.upload_proof'),
                                    'captureImage' => __('borrower.profile.capture_image'),
                                ]"
                            />
                            <p class="text-[11px] text-gray-400 mt-1.5">{{ __('borrower.payments_page.create.upload_proof_hint') }}</p>
                            @error('proof')
                                <p class="text-sm text-rose-700 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </x-site.payment-method-picker>

                @error('payment_method')
                    <p class="text-sm text-rose-700">{{ $message }}</p>
                @enderror
                @error('mobile_number')
                    <p class="text-sm text-rose-700">{{ $message }}</p>
                @enderror
                @error('amount')
                    <p class="text-sm text-rose-700">{{ $message }}</p>
                @enderror

                <p class="text-xs text-gray-500 leading-relaxed">
                    {{ __('borrower.payments_page.create.guidance_bank') }}
                </p>

                <button type="submit" :disabled="paying"
                        class="w-full bg-brand hover:bg-brand-light text-white font-semibold px-5 py-3.5 rounded-xl text-sm shadow-sm disabled:opacity-70">
                    <span x-show="!paying">{{ __('borrower.payments_page.create.submit') }}</span>
                    <span x-cloak x-show="paying">{{ __('borrower.membership.paying') }}</span>
                </button>
            </form>
        </div>
    @endif

</x-site.borrower-layout>
