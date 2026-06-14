@php
    $category = app(\App\Services\RecoveryPolicyService::class)->vendorCategoryForType($partnerType);
@endphp

<x-admin.layout :title="$label" :heading="$label" :subheading="'Recovery partners · portal login, SLA, performance, and commission'">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.recovery.partners.index') }}" class="text-sm font-semibold text-amber-700 hover:text-amber-800">← All recovery partners</a>
        <a href="{{ route('admin.vendors.create', ['category' => $category]) }}"
           class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-lg text-sm">
            + New {{ strtolower($label) }}
        </a>
    </div>

    @livewire('admin.recovery-partners-table', ['partnerType' => $partnerType])
</x-admin.layout>
