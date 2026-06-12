<x-site.borrower-layout :title="brand_title(__('borrower.payment_details.page_title'))" active="profile">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.title'),
            'subtitle' => __('borrower.payment_details.subtitle'),
            'customer' => $customer,
            'active' => 'payment',
            'wizardMode' => $wizardMode ?? false,
            'wizardKey' => $wizardKey ?? 'payment',
        ])

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        @php
            $editing = ($wizardMode ?? false) || ($editing ?? false);
            $editUrl = route('site.borrower.profile', ['section' => 'payment', 'edit' => 1, 'return' => $returnUrl ?? null]);
            $detailsService = app(\App\Services\CustomerDisbursementDetailsService::class);
            $paymentComplete = $detailsService->isComplete($customer);
            $method = old('preferred_disbursement_method', $customer->preferred_disbursement_method ?? 'mobile_money');
            $providers = \App\Services\CustomerDisbursementDetailsService::MOBILE_PROVIDERS;
        @endphp

        <x-site.profile-section-card
            :title="__('borrower.payment_details.section_title')"
            :editing="$editing"
            :edit-url="$editUrl"
            :complete="$paymentComplete">
            @if ($editing)
                <form method="POST"
                      action="{{ route('site.borrower.profile.update', ['section' => 'payment']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}"
                      @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.profile.save_confirm_title')), message: @js(__('borrower.payment_details.save_confirm')), confirmLabel: @js(__('borrower.profile.save')), confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900' })">
                    @csrf @method('PUT')
                    @if ($wizardMode ?? false)
                        <input type="hidden" name="wizard" value="1">
                    @endif
                    @if (! empty($returnUrl))
                        <input type="hidden" name="return" value="{{ $returnUrl }}">
                    @endif

                    <div x-data="{ method: @js($method) }">
                        <fieldset class="mb-6">
                            <legend class="text-sm font-semibold text-gray-900 mb-3">{{ __('borrower.payment_details.preferred_method') }}</legend>
                            <div class="flex flex-wrap gap-4 text-sm">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="preferred_disbursement_method" value="mobile_money"
                                           x-model="method"
                                           class="text-amber-600 focus:ring-amber-500">
                                    <span>{{ __('borrower.payment_details.method_mobile') }}</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="preferred_disbursement_method" value="bank"
                                           x-model="method"
                                           class="text-amber-600 focus:ring-amber-500">
                                    <span>{{ __('borrower.payment_details.method_bank') }}</span>
                                </label>
                            </div>
                        </fieldset>

                        <div x-show="method === 'mobile_money'" x-cloak class="space-y-4 border-t border-gray-100 pt-6">
                            <p class="text-sm font-semibold text-gray-900">{{ __('borrower.payment_details.mobile_section') }}</p>
                            <fieldset>
                                <legend class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">{{ __('borrower.payment_details.provider') }}</legend>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    @foreach ($providers as $key => $label)
                                        <label class="inline-flex items-center gap-2 cursor-pointer text-sm">
                                            <input type="radio" name="disbursement_mobile_provider" value="{{ $key }}"
                                                   @checked(old('disbursement_mobile_provider', $customer->disbursement_mobile_provider) === $key)
                                                   class="text-amber-600 focus:ring-amber-500">
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <div>
                                <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.phone_number') }}</label>
                                <input type="text" name="disbursement_mobile_number"
                                       value="{{ old('disbursement_mobile_number', $customer->disbursement_mobile_number) }}"
                                       placeholder="255XXXXXXXXX"
                                       class="w-full rounded-lg border-gray-300 text-sm">
                                <p class="text-xs text-gray-500 mt-1">{{ __('borrower.payment_details.phone_hint') }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.account_name') }}</label>
                                <input type="text" name="disbursement_mobile_account_name"
                                       value="{{ old('disbursement_mobile_account_name', $customer->disbursement_mobile_account_name) }}"
                                       class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                        </div>

                        <div x-show="method === 'bank'" x-cloak class="space-y-4 border-t border-gray-100 pt-6">
                            <p class="text-sm font-semibold text-gray-900">{{ __('borrower.payment_details.bank_section') }}</p>
                            <div>
                                <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.bank_name') }}</label>
                                <input type="text" name="disbursement_bank_name"
                                       value="{{ old('disbursement_bank_name', $customer->disbursement_bank_name) }}"
                                       class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.account_name') }}</label>
                                <input type="text" name="disbursement_bank_account_name"
                                       value="{{ old('disbursement_bank_account_name', $customer->disbursement_bank_account_name) }}"
                                       class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.account_number') }}</label>
                                <input type="text" name="disbursement_bank_account_number"
                                       value="{{ old('disbursement_bank_account_number', $customer->disbursement_bank_account_number) }}"
                                       class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.branch') }} <span class="text-gray-400 font-normal">({{ __('borrower.payment_details.optional') }})</span></label>
                                <input type="text" name="disbursement_bank_branch"
                                       value="{{ old('disbursement_bank_branch', $customer->disbursement_bank_branch) }}"
                                       class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-2.5 rounded-full text-sm">
                            {{ __('borrower.profile.save') }}
                        </button>
                    </div>
                </form>
            @else
                @php $snapshot = $detailsService->snapshotFromCustomer($customer); @endphp
                @if ($paymentComplete)
                    <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                        @foreach ($detailsService->displayLines($snapshot) as $label => $value)
                            <div>
                                <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ $label }}</dt>
                                <dd class="font-semibold text-gray-900 mt-1">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="text-sm text-gray-600">{{ __('borrower.payment_details.incomplete_hint') }}</p>
                @endif
            @endif
        </x-site.profile-section-card>
    </div>
</x-site.borrower-layout>
