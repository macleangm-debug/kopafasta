@php
    $crb = $review['crb'];
    $crbFreshnessDays = app(\App\Services\CrbFreshnessService::class)->freshnessDays();
@endphp

<div class="grid sm:grid-cols-2 gap-4 mb-5">
    <div class="rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 p-4">
        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Identity (from NIDA / CRB)</p>
        <dl class="mt-2 space-y-1.5 text-sm">
            <div><dt class="text-xs text-gray-500 inline">Name:</dt> <dd class="inline font-medium">{{ $crb['identity']['full_name'] ?? '—' }}</dd></div>
            <div><dt class="text-xs text-gray-500 inline">NIDA:</dt> <dd class="inline font-mono text-xs">{{ $crb['identity']['national_id'] ?? '—' }}</dd></div>
            <div><dt class="text-xs text-gray-500 inline">DOB:</dt> <dd class="inline">{{ $crb['identity']['date_of_birth'] ?? '—' }}</dd></div>
            <div><dt class="text-xs text-gray-500 inline">Gender:</dt> <dd class="inline capitalize">{{ $crb['identity']['gender'] ?? '—' }}</dd></div>
        </dl>
    </div>
    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 p-4">
        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">CRB freshness — {{ $crbFreshnessDays }} days</p>
        <p @class([
            'text-sm font-bold uppercase mt-1',
            'text-emerald-700' => ($crb['freshness_tone'] ?? '') === 'emerald',
            'text-amber-700'   => ($crb['freshness_tone'] ?? '') === 'amber',
            'text-gray-700'    => ($crb['freshness_tone'] ?? '') === 'gray',
        ])>{{ $crb['freshness_label'] ?? '—' }}</p>
        @if ($crb['checked_at'] ?? null)
            <p class="text-xs text-gray-500 mt-1">Retrieved {{ $crb['checked_at']->diffForHumans() }}</p>
        @endif
        @if (($crb['days_since_check'] ?? null) !== null)
            <p class="text-xs text-gray-500">{{ $crb['days_since_check'] }} days ago</p>
        @endif
        @if (! empty($crb['submission_meta']['reused']))
            <p class="text-xs text-emerald-700 mt-3">Existing CRB record reused (no new bureau charge).</p>
        @elseif (! empty($crb['submission_meta']['refreshed']))
            <p class="text-xs text-sky-700 mt-3">CRB refreshed on application submit.</p>
        @endif
    </div>
</div>

<dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm mb-5">
    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
        <dt class="text-[10px] uppercase tracking-widest text-gray-500">Source</dt>
        <dd class="font-semibold mt-1">{{ $crb['status'] ?? '—' }}</dd>
    </div>
    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
        <dt class="text-[10px] uppercase tracking-widest text-gray-500">Outstanding</dt>
        <dd class="font-semibold mt-1">{{ ($crb['outstanding_balance'] ?? null) ? format_money($crb['outstanding_balance']) : '—' }}</dd>
    </div>
    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
        <dt class="text-[10px] uppercase tracking-widest text-gray-500">Risk grade</dt>
        <dd class="font-semibold mt-1 uppercase">{{ $crb['risk_grade'] ?? '—' }}</dd>
    </div>
    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
        <dt class="text-[10px] uppercase tracking-widest text-gray-500">CRB RUID</dt>
        <dd class="font-mono text-xs mt-1 break-all">{{ $crb['crb_ruid'] ?? '—' }}</dd>
    </div>
</dl>

@if (! empty($crb['loan_history']))
    <div class="rounded-xl ring-1 ring-gray-100 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-[10px] uppercase tracking-widest text-gray-500">
                <tr>
                    <th class="px-4 py-2.5 text-left font-semibold">Lender</th>
                    <th class="px-4 py-2.5 text-left font-semibold">Status</th>
                    <th class="px-4 py-2.5 text-right font-semibold">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($crb['loan_history'] as $row)
                    <tr>
                        <td class="px-4 py-2.5">{{ $row['lender'] ?? '—' }}</td>
                        <td class="px-4 py-2.5 capitalize">{{ $row['status'] ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums">{{ isset($row['balance']) ? format_money($row['balance']) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-sm text-gray-500">No external loan history rows on this CRB record.</p>
@endif
