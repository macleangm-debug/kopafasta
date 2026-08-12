@php
    $readiness = $screeningReadiness ?? null;
    $nextSteps = collect($readiness['next_steps'] ?? [])->take(6)->values();
    $criticalFails = collect($readiness['critical_fails'] ?? [])->take(5)->values();
    $docRequestService = app(\App\Services\ApplicationDocumentRequestService::class);
    $openDocRequests = collect($documentRequests ?? [])
        ->filter(function ($req) use ($docRequestService) {
            if ($docRequestService->isProfileGuidedRequest($req)) {
                return $req->needsBorrowerAction() || $req->status === 'uploaded';
            }

            return in_array($req->status, ['pending', 'uploaded', 'rejected'], true);
        })
        ->take(4)
        ->values();
    $documentsHref = route('admin.loan-applications.show', array_filter([
        'loan_application' => $record,
        'workspace' => 'checklist',
        'desk_phase' => 'capacity',
        'capacity_tab' => 'documents',
    ])).'#checklist-documents';
    $checklistHref = route('admin.loan-applications.show', [
        'loan_application' => $record,
        'workspace' => 'checklist',
    ]).'#review-desk';
@endphp

<section id="committee-sprint" class="mb-5 rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-white shadow-sm scroll-mt-24">
    <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand via-brand-light to-brand text-white">
        <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">Committee sprint</p>
        <h3 class="text-base font-bold mt-0.5">Critical areas — not a full re-screen</h3>
        <p class="text-xs text-white/80 mt-1 max-w-3xl">
            Screening already worked this file item by item. You sprint the high-risk spots on the same evidence,
            change a Pass / Fail only when needed (with a reason), then validate or record a different decision below.
        </p>
    </div>

    <div class="grid lg:grid-cols-3 divide-y lg:divide-y-0 lg:divide-x divide-brand/10">
        <div class="p-5 space-y-3">
            <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">1 · Screening signal</p>
            <p class="text-sm text-gray-700">
                Read CRB · Guarantor · Screening cards above. If screening differs from CRB, start there.
            </p>
            <div class="flex flex-wrap gap-2 text-[11px] font-semibold">
                <span class="rounded-lg bg-brand-muted text-brand px-2.5 py-1 tabular-nums">
                    Checklist {{ (int) ($readiness['checklist_done'] ?? 0) }}/{{ (int) ($readiness['checklist_total'] ?? 0) }}
                </span>
                @if (($readiness['checklist_failed'] ?? 0) > 0)
                    <span class="rounded-lg bg-rose-100 text-rose-950 px-2.5 py-1">
                        {{ (int) $readiness['checklist_failed'] }} fail
                    </span>
                @endif
                @if ($criticalFails->isNotEmpty())
                    <span class="rounded-lg bg-rose-100 text-rose-950 px-2.5 py-1">
                        {{ $criticalFails->count() }} high-risk
                    </span>
                @endif
            </div>
            @if ($criticalFails->isNotEmpty())
                <ul class="space-y-1">
                    @foreach ($criticalFails as $fail)
                        <li class="text-xs text-rose-900 flex gap-2">
                            <span class="font-bold shrink-0">•</span>
                            <span>{{ $fail }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-xs text-gray-500">No high-risk checklist fails flagged for a fast pass.</p>
            @endif
        </div>

        <div class="p-5 space-y-3">
            <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">2 · Sprint links</p>
            <p class="text-sm text-gray-700">Jump only into the areas that still matter — same desk screening used.</p>
            <div class="space-y-2">
                @forelse ($nextSteps as $step)
                    @php
                        $stepTone = match ($step['tone'] ?? 'open') {
                            'gate', 'critical' => 'bg-rose-50 text-rose-950 ring-rose-200',
                            'fail' => 'bg-amber-50 text-amber-950 ring-amber-200',
                            default => 'bg-brand-muted/50 text-brand ring-brand/15',
                        };
                    @endphp
                    <a href="{{ $step['href'] }}"
                       class="block rounded-xl px-3.5 py-2.5 ring-1 {{ $stepTone }} hover:brightness-95 transition">
                        <p class="text-sm font-bold">{{ $step['label'] }}</p>
                        <p class="text-[11px] opacity-80 mt-0.5">{{ $step['detail'] }}</p>
                    </a>
                @empty
                    <a href="{{ $checklistHref }}"
                       class="inline-flex text-xs font-bold text-brand hover:underline">
                        Open full checklist →
                    </a>
                @endforelse
            </div>
        </div>

        <div class="p-5 space-y-3">
            <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">3 · Change with reason</p>
            <p class="text-sm text-gray-700">
                Edit a checklist verdict when you disagree with screening. Failures need a reason code.
                Document requests and verify/reject live on the same Documents panel.
            </p>
            @if ($openDocRequests->isNotEmpty())
                <div class="rounded-xl bg-gradient-to-r from-brand-gold/20 to-white ring-1 ring-brand-gold/40 px-3.5 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-bold">Open document requests</p>
                    <ul class="mt-1.5 space-y-1">
                        @foreach ($openDocRequests as $req)
                            <li class="text-xs text-gray-800">• {{ $req->label }}</li>
                        @endforeach
                    </ul>
                    <a href="{{ $documentsHref }}" class="inline-flex mt-2 text-xs font-bold text-brand hover:underline">
                        Open Documents · Requests →
                    </a>
                </div>
            @else
                <a href="{{ $documentsHref }}"
                   class="inline-flex text-xs font-bold text-brand hover:underline">
                    Open Documents (grouped by category) →
                </a>
            @endif
            <p class="text-[11px] text-gray-500">
                Final step: validate screening, or differ with a committee rationale in the decision forms below.
            </p>
        </div>
    </div>
</section>
