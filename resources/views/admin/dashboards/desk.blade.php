@php
    $dashboard = $dashboard ?? [];
    $cards = $dashboard['cards'] ?? [];
    $actions = $dashboard['actions'] ?? [];
    $coverageAlerts = $dashboard['coverageAlerts'] ?? collect();
    $pendingApplications = $dashboard['pendingApplications'] ?? collect();
@endphp

<section class="mb-8">
    <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-7 text-white">
            <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">{{ $dashboard['kicker'] ?? 'Desk' }}</p>
            <h1 class="text-2xl sm:text-3xl font-bold mt-1">{{ $dashboard['title'] ?? 'Dashboard' }}</h1>
            <p class="text-sm text-white/75 mt-2 max-w-2xl">{{ $dashboard['subtitle'] ?? '' }}</p>
        </div>
        @if ($cards !== [])
            <div class="bg-white px-6 py-5 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($cards as $card)
                    <a href="{{ $card['url'] }}" class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 px-4 py-4 hover:ring-brand/30 transition">
                        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ $card['label'] }}</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ format_number($card['value']) }}</p>
                        @if (! empty($card['hint']))
                            <p class="text-xs text-gray-500 mt-1">{{ $card['hint'] }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>

@if ($actions !== [])
    <div class="mb-6 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 sm:p-6 flex flex-wrap gap-3">
        @foreach ($actions as [$label, $url, $tone])
            <a href="{{ $url }}"
               class="inline-flex items-center gap-2 text-sm font-bold px-5 py-2.5 rounded-xl ring-1
                      {{ $tone === 'gold'
                            ? 'bg-brand-gold hover:brightness-95 text-brand shadow-sm ring-brand/15'
                            : 'bg-white hover:bg-brand-muted/40 text-brand ring-brand/20' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
@endif

@if ($coverageAlerts->isNotEmpty())
    <div class="mb-6 space-y-2">
        @foreach ($coverageAlerts as $alert)
            <a href="{{ $alert['url'] }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 hover:ring-amber-300">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Needs you now</p>
                    <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $alert['label'] }}</p>
                    <p class="text-xs text-gray-600 mt-1">Add the region on an existing partner, or enroll a new one.</p>
                </div>
                <span class="text-sm font-semibold text-brand">Review →</span>
            </a>
        @endforeach
    </div>
@endif

@if ($pendingApplications->isNotEmpty())
    <section class="mb-6 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Screening</p>
                <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Partner applications</h2>
            </div>
            <a href="{{ route('admin.partner-applications.index', ['status' => 'pending']) }}" class="text-xs font-semibold text-brand hover:underline">All applications →</a>
        </div>
        <ul class="divide-y divide-gray-100">
            @foreach ($pendingApplications as $application)
                <li>
                    <a href="{{ route('admin.partner-applications.show', $application) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 hover:bg-brand-muted/20">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $application->full_name }}</p>
                            <p class="text-xs text-gray-500 truncate">
                                {{ $application->categoryLabel() }}
                                · {{ $application->region ?: 'No region' }}
                                · {{ $application->documents->count() }} docs
                            </p>
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $application->status === 'needs_info' ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-900' }}">
                            {{ ucfirst(str_replace('_', ' ', $application->status)) }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
