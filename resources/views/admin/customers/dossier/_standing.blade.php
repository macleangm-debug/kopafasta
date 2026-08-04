@php
    $standing = $dossier['repayment_standing'] ?? [];
    $crb = $dossier['crb'] ?? [];
    $eligibility = $dossier['eligibility'] ?? [];
@endphp

<x-admin.review-section id="customer-standing" title="Member standing" subtitle="Trust, eligibility, repayment behaviour, and bureau data when available">
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/15 px-4 py-3">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Trust score</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $standing['trust_percent'] ?? 0 }}%</p>
            <p class="text-xs text-gray-500 mt-1">{{ $standing['trust_stars'] ?? 0 }}/{{ $standing['trust_max'] ?? 5 }} stars · {{ $standing['label'] ?? '—' }}</p>
        </div>
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-3">
            <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Repayment streak</p>
            <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ $standing['streak'] ?? 0 }}</p>
            <p class="text-xs text-gray-500 mt-1">Consecutive on-time instalments</p>
        </div>
        <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-100 px-4 py-3">
            <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">Loyalty points</p>
            <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ number_format((int) ($standing['loyalty_points'] ?? 0)) }}</p>
            <p class="text-xs text-gray-500 mt-1">Redeemable rewards balance</p>
        </div>
        <div class="rounded-xl bg-sky-50 ring-1 ring-sky-100 px-4 py-3">
            <p class="text-[10px] uppercase tracking-widest text-sky-800 font-semibold">Active loans</p>
            <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">{{ $dossier['loans']->whereIn('status', ['active', 'arrears', 'disbursed'])->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $dossier['loans']->count() }} total on file</p>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Trust factors</h3>
            @if (empty($standing['factors']))
                <p class="text-sm text-gray-500">Not enough history yet.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($standing['factors'] as $factor)
                        <li class="flex items-center justify-between gap-3 text-sm rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-2">
                            <span class="text-gray-700">{{ $factor['label'] ?? $factor['key'] }}</span>
                            <span class="font-semibold tabular-nums text-gray-900">{{ $factor['score'] ?? 0 }}/{{ $factor['max'] ?? 100 }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div>
            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">CRB</h3>
            @if (! ($crb['available'] ?? false))
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3 text-sm text-gray-600">
                    {{ $crb['message'] ?? 'CRB not available.' }}
                </div>
            @else
                <dl class="grid sm:grid-cols-2 gap-3 text-sm mb-3">
                    <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                        <dt class="text-[10px] uppercase tracking-widest text-gray-500">Score</dt>
                        <dd class="font-bold text-gray-900 mt-1">{{ $crb['score'] ?? '—' }}</dd>
                    </div>
                    <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                        <dt class="text-[10px] uppercase tracking-widest text-gray-500">Risk grade</dt>
                        <dd class="font-bold text-gray-900 mt-1">{{ $crb['risk_grade'] ?? '—' }}</dd>
                    </div>
                    <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-3 sm:col-span-2">
                        <dt class="text-[10px] uppercase tracking-widest text-gray-500">Pulled</dt>
                        <dd class="font-medium text-gray-900 mt-1">
                            {{ optional($crb['checked_at'])->format('d M Y H:i') ?? '—' }}
                            · {{ ($crb['fresh'] ?? false) ? 'Fresh' : 'Stale' }}
                        </dd>
                    </div>
                </dl>
                <p class="text-xs text-gray-500">{{ $crb['message'] ?? '' }}</p>
            @endif

            @if (! empty($eligibility['items'] ?? null))
                <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3 mt-6">Eligibility checklist</h3>
                <ul class="space-y-2">
                    @foreach ($eligibility['items'] as $item)
                        @php $ok = (bool) ($item['complete'] ?? false); @endphp
                        <li class="flex items-center justify-between gap-3 text-sm rounded-lg px-3 py-2 ring-1 {{ $ok ? 'bg-emerald-50 ring-emerald-100 text-emerald-900' : 'bg-amber-50 ring-amber-100 text-amber-950' }}">
                            <span>{{ $item['label'] ?? $item['key'] ?? 'Item' }}</span>
                            <span class="text-xs font-semibold">{{ $ok ? 'Ready' : 'Incomplete' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-admin.review-section>
