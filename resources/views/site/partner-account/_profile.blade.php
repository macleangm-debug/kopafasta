@props([
    'partner',
    'updateRoute',
    'showBusinessMeta' => true,
])

@php
    $p = $partner;
    $payout = $p->metadata['payout_account'] ?? [];
@endphp

@if (session('status'))
    <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
@endif

<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <x-site.profile-section-card
            :title="__('site.partner_account.contact_details')"
            :complete="filled($p->name) && filled($p->phone)"
            :collapsible="true">
            <x-slot:view>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('site.partner_account.display_name') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $p->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('site.partner_account.phone') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $p->phone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('site.partner_account.email') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $p->email ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('site.partner_account.address') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $p->address ?: '—' }}</dd>
                    </div>
                </dl>
            </x-slot:view>
            <x-slot:form>
                <form method="POST" action="{{ $updateRoute }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.display_name') }}</label>
                        <input name="name" value="{{ old('name', $p->name) }}" required
                               class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand focus:border-brand">
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <x-site.phone-input name="phone" :label="__('site.partner_account.phone')" :value="old('phone', $p->phone)" variant="rounded" />
                        <div>
                            <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.email') }}</label>
                            <input name="email" type="email" value="{{ old('email', $p->email) }}"
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand focus:border-brand">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.address') }}</label>
                        <textarea name="address" rows="3"
                                  class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand focus:border-brand">{{ old('address', $p->address) }}</textarea>
                    </div>
                    <x-site.gated-submit class="rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-5 py-2.5" :label="__('site.partner_account.save_profile')" />
                </form>
            </x-slot:form>
        </x-site.profile-section-card>

        @php
            $residence = $p->metadata['residence'] ?? [];
            $activity = $p->metadata['activity'] ?? [];
        @endphp

        <x-site.profile-section-card
            :title="__('site.partner_account.residence_section')"
            :complete="filled($residence['region'] ?? null) && filled($residence['district'] ?? null)"
            :collapsible="true">
            <x-slot:view>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('site.partner_account.region') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $residence['region'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('site.partner_account.district') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $residence['district'] ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-gray-500">{{ __('site.partner_account.street') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $residence['street'] ?? '—' }}</dd>
                    </div>
                </dl>
            </x-slot:view>
            <x-slot:form>
                <form method="POST" action="{{ $updateRoute }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="name" value="{{ $p->name }}">
                    <div class="grid sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.region') }}</label>
                            <input name="residence_region" value="{{ old('residence_region', $residence['region'] ?? '') }}"
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.district') }}</label>
                            <input name="residence_district" value="{{ old('residence_district', $residence['district'] ?? '') }}"
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.street') }}</label>
                        <input name="residence_street" value="{{ old('residence_street', $residence['street'] ?? '') }}"
                               class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    </div>
                    <x-site.gated-submit class="rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-5 py-2.5" :label="__('site.partner_account.save_profile')" />
                </form>
            </x-slot:form>
        </x-site.profile-section-card>

        <x-site.profile-section-card
            :title="__('site.partner_account.activity_section')"
            :complete="filled($activity['type'] ?? null)"
            :collapsible="true">
            <x-slot:view>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('site.partner_account.activity_type') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $activity['type'] ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-gray-500">{{ __('site.partner_account.activity_details') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5 whitespace-pre-wrap">{{ $activity['details'] ?? '—' }}</dd>
                    </div>
                </dl>
            </x-slot:view>
            <x-slot:form>
                <form method="POST" action="{{ $updateRoute }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="name" value="{{ $p->name }}">
                    <div>
                        <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.activity_type') }}</label>
                        <input name="activity_type" value="{{ old('activity_type', $activity['type'] ?? '') }}"
                               class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm"
                               placeholder="Trading · Services · Agriculture…">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.activity_details') }}</label>
                        <textarea name="activity_details" rows="3"
                                  class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">{{ old('activity_details', $activity['details'] ?? '') }}</textarea>
                    </div>
                    <x-site.gated-submit class="rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-5 py-2.5" :label="__('site.partner_account.save_profile')" />
                </form>
            </x-slot:form>
        </x-site.profile-section-card>

        <x-site.profile-section-card
            :title="__('site.partner_account.payment_section')"
            :complete="! empty($payout)"
            :collapsible="true">
            <x-slot:view>
                @if (empty($payout))
                    <p class="text-sm text-gray-600">{{ __('site.partner_account.payment_empty') }}</p>
                @else
                    <p class="text-sm font-semibold text-gray-900 capitalize">{{ str_replace('_', ' ', $payout['type'] ?? '') }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ $payout['account_name'] ?? '' }}</p>
                    <p class="text-sm font-mono text-gray-800 mt-1">{{ $payout['mobile_number'] ?? $payout['account_number'] ?? '' }}</p>
                @endif
            </x-slot:view>
            <x-slot:form>
                <form method="POST" action="{{ $updateRoute }}" class="space-y-4" x-data="{ type: @js(old('payout_type', $payout['type'] ?? 'mobile_money')) }">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="name" value="{{ $p->name }}">
                    <p class="text-sm text-gray-600">{{ __('site.partner_account.payment_hint') }}</p>
                    <div class="flex flex-wrap gap-3 text-sm">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="payout_type" value="mobile_money" x-model="type" class="text-brand">
                            {{ __('borrower.payment_details.method_mobile') }}
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="payout_type" value="bank" x-model="type" class="text-brand">
                            {{ __('borrower.payment_details.method_bank') }}
                        </label>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.payment_details.account_name') }}</label>
                        <input name="payout_account_name" value="{{ old('payout_account_name', $payout['account_name'] ?? $p->name) }}"
                               class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    </div>
                    <div x-show="type === 'mobile_money'" class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.payment_details.provider') }}</label>
                            <input name="payout_mobile_provider" value="{{ old('payout_mobile_provider', $payout['mobile_provider'] ?? '') }}"
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm" placeholder="M-Pesa / Tigo Pesa / Airtel Money">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.payment_details.phone_number') }}</label>
                            <input name="payout_mobile_number" value="{{ old('payout_mobile_number', $payout['mobile_number'] ?? $p->phone) }}"
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        </div>
                    </div>
                    <div x-show="type === 'bank'" class="grid sm:grid-cols-2 gap-4" x-cloak>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.payment_details.bank_name') }}</label>
                            <input name="payout_bank_name" value="{{ old('payout_bank_name', $payout['bank_name'] ?? '') }}"
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.payment_details.account_number') }}</label>
                            <input name="payout_account_number" value="{{ old('payout_account_number', $payout['account_number'] ?? '') }}"
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        </div>
                    </div>
                    <x-site.gated-submit class="rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-5 py-2.5" :label="__('site.partner_account.save_profile')" />
                </form>
            </x-slot:form>
        </x-site.profile-section-card>
    </div>

    @if ($showBusinessMeta)
        <aside class="space-y-4">
            <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('site.partner_account.partner_code') }}</p>
                <p class="font-mono text-lg font-bold text-gray-900 mt-1">{{ $p->vendor_number ?? $p->partner_number ?? '—' }}</p>

                @if (filled($p->category ?? null))
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold mt-4">{{ __('site.partner_account.category') }}</p>
                    <p class="text-sm font-semibold text-gray-800 mt-1 capitalize">{{ str_replace('_', ' ', $p->category) }}</p>
                @endif

                @if (filled($p->legal_name ?? null))
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold mt-4">{{ __('site.partner_account.legal_name') }}</p>
                    <p class="text-sm text-gray-800 mt-1">{{ $p->legal_name }}</p>
                @endif

                @if (filled($p->registration_number ?? null))
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold mt-4">{{ __('site.partner_account.registration') }}</p>
                    <p class="text-sm font-mono text-gray-800 mt-1">{{ $p->registration_number }}</p>
                @endif

                @if (filled($p->tin ?? null))
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold mt-4">{{ __('site.partner_account.tin') }}</p>
                    <p class="text-sm font-mono text-gray-800 mt-1">{{ $p->tin }}</p>
                @endif

                <p class="text-xs text-gray-500 mt-4">{{ __('site.partner_account.business_meta_hint') }}</p>
            </div>
        </aside>
    @endif
</div>
