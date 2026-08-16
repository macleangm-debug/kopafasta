<x-admin.layout title="Partners" heading="Partners hub" subheading="Unified view of suppliers, affiliates, GPS, insurance, valuers, and other partner roles">
    @php
        $roleOptions = app(\App\Services\PartnerService::class)->roleOptions();
        $activeRole = request('role', '');
    @endphp
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600 max-w-2xl">One partner can hold multiple roles. Use the role chips below instead of separate menus.</p>
        <a href="{{ route('admin.partners.create') }}" class="inline-flex bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2 rounded-lg text-sm">+ New partner</a>
    </div>

    <a href="{{ route('admin.partners.origination-auto-assign') }}"
       class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-5 py-4 hover:ring-brand/30 transition">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Origination</p>
            <p class="text-sm font-bold text-gray-900 mt-0.5">Partner auto-assignment</p>
            <p class="text-xs text-gray-500 mt-1">Valuer, GPS, and insurance — region match after the borrower pays.</p>
        </div>
        <span class="text-sm font-semibold text-brand">Open →</span>
    </a>

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.partners.index') }}"
           class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $activeRole === '' ? 'bg-brand-gold text-brand' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
            All roles
        </a>
        @foreach ($roleOptions as $key => $label)
            <a href="{{ route('admin.partners.index', ['role' => $key]) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $activeRole === $key ? 'bg-brand-gold text-brand' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @livewire('admin.partners-table', ['category' => (string) $activeRole, 'lockCategory' => filled($activeRole)])
</x-admin.layout>
