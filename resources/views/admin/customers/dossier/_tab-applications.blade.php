@php
    $draftService = app(\App\Services\LoanApplicationDraftService::class);
    $guarantorService = app(\App\Services\GuarantorInvitationService::class);
    $filter = request('app_filter', 'all');
    $apps = collect($dossier['applications'] ?? []);
    $categorize = function ($app) {
        $status = (string) ($app->status ?? '');
        $stage = (string) ($app->current_stage ?? '');
        if ($status === 'awaiting_guarantor' || $stage === 'awaiting_guarantor') {
            return 'awaiting_guarantor';
        }
        if (in_array($status, ['rejected', 'expired', 'withdrawn', 'cancelled'], true) || $stage === 'rejected') {
            return 'rejected';
        }
        if (in_array($status, ['approved', 'awaiting_offer', 'offer_issued', 'offer_accepted', 'contract_signed'], true)
            || in_array($stage, ['approved', 'awaiting_offer', 'offer_issued'], true)) {
            return 'approved';
        }
        if (in_array($status, ['disbursed', 'active'], true) || $stage === 'disbursed') {
            return 'disbursed';
        }
        if (in_array($status, ['closed', 'completed', 'settled'], true)) {
            return 'closed';
        }
        if (in_array($stage, ['submitted', 'screening', 'credit_appraisal'], true)
            || in_array($status, ['submitted', 'under_review', 'screening'], true)) {
            return 'screening';
        }

        return 'in_progress';
    };
    $stateLabel = function ($app) use ($categorize) {
        return match ($categorize($app)) {
            'awaiting_guarantor' => 'Awaiting guarantor',
            'rejected' => 'Rejected',
            'approved' => 'Approved / Offer',
            'disbursed' => 'Disbursed',
            'closed' => 'Closed',
            'screening' => 'Submitted / Screening',
            default => 'In progress',
        };
    };
    $counts = [
        'all' => $apps->count(),
        'in_progress' => $apps->filter(fn ($a) => $categorize($a) === 'in_progress')->count(),
        'awaiting_guarantor' => $apps->filter(fn ($a) => $categorize($a) === 'awaiting_guarantor')->count(),
        'screening' => $apps->filter(fn ($a) => $categorize($a) === 'screening')->count(),
        'approved' => $apps->filter(fn ($a) => $categorize($a) === 'approved')->count(),
        'rejected' => $apps->filter(fn ($a) => $categorize($a) === 'rejected')->count(),
        'disbursed' => $apps->filter(fn ($a) => $categorize($a) === 'disbursed')->count(),
        'closed' => $apps->filter(fn ($a) => $categorize($a) === 'closed')->count(),
    ];
    $filtered = $filter === 'all' ? $apps : $apps->filter(fn ($a) => $categorize($a) === $filter)->values();
    $filterUrl = fn (string $key) => route('admin.customers.show', [
        'customer' => $dossier['customer'],
        'tab' => 'applications',
        'app_filter' => $key,
    ]).'#member-file';
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap gap-2">
        @foreach ([
            'all' => 'All',
            'in_progress' => 'In progress',
            'awaiting_guarantor' => 'Awaiting guarantor',
            'screening' => 'Submitted / Screening',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'disbursed' => 'Disbursed',
            'closed' => 'Closed',
        ] as $key => $label)
            <a href="{{ $filterUrl($key) }}"
               @class([
                   'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold ring-1',
                   'bg-brand text-white ring-brand' => $filter === $key,
                   'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50' => $filter !== $key,
               ])>
                {{ $label }}
                <span class="tabular-nums opacity-80">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    @if (($dossier['application_drafts'] ?? collect())->isNotEmpty() && in_array($filter, ['all', 'in_progress'], true))
        <div class="rounded-xl bg-sky-50 ring-1 ring-sky-200 p-4">
            <h3 class="text-sm font-semibold text-sky-900 mb-3">Started — not yet submitted</h3>
            <div class="space-y-3">
                @foreach ($dossier['application_drafts'] as $draft)
                    @php $badge = $draftService->statusBadge($draft); @endphp
                    <div class="rounded-lg bg-white/80 ring-1 ring-sky-100 px-3 py-3 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $draft->product?->name ?? 'Draft application' }}</p>
                            <p class="text-xs text-sky-800 mt-0.5">{{ $badge['label'] }} · In progress</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold tabular-nums text-gray-900">{{ ($amount = $draftService->requestedAmount($draft)) ? format_money($amount) : '—' }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">Last activity {{ $draft->saved_at?->diffForHumans() ?? '—' }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($filtered->isEmpty())
        <p class="text-sm text-gray-500">No applications in this category.</p>
    @else
        <div class="space-y-4">
            @foreach ($filtered as $app)
                @php
                    $amount = (float) ($app->offered_amount ?: $app->requested_amount);
                    $date = $app->submitted_at ?? $app->created_at;
                    $loan = $app->loan;
                    $guarantors = $app->customerGuarantors ?? collect();
                @endphp
                <article class="rounded-2xl ring-1 ring-brand/10 bg-white overflow-hidden">
                    <div class="px-4 py-4 sm:px-5 flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-mono text-xs text-gray-500">{{ $app->application_number }}</p>
                            <h4 class="text-base font-bold text-gray-900 mt-0.5">{{ $app->product?->name ?? 'Application' }}</h4>
                            <p class="text-xs text-gray-500 mt-1">{{ $stateLabel($app) }} · {{ display_label($app->current_stage ?? $app->status, 'application_stage') }}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-lg font-bold tabular-nums text-gray-900">{{ format_money($amount) }}</p>
                            <p class="text-xs text-gray-500 mt-1 whitespace-nowrap">{{ optional($date)->format('d M Y') }}</p>
                        </div>
                    </div>

                    @if ($guarantors->isNotEmpty())
                        <div class="px-4 sm:px-5 pb-4 border-t border-gray-50 pt-3 space-y-2">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Guarantor</p>
                            @foreach ($guarantors as $link)
                                @php
                                    $invite = $link->invitation;
                                    $gCustomer = $invite?->guarantorCustomer;
                                    $status = $guarantorService->workflowStatus($link, $invite);
                                    $name = $invite?->invitee_name
                                        ?? $gCustomer?->full_name
                                        ?? $link->displayName()
                                        ?? 'Guarantor';
                                    $memberRef = $gCustomer?->member_no
                                        ?? $gCustomer?->customer_number
                                        ?? $invite?->membership_id
                                        ?? null;
                                @endphp
                                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                    <div>
                                        @if ($gCustomer)
                                            <a href="{{ route('admin.customers.show', $gCustomer) }}" class="font-semibold text-brand hover:underline">{{ $name }}</a>
                                        @else
                                            <span class="font-semibold text-gray-900">{{ $name }}</span>
                                        @endif
                                        @if ($memberRef)
                                            <span class="block text-xs text-gray-500 mt-0.5">Member {{ $memberRef }}</span>
                                        @endif
                                    </div>
                                    <span class="text-xs font-semibold text-gray-700">{{ $status['label'] ?? display_label($link->status, 'guarantor_status') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="px-4 sm:px-5 py-3 bg-brand-muted/20 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
                        <div class="text-xs text-gray-600">
                            @if ($loan)
                                Resulting loan
                                <a href="{{ route('admin.loans.show', $loan) }}" class="font-semibold text-brand hover:underline ml-1">
                                    {{ $loan->loan_number }} →
                                </a>
                            @else
                                <span class="text-gray-400">No resulting loan yet</span>
                            @endif
                        </div>
                        <a href="{{ route('admin.loan-applications.show', $app) }}" class="text-xs font-semibold text-brand hover:underline">Open application →</a>
                    </div>
                </article>
            @endforeach
        </div>
        <p class="text-xs text-slate-500">Showing {{ $filtered->count() }} of {{ $counts['all'] }} applications.</p>
    @endif
</div>
