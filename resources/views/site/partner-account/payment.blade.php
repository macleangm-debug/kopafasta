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
        adding: @js($openOnLoad),
        step: @js(old('payout_type', $payout['type'] ?? '') !== '' ? 2 : 1),
        type: @js(old('payout_type', $payout['type'] ?? '')),
        mobileProvider: @js(old('payout_mobile_provider', $payout['mobile_provider'] ?? '')),
        mobileNumber: @js(old('payout_mobile_number', $payout['mobile_number'] ?? $partner->phone)),
        bankName: @js(old('payout_bank_name', $payout['bank_name'] ?? '')),
        accountNumber: @js(old('payout_account_number', $payout['account_number'] ?? '')),
        openAdd() { this.adding = true; if (!this.type) this.step = 1; }
    }">
        <div class="px-5 sm:px-6 py-4 border-b border-gray-100/80 flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3 min-w-0">
                <span class="text-2xl leading-none shrink-0 mt-0.5" aria-hidden="true">💳</span>
                <div>
                    <h2 class="font-semibold text-gray-900">{{ __('site.partner_account.payment_section') }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('site.partner_account.payment_hint') }}</p>
                </div>
            </div>
            <button type="button" @click="openAdd()"
                    class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand bg-brand-gold hover:bg-yellow-400 px-3.5 py-1.5 rounded-full shadow-sm">
                <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ $hasPayout ? __('borrower.payment_details.add_account') : __('borrower.profile.add_details') }}
            </button>
        </div>

        <div class="p-5 sm:p-6">
            @if (! $hasPayout)
                <p class="text-sm text-gray-600">{{ __('site.partner_account.payment_empty') }}</p>
            @else
                <p class="text-sm font-semibold text-gray-900 capitalize">{{ str_replace('_', ' ', $payout['type'] ?? '') }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ $payout['account_name'] ?? '' }}</p>
                <p class="text-sm font-mono text-gray-800 mt-1">{{ $payout['mobile_number'] ?? $payout['account_number'] ?? '' }}</p>
            @endif
        </div>

        <x-site.action-panel :title="__('borrower.payment_details.add_account')" open="adding" size="lg">
            <p class="text-[10px] uppercase tracking-widest text-gray-400 font-bold mb-4">
                {{ __('borrower.payment_details.review_title') }} · <span x-text="step"></span>/3
            </p>
            <form method="POST" action="{{ route($updateRoute, ['section' => 'payment']) }}" class="space-y-4"
                  @submit="if (!type || step < 3) { $event.preventDefault(); if (!type) step = 1; }">
                @csrf @method('PUT')
                <input type="hidden" name="payout_type" :value="type">
                <input type="hidden" name="payout_account_name" value="{{ $lockedName }}">
                <input type="hidden" name="payout_mobile_provider" :value="mobileProvider">
                <input type="hidden" name="payout_mobile_number" :value="mobileNumber">
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
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.payment_details.phone_number') }}</label>
                            <input type="text" x-model="mobileNumber" class="w-full h-12 rounded-xl bg-white border border-gray-300 px-3.5 text-base outline-none focus:border-gray-900 focus:ring-4 focus:ring-gray-900/10">
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
                    <button type="button" @click="step = 3"
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
