@php
    $customer = $review['customer'];
    $product = $review['product'];
    $stage = $record->current_stage ?? 'submitted';
    $isDisbursementStage = in_array($stage, ['disbursement'], true) || $record->status === 'disbursed';
    $readiness = app(\App\Services\ApplicationDisbursementReadinessService::class);
    $checklist = $readiness->managementReleaseChecklist($record);
    $doneCount = collect($checklist)->where('complete', true)->count();
    $totalCount = max(1, count($checklist));
    $readyPct = (int) round(($doneCount / $totalCount) * 100);
    $approvedAmount = (float) ($record->offered_amount ?: $record->recommended_amount ?: $record->requested_amount);
    $offerStatus = (string) ($record->offer_status ?: 'pending_borrower');
    $offerLabel = match ($offerStatus) {
        'accepted' => 'Accepted',
        'declined' => 'Declined',
        'pending_borrower' => 'Pending borrower',
        default => str_replace('_', ' ', ucfirst($offerStatus)),
    };
    $offerTone = match ($offerStatus) {
        'accepted' => 'from-emerald-600 to-emerald-800',
        'declined' => 'from-rose-600 to-rose-800',
        'pending_borrower' => 'from-amber-500 to-amber-700',
        default => 'from-brand to-brand-light',
    };
    $canPushDisburse = $readiness->canMarkDisbursement($record) && $offerStatus !== 'declined';
    $loan = $record->loan;
    $isActiveLoan = $loan && in_array((string) $loan->status, ['active', 'disbursed', 'arrears', 'defaulted'], true);
@endphp

