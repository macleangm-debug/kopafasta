<x-site.borrower-layout :title="brand_title(__('borrower.payment_details.section_title'))" active="profile" content-width="wide">

    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.payment_details.section_title'),
            'subtitle' => null,
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
            // Complete + idle → collapsed. Incomplete / validation / explicit add|edit → expanded.
            // After successful save/update (?open=1) stay on the expanded list with the panel closed.
            $forceListOpen = request()->boolean('open');
            $startExpanded = $showAdd || $forceListOpen;
            $startAdding = $showAdd && ! $forceListOpen;
            $focusAccountId = (int) request()->query('account', 0);
            $legalName = $borrowerLegalName ?? trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
            $mobileAccounts = $accounts->where('type', 'mobile_money')->values();
            $bankAccounts = $accounts->where('type', 'bank')->values();
            $returnQuery = ! empty($returnUrl) ? ['return' => $returnUrl] : [];
        @endphp

        <div class="glass-card overflow-hidden" x-data="paymentProfileCard(@js([
            'expanded' => $startExpanded,
            'complete' => $paymentComplete,
            'showEditAction' => $startAdding,
            'adding' => $startAdding,
            'editingId' => (int) old('account_id', 0),
            'step' => $addType !== '' ? 2 : 1,
            'type' => $addType,
            'mobileProvider' => old('mobile_provider', ''),
            'mobileNumber' => old('mobile_number', ''),
            'bankName' => old('bank_name', ''),
            'accountNumber' => old('account_number', ''),
            'bankBranch' => old('bank_branch', ''),
            'editTitle' => __('borrower.payment_details.edit_account_title'),
            'addTitle' => __('borrower.payment_details.add_account'),
            'focusAccountId' => $focusAccountId,
        ]))">
            <div class="px-5 sm:px-6 py-4 border-b border-gray-100/80 flex flex-wrap items-start justify-between gap-3 cursor-pointer"
                 role="button"
                 tabindex="0"
                 @click="toggleExpand()"
                 @keydown.enter.prevent="toggleExpand()"
                 @keydown.space.prevent="toggleExpand()">
                <div class="flex items-start gap-3 min-w-0 text-left flex-1">
                    <span class="text-2xl leading-none shrink-0 mt-0.5" aria-hidden="true">💳</span>
                    <div class="min-w-0">
                        <h2 class="font-semibold text-gray-900 inline-flex items-center gap-2">
                            <span>{{ __('borrower.payment_details.section_title') }}</span>
                            <svg class="size-4 text-gray-400 transition" :class="expanded ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </h2>
                    </div>
                </div>
                <div class="shrink-0 relative min-h-9 flex items-center justify-end gap-2">
                    @if ($paymentComplete)
                        <span
                                x-show="showCompleteTick"
                                @if ($startExpanded) x-cloak @endif
                                class="inline-flex items-center gap-2 rounded-full bg-gradient-to-br from-brand to-brand-light pl-1.5 pr-3 py-1.5 text-brand-gold shadow-sm shadow-brand/25 ring-2 ring-brand-gold/40 pointer-events-none"
                                title="{{ __('borrower.profile.section_complete') }}"
                                aria-label="{{ __('borrower.profile.section_complete') }}">
                            <span class="grid size-7 place-items-center rounded-full bg-white/15 ring-1 ring-white/25">
                                <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                            <span class="text-[11px] font-bold text-white/90">{{ __('borrower.profile.section_complete') }}</span>
                        </span>
                        <button type="button"
                                @click.stop="openAdd()"
                                x-show="!showCompleteTick"
                                @unless ($startExpanded) x-cloak @endunless
                                class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand bg-brand-gold hover:bg-yellow-400 px-3.5 py-1.5 rounded-full shadow-sm">
                            <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            {{ __('borrower.payment_details.add_account') }}
                        </button>
                    @else
                        <button type="button"
                                @click.stop="openAdd()"
                                class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand bg-brand-gold hover:bg-yellow-400 px-3.5 py-1.5 rounded-full shadow-sm">
                            <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                            {{ __('borrower.profile.add_details') }}
                        </button>
                    @endif
                </div>
            </div>

            <div x-show="!expanded" @if ($startExpanded) x-cloak @endif class="px-5 sm:px-6 py-3">
                <button type="button" @click.stop="expanded = true" class="text-xs font-semibold text-brand hover:underline">
                    {{ $paymentComplete ? __('borrower.profile.hub.view') : __('borrower.profile.hub.view_edit') }} →
                </button>
            </div>

            <div x-show="expanded" @unless ($startExpanded) x-cloak @endunless class="p-5 sm:p-6 space-y-6" @click.stop>
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
                                        <div id="payment-account-{{ $account->id }}"
                                             data-payment-account-row="{{ $account->id }}"
                                             @class([
                                            'rounded-2xl px-4 py-3 space-y-3',
                                            'bg-emerald-50 ring-2 ring-emerald-500/40 shadow-sm shadow-emerald-900/5' => $account->is_default,
                                            'bg-white ring-1 ring-gray-200' => ! $account->is_default,
                                        ])>
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div class="min-w-0 flex-1">
                                                    <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                                                        @foreach ([
                                                            ['label' => __('borrower.payment_details.account_type'), 'value' => $account->type === 'bank' ? __('borrower.payment_details.method_bank') : __('borrower.payment_details.method_mobile')],
                                                            ['label' => __('borrower.payment_details.account_name'), 'value' => $account->account_name],
                                                            ['label' => __('borrower.payment_details.provider'), 'value' => $account->mobile_provider],
                                                            ['label' => __('borrower.payment_details.phone_number'), 'value' => $account->mobile_number],
                                                            ['label' => __('borrower.payment_details.bank_name'), 'value' => $account->bank_name],
                                                            ['label' => __('borrower.payment_details.account_number'), 'value' => $account->account_number],
                                                            ['label' => __('borrower.payment_details.branch'), 'value' => $account->bank_branch],
                                                        ] as $row)
                                                            @if (filled($row['value']))
                                                                <div>
                                                                    <dt class="text-xs text-gray-500">{{ $row['label'] }}</dt>
                                                                    <dd class="font-medium text-gray-900 mt-0.5">{{ $row['value'] }}</dd>
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    </dl>
                                                    @if ($account->is_default)
                                                        <span class="inline-flex mt-3 items-center gap-1 text-[10px] uppercase tracking-widest font-bold text-white bg-emerald-600 px-2.5 py-1 rounded-full shadow-sm">
                                                            <svg class="size-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                                            {{ __('borrower.payment_details.default_account') }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0 flex-wrap">
                                                <button type="button"
                                                        data-payment-account-edit="{{ $account->id }}"
                                                        @click.stop="openEdit(@js([
                                                            'id' => $account->id,
                                                            'type' => $account->type,
                                                            'mobile_provider' => $account->mobile_provider,
                                                            'mobile_number' => $account->mobile_number,
                                                            'bank_name' => $account->bank_name,
                                                            'account_number' => $account->account_number,
                                                            'bank_branch' => $account->bank_branch,
                                                        ]))"
                                                        class="inline-flex items-center rounded-full bg-brand-gold hover:bg-yellow-400 text-brand px-3 py-1.5 text-xs font-bold shadow-sm">
                                                    {{ __('borrower.payment_details.edit_account') }}
                                                </button>
                                                @unless ($account->is_default)
                                                    <form method="POST" action="{{ route('site.borrower.profile.payment-accounts.default', $account) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}" @click.stop>
                                                        @csrf
                                                        <button type="submit" class="text-xs font-semibold text-amber-700 hover:text-amber-800">{{ __('borrower.payment_details.set_default') }}</button>
                                                    </form>
                                                @endunless
                                                @if (! $account->is_default || $accounts->count() > 1)
                                                    <form method="POST" action="{{ route('site.borrower.profile.payment-accounts.destroy', $account) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}"
                                                          @click.stop
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
            </div>

            {{-- Outside x-show so teleport/hydration never blanks the account list --}}
            <x-site.action-panel :title="__('borrower.payment_details.add_account')" open="adding" size="lg">
                    <p class="text-sm font-bold text-gray-900 mb-2" x-text="panelTitle"></p>
                    <p class="text-xs text-gray-500 mb-4">{{ __('borrower.payment_details.name_must_match', ['name' => $legalName]) }}</p>
                    <p class="text-[10px] uppercase tracking-widest text-gray-400 font-bold mb-4">
                        <span x-show="step === 1">{{ __('borrower.payment_details.step_of', ['current' => 1, 'total' => 3]) }}</span>
                        <span x-show="step === 2" x-cloak>{{ __('borrower.payment_details.step_of', ['current' => 2, 'total' => 3]) }}</span>
                        <span x-show="step === 3" x-cloak>{{ __('borrower.payment_details.step_of', ['current' => 3, 'total' => 3]) }}</span>
                    </p>

                    <form method="POST"
                          action="{{ route('site.borrower.profile.update', ['section' => 'payment']) }}{{ ($wizardMode ?? false) ? '?wizard=1' : '' }}{{ ! empty($returnUrl) ? (($wizardMode ?? false) ? '&' : '?').'return='.urlencode($returnUrl) : '' }}"
                          @submit="if (!type || step < 3) { $event.preventDefault(); if (!type) step = 1; }">
                        @csrf @method('PUT')
                        @if ($wizardMode ?? false)
                            <input type="hidden" name="wizard" value="1">
                        @endif
                        @if (! empty($returnUrl))
                            <input type="hidden" name="return" value="{{ $returnUrl }}">
                        @endif
                        <input type="hidden" name="account_id" :value="editingId || ''">
                        <input type="hidden" name="type" :value="type">
                        <input type="hidden" name="account_name" value="{{ old('account_name', $legalName) }}">
                        <input type="hidden" name="mobile_provider" :value="mobileProvider">
                        <input type="hidden" name="bank_name" :value="bankName">
                        <input type="hidden" name="account_number" :value="accountNumber">
                        <input type="hidden" name="bank_branch" :value="bankBranch">

                        <div x-show="step === 1" class="space-y-4">
                            <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">{{ __('borrower.payment_details.choose_type_title') }}</p>
                            <div class="grid gap-3">
                                <button type="button"
                                        @click="type = 'mobile_money'; step = 2"
                                        class="rounded-2xl ring-2 px-5 py-5 text-left transition"
                                        :class="type === 'mobile_money' ? 'ring-brand bg-brand-muted/40' : 'ring-gray-200 bg-gradient-to-br from-white to-gray-50 hover:ring-brand/40'">
                                    <span class="text-2xl" aria-hidden="true">📱</span>
                                    <p class="text-base font-bold text-gray-900 mt-2">{{ __('borrower.payment_details.method_mobile') }}</p>
                                    <p class="text-sm text-gray-500 mt-1">{{ __('borrower.payment_details.choose_mobile_hint') }}</p>
                                </button>
                                <button type="button"
                                        @click="type = 'bank'; step = 2"
                                        class="rounded-2xl ring-2 px-5 py-5 text-left transition"
                                        :class="type === 'bank' ? 'ring-brand bg-brand-muted/40' : 'ring-gray-200 bg-gradient-to-br from-white to-gray-50 hover:ring-brand/40'">
                                    <span class="text-2xl" aria-hidden="true">🏦</span>
                                    <p class="text-base font-bold text-gray-900 mt-2">{{ __('borrower.payment_details.method_bank') }}</p>
                                    <p class="text-sm text-gray-500 mt-1">{{ __('borrower.payment_details.choose_bank_hint') }}</p>
                                </button>
                            </div>
                        </div>

                        <div x-show="step === 2" x-cloak class="space-y-5">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-gray-900"
                                   x-text="type === 'bank' ? @js(__('borrower.payment_details.bank_section')) : @js(__('borrower.payment_details.mobile_section'))"></p>
                                <button type="button" @click="step = 1" class="text-xs font-semibold text-gray-600 hover:text-gray-900">
                                    {{ __('borrower.payment_details.change_type') }}
                                </button>
                            </div>

                            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3">
                                <p class="text-xs text-gray-500">{{ __('borrower.payment_details.account_name') }}</p>
                                <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $legalName }}</p>
                            </div>

                            <div x-show="type === 'mobile_money'" class="space-y-4">
                                <fieldset>
                                    <legend class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">{{ __('borrower.payment_details.provider') }} <span class="text-red-500">*</span></legend>
                                    <div class="grid gap-2">
                                        @foreach ($providers as $key => $label)
                                            <label class="inline-flex items-center gap-2 cursor-pointer text-sm rounded-xl ring-1 ring-gray-200 px-3 py-2.5 hover:bg-gray-50 has-[:checked]:ring-brand has-[:checked]:bg-brand-muted/30">
                                                <input type="radio" value="{{ $key }}" x-model="mobileProvider"
                                                       class="text-amber-600"
                                                       x-bind:required="type === 'mobile_money' && step === 2">
                                                <span>{{ $label }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('mobile_provider')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </fieldset>
                                <div>
                                    <x-site.phone-input
                                        name="mobile_number"
                                        :label="__('borrower.payment_details.phone_number')"
                                        :value="old('mobile_number')"
                                        :required="false"
                                        variant="rounded"
                                        :help="__('borrower.payment_details.mobile_prefix_hint')"
                                    />
                                </div>
                            </div>

                            <div x-show="type === 'bank'" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.bank_name') }} <span class="text-red-500">*</span></label>
                                    <input type="text" x-model="bankName" placeholder="{{ __('borrower.payment_details.bank_name_placeholder') }}" autocomplete="off" class="kf-field"
                                           x-bind:required="type === 'bank' && step === 2">
                                    @error('bank_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.account_number') }} <span class="text-red-500">*</span></label>
                                    <input type="text" x-model="accountNumber" placeholder="{{ __('borrower.payment_details.account_number_placeholder') }}" autocomplete="off" class="kf-field"
                                           x-bind:required="type === 'bank' && step === 2">
                                    @error('account_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.payment_details.branch') }} <span class="text-gray-400 font-normal">({{ __('borrower.payment_details.optional') }})</span></label>
                                    <input type="text" x-model="bankBranch" placeholder="{{ __('borrower.payment_details.branch_placeholder') }}" autocomplete="off" class="kf-field">
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 pt-2">
                                <button type="button" @click="syncMobileNumber(); step = 3"
                                        class="bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-2.5 rounded-full text-sm">
                                    {{ __('borrower.payment_details.review_continue') }}
                                </button>
                            </div>
                        </div>

                        <div x-show="step === 3" x-cloak class="space-y-4">
                            <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">{{ __('borrower.payment_details.review_title') }}</p>
                            <div class="rounded-2xl bg-brand/5 ring-1 ring-brand/15 px-4 py-4 space-y-2 text-sm">
                                <div class="flex justify-between gap-3">
                                    <span class="text-gray-500">{{ __('borrower.payment_details.account_type') }}</span>
                                    <span class="font-semibold text-gray-900" x-text="type === 'bank' ? @js(__('borrower.payment_details.method_bank')) : @js(__('borrower.payment_details.method_mobile'))"></span>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <span class="text-gray-500">{{ __('borrower.payment_details.account_name') }}</span>
                                    <span class="font-semibold text-gray-900">{{ $legalName }}</span>
                                </div>
                                <template x-if="type === 'mobile_money'">
                                    <div class="space-y-2">
                                        <div class="flex justify-between gap-3">
                                            <span class="text-gray-500">{{ __('borrower.payment_details.provider') }}</span>
                                            <span class="font-semibold text-gray-900" x-text="mobileProvider"></span>
                                        </div>
                                        <div class="flex justify-between gap-3">
                                            <span class="text-gray-500">{{ __('borrower.payment_details.phone_number') }}</span>
                                            <span class="font-semibold text-gray-900" x-text="mobileNumber"></span>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="type === 'bank'">
                                    <div class="space-y-2">
                                        <div class="flex justify-between gap-3">
                                            <span class="text-gray-500">{{ __('borrower.payment_details.bank_name') }}</span>
                                            <span class="font-semibold text-gray-900" x-text="bankName"></span>
                                        </div>
                                        <div class="flex justify-between gap-3">
                                            <span class="text-gray-500">{{ __('borrower.payment_details.account_number') }}</span>
                                            <span class="font-semibold text-gray-900" x-text="accountNumber"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            @if ($paymentComplete)
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" name="is_default" value="1" @checked(old('is_default')) class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                    <span>{{ __('borrower.payment_details.make_default') }}</span>
                                </label>
                            @endif

                            <div class="flex flex-wrap justify-between gap-3 pt-2">
                                <button type="button" @click="step = 2" class="text-sm font-semibold text-gray-600 hover:text-gray-900">
                                    ← {{ __('borrower.apply.back') }}
                                </button>
                                {{-- Review has no visible [required] fields; do not gate this terminal CTA with kfGatedSubmit. --}}
                                <button type="submit"
                                        data-payment-account-terminal-cta
                                        class="bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-2.5 rounded-full text-sm"
                                        x-text="editingId
                                            ? @js(__('borrower.payment_details.update_account'))
                                            : @js(__('borrower.payment_details.save_account'))">
                                    {{ __('borrower.payment_details.save_account') }}
                                </button>
                            </div>
                        </div>
                    </form>
            </x-site.action-panel>
        </div>
    </div>
</x-site.borrower-layout>
