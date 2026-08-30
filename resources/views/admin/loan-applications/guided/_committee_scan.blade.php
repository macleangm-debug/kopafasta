@php
    $scan = app(\App\Services\GuidedApprovalService::class)->executiveScan($record);
    $changed = $scan['what_changed'] ?? ['has_changes' => false, 'items' => []];
    $afford = $review['affordability'] ?? [];
    $crb = $review['crb'] ?? [];
@endphp
<div class="rounded-2xl bg-white ring-1 ring-brand/15 px-4 py-4 space-y-3">
    <p class="text-[11px] font-bold uppercase tracking-widest text-brand">Committee executive scan</p>
    <p class="text-sm text-slate-700">Screening already established this file. Committee verifies and decides — it does not repeat Screening checks.</p>

    @if (! empty($changed['has_changes']))
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3 py-2.5 space-y-1">
            <p class="text-xs font-bold text-amber-900">Updated since your last review</p>
            @foreach ($changed['items'] as $item)
                <p class="text-sm text-amber-950">
                    {{ str_replace('_', ' ', $item['key'] ?? '') }}:
                    @if (($item['previous'] ?? null) !== null)
                        {{ $item['previous'] }} →
                    @endif
                    {{ is_scalar($item['current'] ?? null) ? $item['current'] : '' }}
                </p>
            @endforeach
        </div>
    @endif

    <ul class="text-sm space-y-1">
        @foreach ($scan['gates'] ?? [] as $gate)
            <li class="flex justify-between gap-2">
                <span>{{ $gate['label'] }}</span>
                <span class="font-semibold">{{ $gate['chip'] ?: $gate['status'] }}</span>
            </li>
        @endforeach
        <li>Affordability · {{ ! empty($afford['pass']) ? 'PASS' : strtoupper((string) ($afford['verdict'] ?? 'review')) }}</li>
        <li>CRB · {{ strtoupper((string) ($crb['recommendation'] ?? '—')) }}</li>
        <li>Screening checklist · {{ (int) ($screeningReadiness['checklist_done'] ?? 0) }}/{{ (int) ($screeningReadiness['checklist_total'] ?? 0) }}</li>
    </ul>
    @php $exceptions = collect($underwritingAnomalies ?? [])->take(5); @endphp
    @if ($exceptions->isNotEmpty())
        <div>
            <p class="text-xs font-bold text-slate-600">Exceptions</p>
            @foreach ($exceptions as $ex)
                <p class="text-sm text-slate-800">{{ $ex['label'] ?? $ex['title'] ?? '' }}</p>
            @endforeach
        </div>
    @else
        <p class="text-sm font-semibold text-emerald-800">No unresolved exceptions listed.</p>
    @endif
    <a href="{{ route('admin.loan-applications.show', ['loan_application' => $record, 'workspace' => 'checklist']) }}"
       class="text-xs font-semibold text-slate-600 underline">View full credit file</a>
</div>
