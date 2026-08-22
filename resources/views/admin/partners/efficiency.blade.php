@php
    $policy = $policy ?? app(\App\Services\PartnerEfficiencyPolicy::class);
    $bandStyles = [
        'strong' => 'bg-emerald-50 text-emerald-800 ring-emerald-100',
        'watch' => 'bg-amber-50 text-amber-800 ring-amber-100',
        'at_risk' => 'bg-rose-50 text-rose-800 ring-rose-100',
        'new' => 'bg-gray-100 text-gray-700 ring-gray-200',
    ];
    $bandLabels = [
        'strong' => 'Strong',
        'watch' => 'Watch',
        'at_risk' => 'Needs coaching',
        'new' => 'New',
    ];
@endphp

<x-admin.layout title="Partner efficiency" heading="" subheading="">
    <x-admin.letterhead
        kicker="Partners"
        title="Partner efficiency"
        subtitle="Completion, on-time SLA, failed jobs, and escalations — same board for admin and Partner support.">
        <x-slot:actions>
            @if (auth()->user()?->hasPermission('settings.manage'))
                <a href="{{ route('admin.settings.partner-performance') }}"
                   class="inline-flex items-center text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-1.5 rounded-lg">
                    Settings
                </a>
            @endif
        </x-slot:actions>
        <x-slot:stats>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand/70 font-semibold">Partners scored</p>
                    <p class="text-2xl font-bold text-gray-900 tabular-nums mt-1">{{ number_format($rows->count()) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">Strong</p>
                    <p class="text-2xl font-bold text-gray-900 tabular-nums mt-1">{{ number_format($leaders->count()) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-rose-800 font-semibold">Needs coaching</p>
                    <p class="text-2xl font-bold text-gray-900 tabular-nums mt-1">{{ number_format($coaching->count()) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Score</p>
                    <p class="text-xs text-gray-600 mt-2">{{ $policy->weightCompletion() }}% completed · {{ $policy->weightOnTime() }}% on time · {{ $policy->weightNotEscalated() }}% not escalated · {{ $policy->weightNotFailed() }}% not failed. {{ $policy->minJobsForScore() }} closed jobs before a score.</p>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.letterhead>

    @if ($coaching->isNotEmpty())
        <section class="mb-6 rounded-2xl bg-rose-50 ring-1 ring-rose-200 p-5">
            <h2 class="text-sm font-bold text-rose-950">Coach these partners</h2>
            <p class="text-xs text-rose-900/80 mt-1">They fail jobs, miss SLA, or get escalated to the next recovery level too often.</p>
            <ul class="mt-3 grid sm:grid-cols-2 gap-3">
                @foreach ($coaching as $row)
                    <li class="rounded-xl bg-white ring-1 ring-rose-100 px-4 py-3">
                        <a href="{{ route('admin.partners.show', $row['partner']) }}" class="text-sm font-semibold text-gray-900 hover:text-brand">
                            {{ $row['partner']->name }}
                        </a>
                        <p class="text-xs text-gray-600 mt-1">
                            Score {{ $row['score'] ?? '—' }}
                            · {{ $row['escalated'] }} escalated
                            · {{ $row['failed'] }} failed
                            · {{ $row['completion_rate'] }}% completed
                        </p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="bg-white rounded-2xl ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Partner</th>
                    <th class="px-4 py-3">Jobs</th>
                    <th class="px-4 py-3">Completed</th>
                    <th class="px-4 py-3">On time</th>
                    <th class="px-4 py-3">Escalated</th>
                    <th class="px-4 py-3">Failed</th>
                    <th class="px-4 py-3">Score</th>
                    <th class="px-4 py-3">Band</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.partners.show', $row['partner']) }}" class="font-semibold text-brand hover:underline">
                                {{ $row['partner']->name }}
                            </a>
                            <div class="text-xs text-gray-500">{{ ucfirst(str_replace('_', ' ', (string) $row['partner']->category)) }}</div>
                        </td>
                        <td class="px-4 py-3 tabular-nums">{{ $row['assigned'] }} <span class="text-xs text-gray-500">({{ $row['open'] }} open)</span></td>
                        <td class="px-4 py-3 tabular-nums">{{ $row['completed'] }} · {{ $row['completion_rate'] }}%</td>
                        <td class="px-4 py-3 tabular-nums">{{ $row['on_time_rate'] }}%</td>
                        <td class="px-4 py-3 tabular-nums {{ $row['escalated'] > 0 ? 'text-red-700 font-semibold' : '' }}">{{ $row['escalated'] }} · {{ $row['escalation_rate'] }}%</td>
                        <td class="px-4 py-3 tabular-nums">{{ $row['failed'] }} · {{ $row['fail_rate'] }}%</td>
                        <td class="px-4 py-3 font-bold tabular-nums">{{ $row['score'] ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $bandStyles[$row['band']] }}">
                                {{ $row['band_label'] ?? $bandLabels[$row['band']] }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-gray-500">No field partners yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @php $affiliates = $affiliates ?? collect(); @endphp
    <div class="mt-6 bg-white rounded-2xl ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <div class="px-4 py-4 border-b border-gray-100">
            <h2 class="text-sm font-bold text-gray-900">Affiliates — new users this period</h2>
            <p class="text-xs text-gray-500 mt-1">Target is set in Affiliate settings (default 10 / month). Onboarding partners are not scored for volume yet.</p>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Affiliate</th>
                    <th class="px-4 py-3">New users</th>
                    <th class="px-4 py-3">Target</th>
                    <th class="px-4 py-3">Missed months</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($affiliates as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.partners.show', $row['partner']) }}" class="font-semibold text-brand hover:underline">
                                {{ $row['partner']->name }}
                            </a>
                            <div class="text-xs text-gray-500">{{ $row['partner']->affiliate_code }}</div>
                        </td>
                        <td class="px-4 py-3 tabular-nums">{{ $row['registrations'] }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ $row['target'] }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ $row['consecutive_misses'] }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $row['missed'] ? 'bg-rose-50 text-rose-800 ring-rose-100' : ($row['onboarding'] ? 'bg-gray-100 text-gray-700 ring-gray-200' : 'bg-emerald-50 text-emerald-800 ring-emerald-100') }}">
                                {{ $row['label'] }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No affiliates yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>
