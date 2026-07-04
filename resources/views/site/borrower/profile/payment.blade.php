<x-site.borrower-layout :title="brand_title(__('borrower.payment_details.page_title'))" active="profile" content-width="wide">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.account_title'),
            'subtitle' => __('borrower.payment_details.subtitle'),
            'customer' => $customer,
            'active' => 'payment',
            'wizardMode' => $wizardMode ?? false,
            'wizardKey' => $wizardKey ?? 'payment',
        ])

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @php
            $editing = ($wizardMode ?? false) || ($editing ?? false);
            $paymentComplete = ($paymentAccounts ?? collect())->isNotEmpty();
            $providers = \App\Services\CustomerDisbursementDetailsService::MOBILE_PROVIDERS;
            $addType = old('type', 'mobile_money');
        @endphp

        <x-site.profile-section-card
            :title="__('borrower.payment_details.section_title')"
            :editing="false"
            :complete="$paymentComplete">
            @if (($paymentAccounts ?? collect())->isNotEmpty())
                <div class="space-y-3 mb-6">
                    @foreach ($paymentAccounts as $account)
                        <div class="rounded-xl ring-1 ring-gray-200 px-4 py-3 flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $detailsService->accountLabel($account) }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $account->account_name }}</p>
                                @if ($account->is_default)
                                    <span class="inline-flex mt-2 text-[10px] uppercase tracking-widest font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">{{ __('borrower.payment_details.default_account') }}</span>
                                @endif
                            </div>
                            @if (! $account->is_default || ($paymentAccounts ?? collect())->count() > 1)
                                <form method="POST" action="{{ route('site.borrower.profile.payment-accounts.destroy', $account) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}"
                                      onsubmit="return confirm(@js(__('borrower.payment_details.remove_confirm')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-red-700 hover:text-red-800">{{ __('borrower.payment_details.remove') }}</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-600 mb-4">{{ __('borrower.payment_details.incomplete_hint') }}</p>
            @endif

            @if ($editing)
                <div class="border-t border-gray-100 pt-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.add_account') }}</h3>
                    <p class="text-xs text-gray-500 mb-4">{{ __('borrower.payment_details.name_must_match', ['name' => $borrowerLegalName ?? $customer->full_name]) }}</p>

                    <form method="POST"
                          action="{{ route('site.borrower.profile.update', ['section' => 'payment']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}">
                        @csrf @method('PUT')
                        @if ($wizardMode ?? false)
                            <input type="hidden" name="wizard" value="1">
                        @endif
                        @if (! empty($returnUrl))
                            <input type="hidden" name="return" value="{{ $returnUrl }}">
                        @endif

                        <div x-data="{ type: @js($addType) }">
                            <fieldset class="mb-4">
                                <legend class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">{{ __('borrower.payment_details.account_type') }}</legend>
                                <div class="flex flex-wrap gap-4 text-sm">
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="type" value="mobile_money" x-model="type" class="text-amber-600">
                                        <span>{{ __('borrower.payment_details.method_mobile') }}</span>
                                    </label>
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="type" value="bank" x-model="type" class="text-amber-600">
                                        <span>{{ __('borrower.payment_details.method_bank') }}</span>
                                    </label>
                                </div>
                            </fieldset>

                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.account_name') }}</label>
                                <input type="text" name="account_name" value="{{ old('account_name', $borrowerLegalName ?? '') }}" class="w-full rounded-lg border-gray-300 text-sm">
                            </div>

                            <div x-show="type === 'mobile_money'" x-cloak class="space-y-4 border-t border-gray-100 pt-4">
                                <fieldset>
                                    <legend class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">{{ __('borrower.payment_details.provider') }}</legend>
                                    <div class="grid sm:grid-cols-2 gap-3">
                                        @foreach ($providers as $key => $label)
                                            <label class="inline-flex items-center gap-2 cursor-pointer text-sm">
                                                <input type="radio" name="mobile_provider" value="{{ $key }}" @checked(old('mobile_provider') === $key) class="text-amber-600" :disabled="type !== 'mobile_money'">
                                                <span>{{ $label }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.phone_number') }}</label>
                                    <input type="text" name="mobile_number" value="{{ old('mobile_number') }}" placeholder="0712345678 or 255712345678" class="w-full rounded-lg border-gray-300 text-sm" :disabled="type !== 'mobile_money'">
                                    <p class="text-xs text-gray-500 mt-1">{{ __('borrower.payment_details.mobile_prefix_hint') }}</p>
                                </div>
                            </div>

                            <div x-show="type === 'bank'" x-cloak class="space-y-4 border-t border-gray-100 pt-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.bank_name') }}</label>
                                    <input type="text" name="bank_name" value="{{ old('bank_name') }}" class="w-full rounded-lg border-gray-300 text-sm" :disabled="type !== 'bank'">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.account_number') }}</label>
                                    <input type="text" name="account_number" value="{{ old('account_number') }}" class="w-full rounded-lg border-gray-300 text-sm" :disabled="type !== 'bank'">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.branch') }} <span class="text-gray-400 font-normal">({{ __('borrower.payment_details.optional') }})</span></label>
                                    <input type="text" name="bank_branch" value="{{ old('bank_branch') }}" class="w-full rounded-lg border-gray-300 text-sm" :disabled="type !== 'bank'">
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-2.5 rounded-full text-sm">
                                {{ __('borrower.payment_details.save_account') }}
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <a href="{{ route('site.borrower.profile', ['section' => 'payment', 'edit' => 1, 'return' => $returnUrl ?? null]) }}"
                   class="inline-flex items-center text-sm font-semibold text-amber-700 hover:text-amber-800">
                    {{ __('borrower.payment_details.manage_accounts') }} &rarr;
                </a>
            @endif
        </x-site.profile-section-card>
    </div>
</x-site.borrower-layout>
