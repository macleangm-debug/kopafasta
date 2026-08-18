@php
    $customer = $review['customer'] ?? null;
    $product = $review['product'] ?? null;
    $linkedLoan = $linkedLoan ?? $record->loan;
    $isServicingFile = $isServicingFile ?? $record->hasActiveFacility();
    $servicing = ($isServicingFile && $linkedLoan)
        ? app(\App\Services\ActiveLoanServicingService::class)->forLoan($linkedLoan)
        : null;
    $workspace = request('workspace');
    $allowedWorkspaces = $isServicingFile ? ['facility', 'letters'] : ['release', 'letters'];
    if (! in_array($workspace, $allowedWorkspaces, true)) {
        $workspace = $isServicingFile ? 'facility' : 'release';
    }
    $workspaceUrl = function (string $key) use ($record) {
        return route('admin.loan-applications.show', [
            'loan_application' => $record,
            'workspace' => $key,
        ]).'#credit-workspace';
    };

    $healthInArrears = (bool) ($servicing['in_arrears'] ?? false);
    $healthTone = match (true) {
        ($linkedLoan?->status === 'defaulted') => 'from-rose-700 to-rose-900',
        $healthInArrears => 'from-amber-500 to-amber-700',
        default => 'from-emerald-600 to-emerald-800',
    };
    $offerStatus = (string) ($record->offer_status ?: ($offer?->isSigned() ? 'accepted' : 'pending_borrower'));
    $offerLabel = match ($offerStatus) {
        'accepted' => 'Accepted',
        'declined' => 'Declined',
        'pending_borrower' => 'Pending borrower',
        default => str_replace('_', ' ', ucfirst($offerStatus)),
    };
    $offerTone = match ($offerStatus) {
        'accepted' => 'from-emerald-600 to-emerald-800',
        'declined' => 'from-rose-600 to-rose-800',
        default => 'from-brand to-brand-light',
    };
@endphp

<section id="credit-workspace" class="space-y-4 mb-6 scroll-mt-24">
    <div>
        <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Credit management workspace</p>
        <h2 class="text-lg font-bold text-gray-900 mt-0.5">
            @if ($isServicingFile)
                {{ $linkedLoan && $linkedLoan->status === 'arrears' ? 'Loan in arrears' : ($linkedLoan && $linkedLoan->status === 'defaulted' ? 'Defaulted facility' : 'Ongoing loan') }}
            @else
                Approved facility
            @endif
        </h2>
        <p class="text-sm text-gray-500 mt-0.5">
            @if ($isServicingFile)
                Outstanding, schedule, letters and collections on the live loan — not screening or committee work.
            @else
                Offer → fees → destination → contract → disbursement. Screening evidence stays on the underwriting desks.
            @endif
        </p>
    </div>

    @if ($isServicingFile && $linkedLoan)
        <div class="grid lg:grid-cols-12 gap-4">
            <div class="lg:col-span-3 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
                <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-4 text-white">
                    <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">Outstanding</p>
                    <p class="text-2xl font-bold mt-1 tabular-nums">{{ format_money((float) ($servicing['outstanding_balance'] ?? $linkedLoan->outstanding_balance ?? 0)) }}</p>
                    <p class="text-sm text-white/75 mt-1">
                        {{ $servicing['tenure_months'] ?? $linkedLoan->tenure_months }} months
                        @if ($product) · {{ $product->name }} @endif
                    </p>
                </div>
                <div class="px-5 py-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500">Loan</p>
                        <p class="font-semibold text-gray-900 mt-0.5 font-mono text-xs">{{ $linkedLoan->loan_number }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500">Principal</p>
                        <p class="font-semibold text-gray-900 mt-0.5">{{ format_money((float) $linkedLoan->principal_amount) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500">Paid</p>
                        <p class="font-semibold text-gray-900 mt-0.5">{{ number_format((float) ($servicing['progress_pct'] ?? 0), 0) }}%</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500">Disbursed</p>
                        <p class="font-semibold text-gray-900 mt-0.5">{{ optional($linkedLoan->disbursement_date ?? $record->disbursed_at)->format('d M Y') ?? '—' }}</p>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-3 rounded-2xl overflow-hidden shadow-sm ring-1 ring-black/5 bg-gradient-to-br {{ $healthTone }} text-white px-5 py-5">
                <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Repayment health</p>
                <p class="text-2xl font-bold mt-1">{{ $servicing['status_label'] ?? display_label($linkedLoan->status, 'loan_status') }}</p>
                <p class="text-sm text-white/85 mt-3">
                    @if (! empty($servicing['next_due_amount']))
                        Next {{ format_money((float) $servicing['next_due_amount']) }}
                        @if (! empty($servicing['next_due_date']))
                            · {{ \Illuminate\Support\Carbon::parse($servicing['next_due_date'])->format('d M Y') }}
                        @endif
                    @else
                        No remaining instalment
                    @endif
                </p>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="rounded-xl bg-white/10 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-wider text-white/60">Days past due</p>
                        <p class="text-sm font-bold mt-0.5 tabular-nums">{{ (int) ($servicing['days_past_due'] ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl bg-white/10 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-wider text-white/60">In arrears</p>
                        <p class="text-sm font-bold mt-0.5 tabular-nums">{{ format_money((float) ($servicing['amount_in_arrears'] ?? 0)) }}</p>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-3 rounded-2xl overflow-hidden shadow-sm ring-1 ring-black/5 bg-gradient-to-br {{ $offerTone }} text-white px-5 py-5">
                <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Offer letter</p>
                <p class="text-2xl font-bold mt-1">{{ $offerLabel }}</p>
                <p class="text-sm text-white/85 mt-3">
                    {{ format_money((float) ($record->offered_amount ?? $linkedLoan->approved_amount ?? 0)) }}
                    · {{ $record->offered_tenure_months ?? $linkedLoan->tenure_months }} months
                </p>
                <a href="{{ $workspaceUrl('letters') }}"
                   class="mt-4 inline-flex text-xs font-semibold rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 transition">
                    Preview offer letter →
                </a>
            </div>

            <div class="lg:col-span-3 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm px-5 py-5">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Borrower</p>
                <p class="text-lg font-bold text-gray-900 mt-1 truncate">{{ $customer?->full_name ?? '—' }}</p>
                <p class="text-xs text-gray-500 mt-1 font-mono">{{ $customer?->member_no ?? '—' }}</p>
                <p class="text-sm text-gray-700 mt-3">{{ $customer?->phone ?? '—' }}</p>
                <p class="text-xs text-gray-500 mt-1">
                    Instalments {{ (int) ($servicing['installments_paid'] ?? 0) }} / {{ (int) ($servicing['installments_total'] ?? 0) }}
                </p>
            </div>
        </div>
    @endif

    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <nav class="flex gap-1 overflow-x-auto px-2 pt-2 border-b border-gray-100" aria-label="Credit management workspace">
            @foreach (($isServicingFile
                ? ['facility' => 'Facility', 'letters' => 'Letters']
                : ['release' => 'Release', 'letters' => 'Letters']
            ) as $key => $label)
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
            @if ($workspace === 'facility')
                @include('admin.loan-applications.review._facility_tab')
            @elseif ($workspace === 'letters')
                @include('admin.loan-applications.review._file_letters', [
                    'offerLetter' => $offer ?? null,
                    'loanContract' => $contract ?? null,
                    'rejectionLetter' => null,
                    'allowMutations' => ! $isServicingFile,
                ])
            @else
                @include('admin.loan-applications.review._ops_workspace')
            @endif
        </div>
    </div>
</section>
