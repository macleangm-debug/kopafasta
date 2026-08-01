@php
    $category = app(\App\Services\RecoveryPolicyService::class)->vendorCategoryForType($partnerType);
@endphp

<x-admin.layout :title="$label" :heading="$label" :subheading="'Recovery partners · portal login, SLA, performance, and commission'">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.recovery.partners.index') }}" class="text-sm font-semibold text-brand hover:text-brand-light">← All recovery partners</a>
        <a href="{{ route('admin.partners.create', ['category' => $category]) }}"
           class="inline-flex bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2 rounded-lg text-sm">
            + New partner
        </a>
    </div>

    @livewire('admin.recovery-partners-table', ['partnerType' => $partnerType])
</x-admin.layout>
