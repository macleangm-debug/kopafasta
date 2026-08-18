@php
    $customer = $review['customer'] ?? null;
    $product = $review['product'] ?? null;
    $linkedLoan = $linkedLoan ?? $record->loan;
    $isServicingFile = $isServicingFile ?? $record->hasActiveFacility();
    $servicing = ($isServicingFile && $linkedLoan)
        ? app(\App\Services\ActiveLoanServicingService::class)->forLoan($linkedLoan)
        : null;
    $workspace = request('workspace');
    $allowedWorkspaces = $isServicingFile ? ['facility', 'documents', 'letters'] : ['release', 'documents', 'letters'];
    if ($workspace === 'letters') {
        $workspace = 'documents';
    }
    if (! in_array($workspace, $allowedWorkspaces, true)) {
        $workspace = $isServicingFile ? 'facility' : 'release';
    }
    $section = request('section');
    $allowedSections = ['owed', 'upcoming', 'schedule', 'follow-up'];
    if (! in_array($section, $allowedSections, true)) {
        $section = 'owed';
    }
    $workspaceUrl = function (string $key, ?string $sectionKey = null) use ($record, $section) {
        return route('admin.loan-applications.show', array_filter([
            'loan_application' => $record,
            'workspace' => $key,
            'section' => $key === 'facility' ? ($sectionKey ?? $section) : null,
        ])).'#credit-workspace';
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
                Outstanding, signed contract, schedule and collections on the live loan — not screening or committee work.
            @else
                Offer → fees → destination → contract → disbursement. Screening evidence stays on the underwriting desks.
            @endif
        </p>
    </div>

    @if ($isServicingFile && $linkedLoan)
        @include('admin.loan-applications.review._management_summary_cards', [
            'record' => $record,
            'customer' => $customer,
            'product' => $product,
            'linkedLoan' => $linkedLoan,
            'servicing' => $servicing,
            'signedContract' => $signedContract ?? null,
            'workspaceUrl' => $workspaceUrl,
            'previewMode' => 'admin',
        ])
    @endif

    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <nav class="flex gap-1 overflow-x-auto px-2 pt-2 border-b border-gray-100" aria-label="Credit management workspace">
            @foreach (($isServicingFile
                ? ['facility' => 'Facility', 'documents' => 'Documents']
                : ['release' => 'Release', 'documents' => 'Documents']
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
                @include('admin.loan-applications.review._facility_tab', ['section' => $section, 'workspaceUrl' => $workspaceUrl])
            @elseif ($workspace === 'documents')
                @include('admin.loan-applications.review._file_letters', [
                    'offerLetter' => $offer ?? null,
                    'loanContract' => $contract ?? null,
                    'finalContract' => $finalContract ?? null,
                    'signedContract' => $signedContract ?? null,
                    'rejectionLetter' => null,
                    'allowMutations' => ! $isServicingFile,
                    'featureSignedContract' => $isServicingFile,
                    'embedDocuments' => true,
                    'documentCards' => true,
                ])
            @else
                @include('admin.loan-applications.review._ops_workspace')
            @endif
        </div>
    </div>
</section>
