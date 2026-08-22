<x-admin.layout title="Partners" heading="" subheading="">
    <x-admin.letterhead kicker="Partners" title="Partners hub" subtitle="Partner support enrolls valuers, GPS, insurance, and other partners, and extends regional coverage" />
    @php
        $roleOptions = app(\App\Services\PartnerService::class)->roleOptions();
        $activeRole = request('role', '');
        $coverageAlerts = auth()->user()?->can('create', \App\Models\Vendor::class)
            ? app(\App\Services\PartnerCoverageRequestService::class)->staffAlerts()
            : collect();
        $screeningCount = \App\Models\PartnerApplication::query()->screening()->count();
        $awaitingCount = \App\Models\Partner::query()->where('status', 'inactive')->count();
    @endphp
    @if ($coverageAlerts->isNotEmpty())
        <div class="mb-6 space-y-2">
            @foreach ($coverageAlerts as $alert)
                <a href="{{ $alert['url'] }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 hover:ring-amber-300">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Coverage gap</p>
                        <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $alert['label'] }}</p>
                        <p class="text-xs text-gray-600 mt-1">Add the region on an existing partner, or enroll a new one.</p>
                    </div>
                    <span class="text-sm font-semibold text-brand">Review →</span>
                </a>
            @endforeach
        </div>
    @endif
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600 max-w-2xl">One partner can hold multiple roles. Use the role chips below instead of separate menus.</p>
        @can('create', \App\Models\Vendor::class)
            <a href="{{ route('admin.partners.create') }}" class="inline-flex bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2 rounded-lg text-sm">+ New partner</a>
        @endcan
    </div>

    <div class="mb-6 grid sm:grid-cols-2 gap-3">
        <a href="{{ route('admin.partner-applications.index') }}"
           class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-5 py-4 hover:ring-brand/30 transition">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Applications to screen</p>
                <p class="text-2xl font-bold text-gray-900 tabular-nums mt-1">{{ number_format($screeningCount) }}</p>
                <p class="text-xs text-gray-500 mt-1">Approve to move them onto this hub.</p>
            </div>
            <span class="text-sm font-semibold text-brand">Open →</span>
        </a>
        <a href="{{ route('admin.partners.onboarding') }}"
           class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-5 py-4 hover:ring-brand/30 transition">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Awaiting activation</p>
                <p class="text-2xl font-bold text-gray-900 tabular-nums mt-1">{{ number_format($awaitingCount) }}</p>
                <p class="text-xs text-gray-500 mt-1">Approved partners who have not set a PIN yet.</p>
            </div>
            <span class="text-sm font-semibold text-brand">Open →</span>
        </a>
    </div>

    <a href="{{ route('admin.partners.efficiency') }}"
       class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm px-5 py-4 hover:ring-brand/30 transition">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Performance</p>
            <p class="text-sm font-bold text-gray-900 mt-0.5">Partner efficiency</p>
            <p class="text-xs text-gray-500 mt-1">Field partners on jobs. Affiliates on monthly new users. Open the board to see who is on each track.</p>
        </div>
        <span class="text-sm font-semibold text-brand">Open →</span>
    </a>

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
