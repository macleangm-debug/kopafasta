@php
    $partner = $partner ?? $vendor;
    $profile = app(\App\Services\PartnerProfileService::class);
    $types = $profile->documentTypesFor($partner);
    unset($types['other']);
    $uploaded = $documents;
    $providedTypes = $uploaded->pluck('doc_type')->filter()->all();
    $requiredMissing = collect($types)
        ->reject(fn ($label, $key) => in_array($key, $providedTypes, true))
        ->all();
@endphp

<x-site.supplier-layout :title="__('site.supplier_portal.documents_title')" active="profile">
    <x-site.borrower-page-header
        :eyebrow="__('site.supplier_portal.title')"
        :title="__('site.supplier_portal.documents_title')"
        :subtitle="__('site.supplier_portal.documents_subtitle')"
    />

    @include('site.partner-account._tabs', [
        'active' => 'documents',
        'partner' => $partner,
        'profileRoute' => 'site.supplier.profile',
        'portal' => 'supplier',
    ])

    <div class="grid sm:grid-cols-3 gap-3 mb-6">
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('site.supplier_portal.docs_required') }}</p>
            <p class="text-2xl font-extrabold text-brand tabular-nums mt-1">{{ count($requiredMissing) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('site.supplier_portal.docs_provided') }}</p>
            <p class="text-2xl font-extrabold text-brand tabular-nums mt-1">{{ $uploaded->count() }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-amber-200 bg-amber-50/60 px-4 py-3">
            <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">{{ __('site.supplier_portal.docs_attention') }}</p>
            <p class="text-2xl font-extrabold text-amber-800 tabular-nums mt-1">{{ count($requiredMissing) }}</p>
        </div>
    </div>

    @include('site.partner-account._documents', [
        'documents' => $documents,
        'uploadRoute' => route('site.supplier.documents.store'),
        'documentTypes' => $profile->documentTypesFor($partner),
    ])
</x-site.supplier-layout>
