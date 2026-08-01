<x-admin.layout title="Partners" heading="Partners hub" subheading="Unified view of suppliers, affiliates, GPS, insurance, valuers, and other partner roles">
    @php
        $roleOptions = app(\App\Services\PartnerService::class)->roleOptions();
        $activeRole = request('role', '');
    @endphp
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600 max-w-2xl">One partner can hold multiple roles. Use the role chips below instead of separate menus.</p>
        <a href="{{ route('admin.partners.create') }}" class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-lg text-sm">+ New partner</a>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.partners.index') }}"
           class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $activeRole === '' ? 'bg-amber-500 text-gray-900' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
            All roles
        </a>
        @foreach ($roleOptions as $key => $label)
            <a href="{{ route('admin.partners.index', ['role' => $key]) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $activeRole === $key ? 'bg-amber-500 text-gray-900' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @livewire('admin.partners-table', ['category' => $activeRole ?: null, 'lockCategory' => filled($activeRole)])
</x-admin.layout>
