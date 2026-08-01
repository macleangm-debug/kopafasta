@props([
    'partner',
    'updateRoute',
    'showBusinessMeta' => true,
])

@php
    $p = $partner;
@endphp

@if (session('status'))
    <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
@endif

<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <x-site.profile-section-card :title="__('site.partner_account.contact_details')" :collapsible="false" :default-open="true">
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
                <button type="submit" class="rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-5 py-2.5">
                    {{ __('site.partner_account.save_profile') }}
                </button>
            </form>
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
