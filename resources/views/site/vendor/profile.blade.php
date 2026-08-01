@php
    $accountTabs = [
        ['key' => 'profile', 'label' => __('site.partner_account.tab_profile'), 'url' => route('site.partner.profile')],
        ['key' => 'documents', 'label' => __('site.partner_account.tab_documents'), 'url' => route('site.partner.documents')],
        ['key' => 'settings', 'label' => __('site.partner_account.tab_settings'), 'url' => route('site.partner.settings')],
    ];
@endphp

<x-site.vendor-layout :title="__('site.partner_account.profile_title')" active="profile">
    <x-site.borrower-page-header
        :eyebrow="ucfirst(str_replace('_', ' ', $vendor->category ?? 'partner'))"
        :title="__('site.partner_account.profile_title')"
        :subtitle="__('site.partner_account.profile_subtitle')"
    />

    <x-site.partner-account-tabs active="profile" :tabs="$accountTabs" />

    @include('site.partner-account._profile', [
        'partner' => $vendor,
        'updateRoute' => route('site.partner.profile.update'),
    ])

    @if ($vendor->isAffiliate())
        <div class="mt-6 glass-card rounded-2xl ring-1 ring-brand/10 p-5 sm:p-6">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Affiliate KYC</p>
            <p class="text-sm text-gray-600 mt-1 mb-4">Status: {{ ucfirst($vendor->affiliate_kyc_status ?? 'pending') }}</p>
            <form method="POST" action="{{ route('site.partner.profile.update') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf @method('PUT')
                <input type="hidden" name="name" value="{{ $vendor->name }}">
                <div class="grid sm:grid-cols-3 gap-3">
                    <label class="block text-xs font-semibold text-brand">Selfie<input type="file" name="affiliate_selfie" accept="image/*" class="mt-1 w-full text-xs"></label>
                    <label class="block text-xs font-semibold text-brand">National ID<input type="file" name="affiliate_id" accept="image/*" class="mt-1 w-full text-xs"></label>
                    <label class="block text-xs font-semibold text-brand">Photo<input type="file" name="affiliate_photo" accept="image/*" class="mt-1 w-full text-xs"></label>
                </div>
                <button class="rounded-xl bg-brand-gold hover:brightness-95 text-brand text-sm font-bold px-5 py-2.5">Upload KYC</button>
            </form>
        </div>
    @endif
</x-site.vendor-layout>
