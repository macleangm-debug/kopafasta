@php
    $step = $walk['step'] ?? [];
    $index = (int) ($walk['index'] ?? 1);
    $total = (int) ($walk['total'] ?? 6);
    $changed = $walk['changed'] ?? ['has_changes' => false, 'items' => []];
    $key = $step['key'] ?? 'facility';
    $afford = $review['affordability'] ?? [];
    $crb = $review['crb'] ?? [];
    $exceptions = $walk['exceptions'] ?? ($walk['scan']['exceptions'] ?? ['total' => 0, 'items' => []]);
    $exceptionItems = collect($exceptions['items'] ?? []);
    $blockDecision = ! empty($walk['block_decision']);
    $committeeDesk = route('admin.teams.committee');
@endphp
<x-admin.guided-review-shell
    :record="$record"
    mode="committee"
    :percent="$walk['percent'] ?? (int) round(($index / max(1, $total)) * 100)"
    :gateChip="'Step '.$index.' of '.$total.' · '.($step['title'] ?? 'Committee')"
    :gateProgress="'Committee scan · '.$index.' of '.$total"
    :backUrl="$committeeDesk"
    backLabel="Back to Committee">

        <div class="mt-4 rounded-2xl bg-white ring-1 ring-brand/15 px-4 py-3 space-y-2">
            <p class="text-[11px] font-bold uppercase tracking-wide text-brand">Screening result</p>
            <p class="text-sm font-semibold text-slate-900">Screening complete ✓</p>
            @if ((int) ($exceptions['total'] ?? 0) > 0)
                <p class="text-sm text-amber-950 font-semibold">
                    {{ $exceptions['total'] }} {{ \Illuminate\Support\Str::plural('exception', $exceptions['total']) }} require{{ (int) $exceptions['total'] === 1 ? 's' : '' }} Committee attention
                </p>
                <p class="text-xs text-slate-600">
                    {{ (int) ($exceptions['critical'] ?? 0) }} critical
                    · {{ (int) ($exceptions['material'] ?? 0) }} material
                    · {{ (int) ($exceptions['information'] ?? 0) }} information
                </p>
                <a href="{{ route('admin.loan-applications.guided-committee', ['loan_application' => $record, 'step' => 1]) }}"
                   class="inline-flex text-sm font-bold text-brand underline">Review exceptions →</a>
            @else
                <p class="text-sm text-slate-600">No system disagreements were accepted during Screening.</p>
            @endif
        </div>

        @if (! empty($changed['has_changes']))
            <div class="mt-4 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 space-y-1">
                <p class="text-[11px] font-bold uppercase tracking-wide text-amber-800">Updated since your last review</p>
                @foreach ($changed['items'] as $item)
                    @php $changeKey = (string) ($item['key'] ?? ''); @endphp
                    <p class="text-sm text-amber-950">
                        @if ($changeKey === 'exceptions_signature')
                            Screening added clarification or updated an accepted exception.
                        @elseif ($changeKey === 'screening_response')
                            Screening responded to Committee’s clarification.
                        @else
                            {{ str_replace('_', ' ', $changeKey) }}:
                            @if (($item['previous'] ?? null) !== null)
                                {{ $item['previous'] }} →
                            @endif
                            {{ is_scalar($item['current'] ?? null) ? $item['current'] : '' }}
                        @endif
                    </p>
                @endforeach
            </div>
        @endif

        <div class="mt-4 rounded-2xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">What happens next</p>
            <p class="text-sm text-slate-800 mt-1">
                @if ($key === 'exceptions')
                    Review each system disagreement Screening accepted. Acknowledge material items, or return one for clarification. This is not a re-screen.
                @else
                    Screening already established this file. Scan this step, then continue. This is not a re-screen.
                @endif
            </p>
        </div>

        <div class="mt-6 rounded-2xl bg-white ring-1 ring-brand/15 px-4 py-4 space-y-3 text-sm">
            <h2 class="text-lg font-bold text-slate-900">{{ $step['title'] ?? 'Committee review' }}</h2>
            <p class="text-slate-700">{{ $step['prompt'] ?? '' }}</p>
            @if ($key === 'exceptions')
                @forelse ($exceptionItems as $row)
                    @php
                        $severity = (string) ($row['severity'] ?? 'information');
                        $severityLabel = match ($severity) {
                            'critical' => 'Critical exception',
                            'material' => 'Material exception',
                            default => 'Information',
                        };
                        $committeeStatus = (string) ($row['committee']['status'] ?? '');
                        $personLabel = match ($row['person'] ?? 'borrower') {
                            'member' => 'Member'.(! empty($row['m']) ? ' '.$row['m'] : ''),
                            'guarantor' => 'Guarantor',
                            default => 'Borrower',
                        };
                        $evidenceHref = guided_evidence_url(route('admin.loan-applications.show', array_filter([
                            'loan_application' => $record,
                            'workspace' => 'checklist',
                            'desk_phase' => 'capacity',
                            'capacity_tab' => 'crb',
                            'review_person' => $row['person'] ?? 'borrower',
                            'review_m' => $row['m'] ?? null,
                            'review_g' => $row['g'] ?? null,
                            'open_item' => $row['item_key'] ?? null,
                        ])), 'committee');
                    @endphp
                    <article @class([
                        'rounded-xl ring-1 px-3 py-3 space-y-2',
                        'bg-red-50 ring-red-200' => $severity === 'critical' && $committeeStatus === '',
                        'bg-amber-50 ring-amber-200' => $severity === 'material' && $committeeStatus === '',
                        'bg-slate-50 ring-slate-200' => $severity === 'information' && $committeeStatus === '',
                        'bg-emerald-50 ring-emerald-200' => $committeeStatus === 'acknowledged',
                        'bg-white ring-slate-200' => $committeeStatus === 'clarification',
                    ])>
                        <p class="text-[11px] font-bold uppercase tracking-wide">{{ $severityLabel }} · {{ strtoupper((string) ($row['gate'] ?? 'CRB')) }} · {{ $personLabel }}</p>
                        <p><span class="font-semibold">System recommendation:</span> {{ $row['system_outcome'] ?? '—' }}</p>
                        <p><span class="font-semibold">Screening decision:</span> {{ strtoupper((string) ($row['analyst_outcome'] ?? 'accepted')) }}</p>
                        @if (! empty($row['system_label']))
                            <p class="text-slate-600">{{ $row['system_label'] }}</p>
                        @endif
                        @if (! empty($row['reason']))
                            <p class="text-slate-800">Why: “{{ $row['reason'] }}”</p>
                        @endif
                        <p class="text-xs text-slate-600">
                            Accepted by {{ $row['by_name'] ?? 'analyst' }}
                            · {{ format_app_datetime($row['at'] ?? null, 'd M Y') }}
                            @if (! empty($row['at']))
                                · {{ format_app_datetime($row['at'], 'g:i A') }}
                            @endif
                        </p>
                        @if ($committeeStatus === 'acknowledged')
                            <p class="font-semibold text-emerald-800">Reviewed ✓ {{ $row['committee']['by_name'] ?? '' }}</p>
                        @elseif ($committeeStatus === 'clarification')
                            <p class="font-semibold text-amber-900">Returned for clarification</p>
                        @else
                            <div class="flex flex-col sm:flex-row gap-2 pt-1">
                                <a href="{{ $evidenceHref }}" class="flex-1 text-center rounded-xl bg-white ring-1 ring-slate-200 font-bold text-sm py-2">Review evidence</a>
                                @if ($severity !== 'information')
                                    <form method="POST" action="{{ route('admin.loan-applications.screening-exceptions.acknowledge', [$record, $row['id']]) }}" class="flex-1" data-no-draft>
                                        @csrf
                                        <button type="submit" class="w-full rounded-xl bg-brand text-white font-bold text-sm py-2">Acknowledge &amp; continue</button>
                                    </form>
                                @endif
                            </div>
                            @if ($severity !== 'information')
                                <form method="POST" action="{{ route('admin.loan-applications.screening-exceptions.clarify', [$record, $row['id']]) }}" class="space-y-2" data-no-draft>
                                    @csrf
                                    <label class="block text-xs font-bold text-slate-600">Return for clarification</label>
                                    <textarea name="note" required minlength="8" rows="2" maxlength="1000"
                                              placeholder="What should Screening clarify?"
                                              class="w-full rounded-xl border-slate-300 text-sm"></textarea>
                                    <button type="submit" class="rounded-xl bg-white ring-1 ring-amber-300 text-amber-950 font-bold text-sm px-3 py-2">Return for clarification</button>
                                </form>
                            @endif
                        @endif
                    </article>
                @empty
                    <p class="text-slate-600">No screening exceptions on this file.</p>
                @endforelse
            @elseif ($key === 'facility')
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
                <p>CRB recommendation: {{ strtoupper((string) ($crb['recommendation'] ?? '—')) }} — this is bureau status, not a Committee action.</p>
                <p class="text-slate-600">Existing reports are reused. Opening this step does not pull CRB again.</p>
                @if ((int) ($exceptions['total'] ?? 0) > 0)
                    <p class="font-semibold text-amber-950">CRB ⚠ {{ $exceptions['total'] }} accepted {{ \Illuminate\Support\Str::plural('exception', $exceptions['total']) }}</p>
                    <a href="{{ route('admin.loan-applications.guided-committee', ['loan_application' => $record, 'step' => 1]) }}" class="font-bold text-brand underline">Review exceptions →</a>
                @endif
                <p><a href="{{ guided_evidence_url($walk['crb_href'] ?? $walk['file_href'], 'committee') }}" class="font-bold text-brand underline">Review evidence</a></p>
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
            <a href="{{ guided_evidence_url($walk['file_href'], 'committee') }}" class="text-xs font-semibold text-slate-600 underline">View full credit file</a>
        </p>

    <x-slot:footer>
    <div class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 backdrop-blur px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
        <div class="max-w-xl mx-auto flex gap-2 items-stretch">
            @if (! empty($walk['prev_index']))
                <a href="{{ route('admin.loan-applications.guided-committee', ['loan_application' => $record, 'step' => $walk['prev_index']]) }}"
                   class="flex-1 min-w-0 text-center rounded-xl bg-white ring-1 ring-slate-200 font-bold text-sm py-3 px-2 leading-snug whitespace-normal">Back</a>
            @else
                <a href="{{ $committeeDesk }}"
                   class="flex-1 min-w-0 text-center rounded-xl bg-white ring-1 ring-slate-200 font-bold text-sm py-3 px-2 leading-snug whitespace-normal">Back to Committee</a>
            @endif
            @if (! empty($walk['next_index']))
                <a href="{{ route('admin.loan-applications.guided-committee', ['loan_application' => $record, 'step' => $walk['next_index']]) }}"
                   class="flex-[2] min-w-0 text-center rounded-xl bg-brand text-white font-bold text-sm py-3 px-2 leading-snug whitespace-normal">Continue</a>
            @elseif ($blockDecision)
                <a href="{{ route('admin.loan-applications.guided-committee', ['loan_application' => $record, 'step' => 1]) }}"
                   class="flex-[2] min-w-0 text-center rounded-xl bg-amber-100 text-amber-950 font-bold text-sm py-3 px-2 leading-snug whitespace-normal">Acknowledge material exceptions first</a>
            @else
                <a href="{{ $walk['decision_href'] }}"
                   class="flex-[2] min-w-0 text-center rounded-xl bg-brand text-white font-bold text-sm py-3 px-2 leading-snug whitespace-normal">Committee decision</a>
            @endif
        </div>
    </div>
    </x-slot:footer>
</x-admin.guided-review-shell>
