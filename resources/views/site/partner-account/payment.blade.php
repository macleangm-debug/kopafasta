@props([
    'partner',
    'portal',
    'profileRoute',
    'updateRoute',
    'layoutComponent',
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'accountTabs' => [],
])

@php
    $meta = $partner->metadata ?? [];
    $payout = is_array($meta['payout_account'] ?? null) ? $meta['payout_account'] : [];
    $lockedName = app(\App\Services\PartnerProfileService::class)->payoutAccountName($partner);
    $hasPayout = ! empty($payout);
    $openOnLoad = $errors->any() || ! $hasPayout;
@endphp

<x-dynamic-component :component="$layoutComponent" :title="brand_title($title)" active="profile">

    <x-site.partner-account-tabs active="profile" :tabs="$accountTabs" />

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    @include('site.partner-account._shell', [
        'partner' => $partner,
        'portal' => $portal,
        'active' => 'payment',
        'profileRoute' => $profileRoute,
    ])

    <div class="glass-card overflow-hidden" x-data="{
        expanded: @js($openOnLoad),
        adding: @js($openOnLoad),
        complete: @js($hasPayout),
        step: @js(old('payout_type', $payout['type'] ?? '') !== '' ? 2 : 1),
        type: @js(old('payout_type', $payout['type'] ?? '')),
        mobileProvider: @js(old('payout_mobile_provider', $payout['mobile_provider'] ?? '')),
        mobileNumber: @js(old('payout_mobile_number', $payout['mobile_number'] ?? $partner->phone)),
        bankName: @js(old('payout_bank_name', $payout['bank_name'] ?? '')),
        accountNumber: @js(old('payout_account_number', $payout['account_number'] ?? '')),
        get showCompleteTick() { return this.complete && ! this.adding && ! this.expanded; },
        toggleExpand() { this.expanded = ! this.expanded; },
        openAdd() { this.adding = true; this.expanded = true; if (!this.type) this.step = 1; }
    }">
        <div class="px-5 sm:px-6 py-4 border-b border-gray-100/80 flex flex-wrap items-start justify-between gap-3 cursor-pointer"
             role="button"
             tabindex="0"
             @click="toggleExpand()"
             @keydown.enter.prevent="toggleExpand()"
             @keydown.space.prevent="toggleExpand()">
            <div class="flex items-start gap-3 min-w-0 flex-1">
                <span class="text-2xl leading-none shrink-0 mt-0.5" aria-hidden="true">💳</span>
                <div>
                    <h2 class="font-semibold text-gray-900 inline-flex items-center gap-2">
                        <span>{{ __('site.partner_account.payment_section') }}</span>
                        <svg class="size-4 text-gray-400 transition" :class="expanded ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('site.partner_account.payment_hint') }}</p>
                </div>
            </div>
            <div class="shrink-0 flex items-center justify-end min-h-9">
                @if ($hasPayout)
                    <span x-show="showCompleteTick"
                          @if ($openOnLoad) x-cloak @endif
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
                @endif
                <button type="button" @click.stop="openAdd()"
                        x-show="!showCompleteTick"
                        @unless ($openOnLoad) x-cloak @endunless
                        class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand bg-brand-gold hover:bg-yellow-400 px-3.5 py-1.5 rounded-full shadow-sm">
                    <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    {{ $hasPayout ? __('borrower.payment_details.add_account') : __('borrower.profile.add_details') }}
                </button>
            </div>
        </div>

        <div x-show="!expanded" @if ($openOnLoad) x-cloak @endif class="px-5 sm:px-6 py-3">
            <button type="button" @click.stop="expanded = true" class="text-xs font-semibold text-brand hover:underline">
                {{ $hasPayout ? __('borrower.profile.hub.view') : __('borrower.profile.hub.view_edit') }} →
            </button>
        </div>

        <div x-show="expanded" @unless ($openOnLoad) x-cloak @endunless class="p-5 sm:p-6" @click.stop>
            @if (! $hasPayout)
                <p class="text-sm text-gray-600">{{ __('site.partner_account.payment_empty') }}</p>
            @else
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    @foreach ([
                        ['label' => __('borrower.payment_details.account_type'), 'value' => filled($payout['type'] ?? null) ? str_replace('_', ' ', $payout['type']) : null],
                        ['label' => __('borrower.payment_details.account_name'), 'value' => $payout['account_name'] ?? $lockedName],
                        ['label' => __('borrower.payment_details.provider'), 'value' => $payout['mobile_provider'] ?? null],
                        ['label' => __('borrower.payment_details.phone_number'), 'value' => $payout['mobile_number'] ?? null],
                        ['label' => __('borrower.payment_details.bank_name'), 'value' => $payout['bank_name'] ?? null],
                        ['label' => __('borrower.payment_details.account_number'), 'value' => $payout['account_number'] ?? null],
                        ['label' => __('borrower.payment_details.branch'), 'value' => $payout['bank_branch'] ?? ($payout['branch'] ?? null)],
                    ] as $row)
                        @if (filled($row['value']))
                            <div>
                                <dt class="text-xs text-gray-500">{{ $row['label'] }}</dt>
                                <dd class="font-semibold text-gray-900 mt-0.5 capitalize">{{ $row['value'] }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
                <button type="button" @click.stop="openAdd()" class="mt-4 inline-flex items-center rounded-full bg-brand-gold hover:bg-yellow-400 text-brand px-3 py-1.5 text-xs font-bold shadow-sm">
                    {{ __('borrower.payment_details.edit_account') }}
                </button>
            @endif
        </div>

        <x-site.action-panel :title="__('borrower.payment_details.add_account')" open="adding" size="lg">
            <p class="text-[10px] uppercase tracking-widest text-gray-400 font-bold mb-4">
                <span x-show="step === 1">{{ __('borrower.payment_details.step_of', ['current' => 1, 'total' => 3]) }}</span>
                <span x-show="step === 2" x-cloak>{{ __('borrower.payment_details.step_of', ['current' => 2, 'total' => 3]) }}</span>
                <span x-show="step === 3" x-cloak>{{ __('borrower.payment_details.step_of', ['current' => 3, 'total' => 3]) }}</span>
            </p>
            <form method="POST" action="{{ route($updateRoute, ['section' => 'payment']) }}" class="space-y-4"
                  @submit="if (!type || step < 3) { $event.preventDefault(); if (!type) step = 1; }">
                @csrf @method('PUT')
                <input type="hidden" name="payout_type" :value="type">
                <input type="hidden" name="payout_account_name" value="{{ $lockedName }}">
                <input type="hidden" name="payout_mobile_provider" :value="mobileProvider">
                <input type="hidden" name="payout_bank_name" :value="bankName">
                <input type="hidden" name="payout_account_number" :value="accountNumber">

                <div x-show="step === 1" class="space-y-4">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">{{ __('borrower.payment_details.choose_type_title') }}</p>
                    <div class="grid gap-3">
                        <button type="button" @click="type = 'mobile_money'; step = 2"
                                class="rounded-2xl ring-2 px-4 py-5 text-left transition"
                                :class="type === 'mobile_money' ? 'ring-brand bg-brand-muted/40' : 'ring-gray-200 bg-white hover:ring-brand/40'">
                            <span class="text-2xl" aria-hidden="true">📱</span>
                            <p class="text-sm font-bold text-gray-900 mt-2">{{ __('borrower.payment_details.method_mobile') }}</p>
                        </button>
                        <button type="button" @click="type = 'bank'; step = 2"
                                class="rounded-2xl ring-2 px-4 py-5 text-left transition"
                                :class="type === 'bank' ? 'ring-brand bg-brand-muted/40' : 'ring-gray-200 bg-white hover:ring-brand/40'">
                            <span class="text-2xl" aria-hidden="true">🏦</span>
                            <p class="text-sm font-bold text-gray-900 mt-2">{{ __('borrower.payment_details.method_bank') }}</p>
                        </button>
                    </div>
                </div>

                <div x-show="step === 2" x-cloak class="space-y-4">
                    <button type="button" @click="step = 1" class="text-sm font-semibold text-gray-600">← {{ __('borrower.apply.back') }}</button>
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3">
                        <p class="text-xs text-gray-500">{{ __('borrower.payment_details.account_name') }}</p>
                        <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $lockedName }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ __('site.partner_account.payout_name_locked') }}</p>
                    </div>
                    <div x-show="type === 'mobile_money'" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.payment_details.provider') }}</label>
                            <div class="grid gap-2">
                                @foreach (\App\Services\CustomerDisbursementDetailsService::MOBILE_PROVIDERS as $key => $label)
                                    <label class="inline-flex items-center gap-2 cursor-pointer text-sm rounded-xl ring-1 ring-gray-200 px-3 py-2.5 hover:bg-gray-50 has-[:checked]:ring-brand has-[:checked]:bg-brand-muted/30">
                                        <input type="radio" value="{{ $key }}" x-model="mobileProvider" class="text-amber-600">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <x-site.phone-input
                                name="payout_mobile_number"
                                :label="__('borrower.payment_details.phone_number')"
                                :value="old('payout_mobile_number', $payout['mobile_number'] ?? $partner->phone)"
                                :required="false"
                                variant="rounded"
                                :help="__('borrower.payment_details.mobile_prefix_hint')"
                            />
                        </div>
                    </div>
                    <div x-show="type === 'bank'" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.payment_details.bank_name') }}</label>
                            <input type="text" x-model="bankName" class="w-full h-12 rounded-xl bg-white border border-gray-300 px-3.5 text-base outline-none focus:border-gray-900 focus:ring-4 focus:ring-gray-900/10">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.payment_details.account_number') }}</label>
                            <input type="text" x-model="accountNumber" class="w-full h-12 rounded-xl bg-white border border-gray-300 px-3.5 text-base outline-none focus:border-gray-900 focus:ring-4 focus:ring-gray-900/10">
                        </div>
                    </div>
                    <button type="button" @click="mobileNumber = ($root.querySelector('input[name=payout_mobile_number]')?.value || mobileNumber); step = 3"
                            class="w-full rounded-xl bg-brand-gold hover:bg-yellow-400 text-brand text-sm font-bold px-5 py-3">
                        {{ __('borrower.payment_details.review_continue') }}
                    </button>
                </div>

                <div x-show="step === 3" x-cloak class="space-y-4">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">{{ __('borrower.payment_details.review_title') }}</p>
                    <div class="rounded-2xl bg-brand/5 ring-1 ring-brand/15 px-4 py-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-500">{{ __('borrower.payment_details.account_type') }}</span>
                            <span class="font-semibold" x-text="type === 'bank' ? @js(__('borrower.payment_details.method_bank')) : @js(__('borrower.payment_details.method_mobile'))"></span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-500">{{ __('borrower.payment_details.account_name') }}</span>
                            <span class="font-semibold">{{ $lockedName }}</span>
                        </div>
                        <div class="flex justify-between gap-3" x-show="type === 'mobile_money'">
                            <span class="text-gray-500">{{ __('borrower.payment_details.phone_number') }}</span>
                            <span class="font-semibold" x-text="mobileNumber"></span>
                        </div>
                        <div class="flex justify-between gap-3" x-show="type === 'bank'">
                            <span class="text-gray-500">{{ __('borrower.payment_details.account_number') }}</span>
                            <span class="font-semibold" x-text="accountNumber"></span>
                        </div>
                    </div>
                    <div class="flex justify-between gap-3">
                        <button type="button" @click="step = 2" class="text-sm font-semibold text-gray-600">← {{ __('borrower.apply.back') }}</button>
                        <button type="submit" class="rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-5 py-3">
                            {{ __('site.partner_account.save_payment') }}
                        </button>
                    </div>
                </div>
            </form>
        </x-site.action-panel>
    </div>

</x-dynamic-component>
