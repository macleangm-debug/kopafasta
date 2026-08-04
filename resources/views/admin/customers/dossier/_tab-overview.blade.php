@php
    $standing = $dossier['repayment_standing'] ?? [];
    $crb = $dossier['crb'] ?? [];
    $eligibility = $dossier['eligibility'] ?? [];
@endphp

<div class="space-y-6">
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach ($dossier['checklist'] as $item)
            @php
                $tone = match ($item['tone']) {
                    'emerald' => 'bg-emerald-50 ring-emerald-200 text-emerald-800',
                    'amber'   => 'bg-amber-50 ring-amber-200 text-amber-900',
                    default   => 'bg-gray-50 ring-gray-200 text-gray-700',
                };
            @endphp
            <div class="rounded-xl ring-1 px-4 py-3 {{ $tone }}">
                <p class="text-[10px] uppercase tracking-widest font-semibold opacity-80">{{ $item['label'] }}</p>
                <p class="text-sm font-semibold mt-1">{{ $item['detail'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <div>
            <h4 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Trust factors</h4>
            @forelse ($standing['factors'] ?? [] as $factor)
                <div class="flex items-center justify-between gap-3 text-sm rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-2 mb-2">
                    <span>{{ $factor['label'] ?? $factor['key'] }}</span>
                    <span class="font-semibold tabular-nums">{{ $factor['score'] ?? 0 }}/{{ $factor['max'] ?? 100 }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Not enough history yet.</p>
            @endforelse
            <p class="text-xs text-gray-500 mt-3">Loyalty points: <span class="font-semibold text-gray-800">{{ number_format((int) ($standing['loyalty_points'] ?? 0)) }}</span></p>
        </div>
        <div>
            <h4 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">CRB</h4>
            @if (! ($crb['available'] ?? false))
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3 text-sm text-gray-600">{{ $crb['message'] ?? 'CRB not available.' }}</div>
            @else
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                        <dt class="text-[10px] uppercase text-gray-500">Score</dt>
                        <dd class="font-bold mt-1">{{ $crb['score'] ?? '—' }}</dd>
                    </div>
                    <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                        <dt class="text-[10px] uppercase text-gray-500">Grade</dt>
                        <dd class="font-bold mt-1">{{ $crb['risk_grade'] ?? '—' }}</dd>
                    </div>
                </dl>
                <p class="text-xs text-gray-500 mt-2">{{ $crb['message'] ?? '' }}</p>
            @endif

            @if (! empty($eligibility['items']))
                <h4 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3 mt-5">Eligibility</h4>
                <ul class="space-y-1.5">
                    @foreach (collect($eligibility['items'])->take(6) as $item)
                        @php $ok = (bool) ($item['complete'] ?? false); @endphp
                        <li class="flex justify-between text-sm rounded-lg px-3 py-2 ring-1 {{ $ok ? 'bg-emerald-50 ring-emerald-100' : 'bg-amber-50 ring-amber-100' }}">
                            <span>{{ $item['label'] ?? $item['key'] }}</span>
                            <span class="text-xs font-semibold">{{ $ok ? 'Ready' : 'Incomplete' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