<section class="space-y-4 mb-6" id="credit-management-desk">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Credit management workspace</p>
            <h2 class="text-lg font-bold text-gray-900 mt-0.5">
                {{ $isActiveLoan ? 'Active facility' : ($isDisbursementStage ? 'Release this facility' : 'Management desk') }}
            </h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Offer → fees → destination → contract → disbursement. Capital was settled at committee — this desk tracks borrower completion and release.
            </p>
        </div>
        <span class="text-xs font-semibold rounded-full px-3 py-1.5 bg-brand-gold text-brand ring-1 ring-brand/20">
            {{ $readiness->managementLifecycleLabel($record) }}
        </span>
    </div>

    @if ($offerStatus === 'declined')
        <div class="rounded-2xl bg-rose-50 ring-1 ring-rose-200 px-5 py-4 text-sm text-rose-900">
            <p class="font-bold">Offer declined — file closed</p>
            <p class="mt-1 text-rose-800/80">The borrower declined the offer. No further post-approval steps apply.</p>
        </div>
    @endif

    <div class="grid lg:grid-cols-12 gap-4">
        <div class="lg:col-span-5 rounded-2xl bg-white ring-2 ring-brand-gold shadow-sm overflow-hidden">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-4 text-white">
                <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">Approved facility</p>
                <p class="text-2xl font-bold mt-1 tabular-nums">{{ format_money($approvedAmount) }}</p>
                <p class="text-sm text-white/75 mt-1">
                    {{ $record->offered_tenure_months ?? $record->requested_tenure_months }} months
                    @if ($product) · {{ $product->name }} @endif
                </p>
            </div>
            <div class="px-5 py-4 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">Borrower</p>
                    <p class="font-semibold text-gray-900 mt-0.5 truncate">{{ $customer->full_name }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">Member</p>
                    <p class="font-semibold text-gray-900 mt-0.5 font-mono text-xs">{{ $customer->member_no ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">Approved</p>
                    <p class="font-semibold text-gray-900 mt-0.5">{{ optional($record->approved_at)->format('d M Y') ?? '—' }}</p>
                </div>
                @if ($loan)
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500">Loan</p>
                        <p class="font-semibold text-gray-900 mt-0.5 font-mono text-xs">{{ $loan->loan_number }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="lg:col-span-3 rounded-2xl overflow-hidden shadow-sm ring-1 ring-black/5 bg-gradient-to-br {{ $offerTone }} text-white p-5">
            <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Offer status</p>
            <p class="text-2xl font-bold mt-2 tracking-tight">{{ $offerLabel }}</p>
            @if ($record->offered_amount)
                <p class="text-sm text-white/85 mt-3 tabular-nums">{{ format_money((float) $record->offered_amount) }}</p>
            @else
                <p class="text-sm text-white/80 mt-3">Offer letter issued on approval.</p>
            @endif
        </div>

        <div @class([
            'lg:col-span-4 rounded-2xl ring-1 p-5 shadow-sm',
            'bg-emerald-50 text-emerald-900 ring-emerald-200' => $readyPct >= 100 || $canPushDisburse,
            'bg-amber-50 text-amber-950 ring-amber-200' => $readyPct < 100 && ! $canPushDisburse && $offerStatus !== 'declined',
            'bg-rose-50 text-rose-900 ring-rose-200' => $offerStatus === 'declined',
        ])>
            <p class="text-[10px] uppercase tracking-widest font-semibold opacity-80">Release readiness</p>
            <div class="flex items-end gap-1.5 mt-2">
                <span class="text-4xl font-bold leading-none tabular-nums">{{ $offerStatus === 'declined' ? 0 : $readyPct }}</span>
                <span class="text-sm font-semibold pb-1 opacity-70">%</span>
            </div>
            <p class="text-sm font-bold mt-2">
                @if ($offerStatus === 'declined')
                    Closed
                @elseif ($canPushDisburse)
                    Ready for disbursement queue
                @else
                    {{ $doneCount }} of {{ count($checklist) }} steps complete
                @endif
            </p>
            <div class="mt-3 h-2 rounded-full bg-black/10 overflow-hidden">
                <div class="h-full rounded-full bg-current opacity-70" style="width: {{ $offerStatus === 'declined' ? 4 : max(4, $readyPct) }}%"></div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-brand-gold/30 bg-gradient-to-r from-brand-gold/20 to-white flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Management spine</p>
                <h3 class="text-base font-bold text-gray-900 mt-0.5">Offer · Fees · Destination · Contract · Disbursement</h3>
                <p class="text-xs text-gray-500 mt-0.5">Fee amounts come from product settings (paid / unpaid). Borrower confirms destination after accepting the offer.</p>
            </div>
            @if ($canPushDisburse)
                <span class="text-xs font-semibold rounded-full px-3 py-1 bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200">Ready to push</span>
            @endif
        </div>

        <div class="p-5 grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ($checklist as $key => $item)
                @php
                    $done = (bool) ($item['complete'] ?? false);
                    $status = (string) ($item['status'] ?? ($done ? 'complete' : 'pending'));
                @endphp
                <div @class([
                    'rounded-xl px-4 py-3 ring-1 text-sm',
                    'bg-emerald-50 ring-emerald-200 text-emerald-900' => $done || $status === 'ready',
                    'bg-gray-50 ring-gray-200 text-gray-500' => in_array($status, ['locked', 'not_required'], true),
                    'bg-amber-50 ring-amber-200 text-amber-950' => ! $done && ! in_array($status, ['locked', 'not_required', 'ready'], true),
                ])>
                    <p class="text-[10px] uppercase tracking-widest font-semibold opacity-70">{{ str_replace('_', ' ', $key) }}</p>
                    <p class="font-semibold mt-1">{{ $item['label'] ?? $key }}</p>
                    <p class="text-xs mt-1 opacity-80 capitalize">{{ str_replace('_', ' ', $status) }}</p>
                </div>
            @endforeach
        </div>

        @if (($availableActions ?? collect())->isNotEmpty() && $offerStatus !== 'declined')
            <div class="px-5 pb-5 border-t border-brand/10 pt-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-brand mb-3">Management actions</p>
                @include('admin.loan-applications._workflow_actions')
            </div>
        @endif
    </div>

    @include('admin.loan-applications._loan-link')

    @if ($offerStatus !== 'declined')
        @include('admin.loan-applications.review._contract')
    @endif

    @if ($isActiveLoan && $loan)
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Servicing</p>
                    <h3 class="text-base font-bold text-gray-900 mt-0.5">Active loan · repayments &amp; arrears</h3>
                </div>
                <a href="{{ route('admin.loans.show', $loan) }}"
                   class="inline-flex items-center gap-2 text-sm font-bold rounded-xl bg-brand-gold text-brand hover:brightness-95 px-4 py-2.5 shadow-sm ring-1 ring-brand/15">
                    Open loan file
                </a>
            </div>
            <div class="p-5 grid sm:grid-cols-3 gap-3 text-sm">
                <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Status</p>
                    <p class="font-bold text-gray-900 mt-1 capitalize">{{ str_replace('_', ' ', (string) $loan->status) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Principal</p>
                    <p class="font-bold text-gray-900 mt-1 tabular-nums">{{ format_money((float) ($loan->principal_amount ?? $loan->amount ?? 0)) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Disbursed</p>
                    <p class="font-bold text-gray-900 mt-1">{{ optional($record->disbursed_at ?? $loan->disbursement_date)->format('d M Y') ?? '—' }}</p>
                </div>
            </div>
        </div>
    @endif
</section>
