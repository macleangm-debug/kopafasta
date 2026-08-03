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
        @if ($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @php
            $editing = ($wizardMode ?? false) || ($editing ?? false) || request()->boolean('edit') || request()->boolean('add');
            $accounts = $paymentAccounts ?? collect();
            $paymentComplete = $accounts->isNotEmpty();
            $providers = \App\Services\CustomerDisbursementDetailsService::MOBILE_PROVIDERS;
            $addType = old('type', '');
            $showAdd = $editing || ! $paymentComplete || $errors->any();
            $legalName = $borrowerLegalName ?? trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
            $mobileAccounts = $accounts->where('type', 'mobile_money')->values();
            $bankAccounts = $accounts->where('type', 'bank')->values();
            $returnQuery = ! empty($returnUrl) ? ['return' => $returnUrl] : [];
        @endphp

        <div class="glass-card overflow-hidden" x-data="{ expanded: @js($showAdd || ! $paymentComplete) }">
            <div class="px-5 sm:px-6 py-4 border-b border-gray-100/80 flex flex-wrap items-start justify-between gap-3">
                <button type="button" @click="expanded = !expanded" class="flex items-start gap-3 min-w-0 text-left flex-1">
                    <span class="text-2xl leading-none shrink-0 mt-0.5" aria-hidden="true">💳</span>
                    <div class="min-w-0">
                        <h2 class="font-semibold text-gray-900 inline-flex items-center gap-2">
                            <span>{{ __('borrower.payment_details.section_title') }}</span>
                            <svg class="size-4 text-gray-400 transition" :class="expanded ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </h2>
                    </div>
                </button>
                @if ($paymentComplete && ! $showAdd)
                    <a href="{{ route('site.borrower.profile', array_filter(['section' => 'payment', 'add' => 1] + $returnQuery)) }}"
                       class="shrink-0 inline-flex items-center gap-1.5 text-sm font-semibold text-amber-700 hover:text-amber-800 px-3 py-1.5 rounded-full ring-1 ring-amber-200 bg-amber-50">
                        {{ __('borrower.payment_details.add_account') }}
                    </a>
                @endif
            </div>

            <div x-show="!expanded" x-cloak class="px-5 sm:px-6 py-3">
                <button type="button" @click="expanded = true" class="text-xs font-semibold text-brand hover:underline">
                    {{ __('borrower.profile.hub.view_edit') }} →
                </button>
            </div>

            <div x-show="expanded" class="p-5 sm:p-6 space-y-6">
                @if ($paymentComplete)
                    @foreach ([
                        ['label' => __('borrower.payment_details.method_mobile'), 'items' => $mobileAccounts],
                        ['label' => __('borrower.payment_details.method_bank'), 'items' => $bankAccounts],
                    ] as $group)
                        @if ($group['items']->isNotEmpty())
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">{{ $group['label'] }}</p>
                                <div class="space-y-3">
                                    @foreach ($group['items'] as $account)
                                        <div @class([
                                            'rounded-2xl px-4 py-3 flex flex-wrap items-start justify-between gap-3',
                                            'bg-emerald-50 ring-2 ring-emerald-500/40 shadow-sm shadow-emerald-900/5' => $account->is_default,
                                            'bg-white ring-1 ring-gray-200' => ! $account->is_default,
                                        ])>
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-sm font-semibold text-gray-900">{{ $detailsService->accountLabel($account) }}</p>
                                                    @if ($account->is_default)
                                                        <span class="inline-flex items-center gap-1 text-[10px] uppercase tracking-widest font-bold text-white bg-emerald-600 px-2.5 py-1 rounded-full shadow-sm">
                                                            <svg class="size-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                                            {{ __('borrower.payment_details.default_account') }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <p class="text-xs text-gray-500 mt-0.5">{{ $account->account_name }}</p>
                                            </div>
                                            <div class="flex items-center gap-3 shrink-0">
                                                @unless ($account->is_default)
                                                    <form method="POST" action="{{ route('site.borrower.profile.payment-accounts.default', $account) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}">
                                                        @csrf
                                                        <button type="submit" class="text-xs font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.payment_details.set_default') }}</button>
                                                    </form>
                                                @endunless
                                                @if (! $account->is_default || $accounts->count() > 1)
                                                    <form method="POST" action="{{ route('site.borrower.profile.payment-accounts.destroy', $account) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}"
                                                          @submit.prevent="window.confirmForm($el, {
                                                              title: @js(__('borrower.payment_details.remove_confirm_title')),
                                                              message: @js(__('borrower.payment_details.remove_confirm')),
                                                              confirmLabel: @js(__('borrower.payment_details.remove')),
                                                              confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                                                              tone: 'warning'
                                                          })">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-xs font-semibold text-red-700 hover:text-red-800">{{ __('borrower.payment_details.remove') }}</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                @else
                    <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50/80 px-5 py-8 text-center">
                        <p class="text-sm text-gray-600">{{ __('borrower.payment_details.incomplete_hint') }}</p>
                    </div>
                @endif

                @if ($showAdd)
                    <div class="{{ $paymentComplete ? 'border-t border-gray-100 pt-6' : '' }}"
                         x-data="{ step: @js($addType !== '' ? 2 : 1), type: @js($addType) }">
                        <h3 class="text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.add_account') }}</h3>
                        <p class="text-xs text-gray-500 mb-5">{{ __('borrower.payment_details.name_must_match', ['name' => $legalName]) }}</p>

                        <form method="POST"
                              action="{{ route('site.borrower.profile.update', ['section' => 'payment']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}"
                              @submit="if (!type) { $event.preventDefault(); step = 1; }">
                            @csrf @method('PUT')
                            @if ($wizardMode ?? false)
                                <input type="hidden" name="wizard" value="1">
                            @endif
                            @if (! empty($returnUrl))
                                <input type="hidden" name="return" value="{{ $returnUrl }}">
                            @endif
                            <input type="hidden" name="type" :value="type">
                            <input type="hidden" name="account_name" value="{{ old('account_name', $legalName) }}">

                            {{-- Step 1: choose type --}}
                            <div x-show="step === 1" class="space-y-4">
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">{{ __('borrower.payment_details.choose_type_title') }}</p>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <button type="button"
                                            @click="type = 'mobile_money'; step = 2"
                                            class="rounded-2xl ring-1 ring-gray-200 bg-white hover:ring-amber-300 hover:bg-amber-50/40 px-5 py-5 text-left transition">
                                        <p class="text-sm font-semibold text-gray-900">{{ __('borrower.payment_details.method_mobile') }}</p>
                                        <p class="text-xs text-gray-500 mt-1">{{ __('borrower.payment_details.choose_mobile_hint') }}</p>
                                    </button>
                                    <button type="button"
                                            @click="type = 'bank'; step = 2"
                                            class="rounded-2xl ring-1 ring-gray-200 bg-white hover:ring-amber-300 hover:bg-amber-50/40 px-5 py-5 text-left transition">
                                        <p class="text-sm font-semibold text-gray-900">{{ __('borrower.payment_details.method_bank') }}</p>
                                        <p class="text-xs text-gray-500 mt-1">{{ __('borrower.payment_details.choose_bank_hint') }}</p>
                                    </button>
                                </div>
                            </div>

                            {{-- Step 2: details --}}
                            <div x-show="step === 2" x-cloak class="space-y-5">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold text-gray-900"
                                       x-text="type === 'bank' ? @js(__('borrower.payment_details.bank_section')) : @js(__('borrower.payment_details.mobile_section'))"></p>
                                    <button type="button" @click="step = 1; type = ''" class="text-xs font-semibold text-gray-600 hover:text-gray-900">
                                        {{ __('borrower.payment_details.change_type') }}
                                    </button>
                                </div>

                                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3">
                                    <p class="text-xs text-gray-500">{{ __('borrower.payment_details.account_name') }}</p>
                                    <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $legalName }}</p>
                                </div>

                                <div x-show="type === 'mobile_money'" class="space-y-4">
                                    <fieldset>
                                        <legend class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">{{ __('borrower.payment_details.provider') }}</legend>
                                        <div class="grid sm:grid-cols-2 gap-3">
                                            @foreach ($providers as $key => $label)
                                                <label class="inline-flex items-center gap-2 cursor-pointer text-sm rounded-xl ring-1 ring-gray-200 px-3 py-2.5 hover:bg-gray-50">
                                                    <input type="radio" name="mobile_provider" value="{{ $key }}" @checked(old('mobile_provider') === $key) class="text-amber-600" :disabled="type !== 'mobile_money'">
                                                    <span>{{ $label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @error('mobile_provider')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </fieldset>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.phone_number') }}</label>
                                        <input type="text" name="mobile_number" value="{{ old('mobile_number') }}" placeholder="{{ __('borrower.apply.guarantor_fields.phone_placeholder') }}" autocomplete="off" class="kf-field" :disabled="type !== 'mobile_money'">
                                        <p class="text-xs text-gray-500 mt-1">{{ __('borrower.payment_details.mobile_prefix_hint') }}</p>
                                        @error('mobile_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <div x-show="type === 'bank'" class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.bank_name') }}</label>
                                        <input type="text" name="bank_name" value="{{ old('bank_name') }}" placeholder="{{ __('borrower.payment_details.bank_name_placeholder') }}" autocomplete="off" class="kf-field" :disabled="type !== 'bank'">
                                        @error('bank_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.account_number') }}</label>
                                        <input type="text" name="account_number" value="{{ old('account_number') }}" placeholder="{{ __('borrower.payment_details.account_number_placeholder') }}" autocomplete="off" class="kf-field" :disabled="type !== 'bank'">
                                        @error('account_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.branch') }} <span class="text-gray-400 font-normal">({{ __('borrower.payment_details.optional') }})</span></label>
                                        <input type="text" name="bank_branch" value="{{ old('bank_branch') }}" placeholder="{{ __('borrower.payment_details.branch_placeholder') }}" autocomplete="off" class="kf-field" :disabled="type !== 'bank'">
                                    </div>
                                </div>

                                @if ($paymentComplete)
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="is_default" value="1" @checked(old('is_default')) class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                        <span>{{ __('borrower.payment_details.make_default') }}</span>
                                    </label>
                                @endif

                                <div class="flex flex-wrap justify-end gap-3 pt-2">
                                    @if ($paymentComplete)
                                        <a href="{{ route('site.borrower.profile', array_filter(['section' => 'payment'] + $returnQuery)) }}"
                                           class="inline-flex items-center justify-center px-5 py-2.5 rounded-full text-sm font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50">
                                            {{ __('borrower.profile.cancel_edit') }}
                                        </a>
                                    @endif
                                    <button type="submit"
                                            x-data="kfGatedSubmit()"
                                            x-show="ready"
                                            x-cloak
                                            class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-2.5 rounded-full text-sm">
                                        {{ __('borrower.payment_details.save_account') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-site.borrower-layout>
