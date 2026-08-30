@php
    $step = $walk['step'] ?? [];
    $index = (int) ($walk['index'] ?? 1);
    $total = (int) ($walk['total'] ?? 6);
    $changed = $walk['changed'] ?? ['has_changes' => false, 'items' => []];
    $key = $step['key'] ?? 'facility';
    $afford = $review['affordability'] ?? [];
    $crb = $review['crb'] ?? [];
@endphp
<x-admin.layout
    :title="$record->application_number.' · Committee Review'"
    heading=""
    :backUrl="route('admin.loan-applications.show', $record)"
    backLabel="Credit file">

    <div class="max-w-xl mx-auto pb-28">
        <p class="text-[11px] font-bold uppercase tracking-widest text-brand">Committee · Step {{ $index }} of {{ $total }}</p>
        <h1 class="text-xl font-bold text-slate-900 mt-1">{{ $step['title'] ?? 'Committee review' }}</h1>
        <p class="text-sm text-slate-600 mt-1">{{ $record->application_number }} · Screening complete — this is not a re-screen.</p>

        @if (! empty($changed['has_changes']))
            <div class="mt-4 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 space-y-1">
                <p class="text-[11px] font-bold uppercase tracking-wide text-amber-800">Updated since your last review</p>
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

        <div class="mt-6 rounded-2xl bg-white ring-1 ring-brand/15 px-4 py-4 space-y-3 text-sm">
            <p class="text-slate-700">{{ $step['prompt'] ?? '' }}</p>
            @if ($key === 'facility')
                <p><span class="font-semibold">Amount</span> · {{ format_money((float) $record->requested_amount) }}</p>
                <p><span class="font-semibold">Term</span> · {{ $record->requested_tenure_months }} months</p>
                <p><span class="font-semibold">Product</span> · {{ $record->product?->name ?? '—' }}</p>
                <p><span class="font-semibold">Party</span> · {{ $record->partyLabel() }}</p>
            @elseif ($key === 'capacity')
                <p>Screening: {{ ! empty($afford['pass']) ? 'PASS' : strtoupper((string) ($afford['verdict'] ?? 'review')) }}</p>
                @if (! empty($afford['reason']))
                    <p class="text-slate-600">{{ $afford['reason'] }}</p>
                @endif
            @elseif ($key === 'crb')
                <p>Recommendation: {{ strtoupper((string) ($crb['recommendation'] ?? '—')) }}</p>
                <p class="text-slate-600">Existing reports are reused. Opening this step does not pull CRB again.</p>
            @elseif ($key === 'people')
                <p>Participants were verified in Screening Gate 4. Tap View full credit file to inspect evidence. Do not call NOK or LGO again unless returning for clarification.</p>
            @elseif ($key === 'security')
                <p>Collateral and valuation results are the same records Screening used. GPS remains outside Screening where policy says it is post-approval.</p>
            @else
                <p class="font-semibold">Screening recommendation: {{ $record->recommendation_type ?: 'not recorded' }}</p>
                <p>If something is wrong, return for clarification rather than re-doing Screening. The return opens Screening at this question.</p>
            @endif
        </div>

        <p class="mt-4">
            <a href="{{ $walk['file_href'] }}" class="text-xs font-semibold text-slate-600 underline">View full credit file</a>
        </p>
    </div>

    <div class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 backdrop-blur px-4 py-3">
        <div class="max-w-xl mx-auto flex gap-2">
            @if (! empty($walk['prev_index']))
                <a href="{{ route('admin.loan-applications.guided-committee', ['loan_application' => $record, 'step' => $walk['prev_index']]) }}"
                   class="flex-1 text-center rounded-xl bg-white ring-1 ring-slate-200 font-bold text-sm py-3">Back</a>
            @else
                <a href="{{ route('admin.loan-applications.show', ['loan_application' => $record, 'workspace' => 'overview']) }}"
                   class="flex-1 text-center rounded-xl bg-white ring-1 ring-slate-200 font-bold text-sm py-3">Back</a>
            @endif
            @if (! empty($walk['next_index']))
                <a href="{{ route('admin.loan-applications.guided-committee', ['loan_application' => $record, 'step' => $walk['next_index']]) }}"
                   class="flex-[2] text-center rounded-xl bg-brand text-white font-bold text-sm py-3">Continue</a>
            @else
                <a href="{{ $walk['decision_href'] }}"
                   class="flex-[2] text-center rounded-xl bg-brand text-white font-bold text-sm py-3">Committee decision</a>
            @endif
        </div>
    </div>
</x-admin.layout>
