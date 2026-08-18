@php
    $customer = $review['customer'] ?? null;
    $product = $review['product'] ?? null;
    $closedStatus = $closedStatus ?? $record->closedStatus();
    $isRejected = $closedStatus === 'rejected';
    $workspace = request('workspace');
    $allowedWorkspaces = $isRejected ? ['decision', 'letter'] : ['decision'];
    if (! in_array($workspace, $allowedWorkspaces, true)) {
        $workspace = 'decision';
    }
    $workspaceUrl = function (string $key) use ($record) {
        return route('admin.loan-applications.show', [
            'loan_application' => $record,
            'workspace' => $key,
        ]).'#credit-workspace';
    };
    $rejectionCodes = collect($record->rejection_reason_codes ?? [])->filter()->values();
@endphp

<section id="credit-workspace" class="space-y-4 mb-6 scroll-mt-24">
    <div>
        <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">
            {{ $isRejected ? 'Rejected application' : 'Closed file' }}
        </p>
        <h2 class="text-lg font-bold text-gray-900 mt-0.5">
            {{ $isRejected ? 'Decision on file' : 'Application record' }}
        </h2>
        <p class="text-sm text-gray-500 mt-0.5">
            This file is {{ display_label($closedStatus, 'application_status') }}. It is view-only — no further credit actions.
        </p>
    </div>

    <div class="grid lg:grid-cols-12 gap-4">
        <div class="lg:col-span-4 rounded-2xl overflow-hidden shadow-sm ring-1 ring-black/5 bg-gradient-to-br {{ $isRejected ? 'from-rose-600 to-rose-800' : 'from-slate-600 to-slate-800' }} text-white">
            <div class="px-5 py-5">
                <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Outcome</p>
                <p class="text-2xl font-bold mt-1">{{ display_label($closedStatus, 'application_status') }}</p>
                <p class="text-sm text-white/80 mt-3">
                    {{ $product?->name ?? '—' }}
                    · {{ format_money((float) ($record->requested_amount ?? 0)) }}
                </p>
            </div>
        </div>

        <div class="lg:col-span-5 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm px-5 py-5">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $isRejected ? 'Decision reason' : 'Status' }}</p>
            <p class="text-sm font-semibold text-gray-900 mt-2 leading-relaxed">
                {{ $record->rejection_reason ?: ($isRejected ? 'No borrower-facing reason stored.' : display_label($closedStatus, 'application_status')) }}
            </p>
            @if ($rejectionCodes->isNotEmpty())
                <ul class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($rejectionCodes as $code)
                        <li class="inline-flex rounded-full bg-rose-50 text-rose-900 ring-1 ring-rose-200 px-2.5 py-1 text-[11px] font-semibold">
                            {{ display_label($code, 'rejection_reason') }}
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($record->rejection_advice)
                <p class="mt-3 text-xs text-gray-600">Advice to applicant: {{ $record->rejection_advice }}</p>
            @endif
        </div>

        <div class="lg:col-span-3 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm px-5 py-5">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Applicant</p>
            <p class="text-sm font-semibold text-gray-900 mt-2 truncate">{{ $customer?->full_name ?? '—' }}</p>
            <p class="text-xs text-gray-500 mt-1 font-mono">{{ $customer?->member_no ?? '—' }}</p>
            <p class="text-xs text-gray-500 mt-3">Decided {{ optional($record->updated_at)->format('d M Y') }}</p>
        </div>
    </div>

    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <nav class="flex gap-1 overflow-x-auto px-2 pt-2 border-b border-gray-100" aria-label="Closed file">
            @foreach (['decision' => 'Decision', 'letter' => 'Feedback letter'] as $key => $label)
                @continue($key === 'letter' && ! $isRejected)
                <a href="{{ $workspaceUrl($key) }}"
                   @class([
                       'shrink-0 px-4 py-3 text-sm font-semibold rounded-t-xl border-b-2 transition',
                       'border-brand text-brand bg-brand-muted/40' => $workspace === $key,
                       'border-transparent text-gray-600 hover:text-brand hover:bg-gray-50' => $workspace !== $key,
                   ])
                   @if ($workspace === $key) aria-current="page" @endif>
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="p-4 sm:p-5 space-y-4">
            @if ($workspace === 'letter')
                @include('admin.loan-applications.review._file_letters', [
                    'offerLetter' => null,
                    'loanContract' => null,
                    'rejectionLetter' => $rejectionLetter ?? null,
                    'allowMutations' => false,
                ])
            @else
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                        <dt class="text-[10px] uppercase tracking-widest text-gray-500">Requested</dt>
                        <dd class="font-semibold text-gray-900 mt-1">{{ format_money((float) $record->requested_amount) }} · {{ $record->requested_tenure_months }} months</dd>
                    </div>
                    <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                        <dt class="text-[10px] uppercase tracking-widest text-gray-500">Submitted</dt>
                        <dd class="font-semibold text-gray-900 mt-1">{{ optional($record->submitted_at)->format('d M Y') ?? '—' }}</dd>
                    </div>
                    @if ($record->rejection_internal_notes)
                        <div class="sm:col-span-2 rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                            <dt class="text-[10px] uppercase tracking-widest text-gray-500">Internal notes</dt>
                            <dd class="text-gray-800 mt-1 whitespace-pre-line">{{ $record->rejection_internal_notes }}</dd>
                        </div>
                    @endif
                </dl>
                <p class="text-sm text-gray-500">No review checklist and no workflow actions on a closed file.</p>
            @endif
        </div>
    </div>
</section>
