@php
    $partnerAvailability = $partnerAvailability ?? [
        'region' => null,
        'region_missing' => true,
        'available' => [],
        'unavailable' => [],
        'counts' => ['available' => 0, 'unavailable' => 0, 'by_type_available' => [], 'by_type_unavailable' => []],
    ];
    $mode = $mode ?? 'available'; // available|unavailable
    $rows = $mode === 'unavailable'
        ? ($partnerAvailability['unavailable'] ?? [])
        : ($partnerAvailability['available'] ?? []);
    $region = $partnerAvailability['region'] ?? null;
    $regionMissing = (bool) ($partnerAvailability['region_missing'] ?? blank($region));
@endphp

<div class="space-y-4">
    <div class="rounded-xl ring-1 px-4 py-3 {{ $regionMissing ? 'bg-amber-50 ring-amber-200 text-amber-950' : 'bg-sky-50 ring-sky-200 text-sky-950' }}">
        <p class="text-sm font-semibold">
            @if ($mode === 'available')
                Partners available for this borrower
            @else
                Partners outside this borrower’s region
            @endif
        </p>
        <p class="text-xs mt-1 opacity-90">
            @if ($regionMissing)
                Borrower residence region is missing — only <strong>nationwide</strong> partners count as available. Set the region on Residence so regional partners can be matched like Bolt/Uber.
            @else
                Borrower region: <strong>{{ $region }}</strong>.
                Nationwide partners always qualify; regional partners must list this region on their coverage.
            @endif
        </p>
    </div>

    @if ($mode === 'unavailable')
        <div class="rounded-xl bg-white ring-1 ring-brand/10 px-4 py-3 text-sm text-gray-700">
            Use this list to enroll or extend coverage before the file reaches valuation, GPS, insurance, or recovery steps.
            <a href="{{ route('admin.partners.create') }}" class="font-semibold text-brand hover:underline ml-1">Add partner →</a>
        </div>
    @endif

    @php
        $grouped = collect($rows)->groupBy('type_label');
    @endphp

    @forelse ($grouped as $typeLabel => $partners)
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-2 bg-brand-muted/30">
                <p class="text-sm font-semibold text-gray-900">{{ $typeLabel }}</p>
                <span class="text-xs font-bold text-brand">{{ $partners->count() }}</span>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($partners as $partner)
                    <li class="px-4 py-3 flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $partner['name'] }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                @if ($partner['partner_number'])
                                    <span class="font-mono">{{ $partner['partner_number'] }}</span>
                                    <span class="text-gray-300">·</span>
                                @endif
                                {{ $partner['phone'] ?: 'No phone' }}
                            </p>
                            <p class="text-[11px] mt-1 {{ ($partner['coverage_type'] ?? '') === 'nationwide' ? 'text-emerald-700 font-semibold' : 'text-gray-500' }}">
                                Coverage: {{ $partner['coverage'] }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ $partner['show_url'] }}" class="text-xs font-semibold text-brand hover:underline">View</a>
                            <a href="{{ $partner['edit_url'] }}" class="text-xs font-semibold text-gray-600 hover:underline">Edit coverage</a>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-8 text-center">
            <p class="text-sm font-semibold text-gray-800">
                @if ($mode === 'available')
                    No partners cover this region yet
                @else
                    Every active partner already covers this region
                @endif
            </p>
            <p class="text-xs text-gray-500 mt-1">
                @if ($mode === 'available')
                    Enroll a regional or nationwide partner, or extend coverage on existing partners.
                @else
                    Gaps will appear here when partners are missing this region.
                @endif
            </p>
            @if ($mode === 'available')
                <a href="{{ route('admin.partners.create') }}" class="inline-flex mt-3 text-sm font-semibold text-brand hover:underline">Enroll partner →</a>
            @endif
        </div>
    @endforelse
</div>
