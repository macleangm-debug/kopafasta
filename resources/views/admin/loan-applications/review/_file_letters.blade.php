@php
    $offerLetter = $offerLetter ?? ($offer ?? null);
    $loanContract = $loanContract ?? ($contract ?? null);
    $finalContract = $finalContract ?? null;
    $signedContract = $signedContract ?? null;
    $rejectionLetter = $rejectionLetter ?? null;
    $allowMutations = $allowMutations ?? false;
    $featureSignedContract = $featureSignedContract ?? false;
    $useAdminPreview = $useAdminPreview ?? true;
    $embedDocuments = $embedDocuments ?? $useAdminPreview;
    $documentCards = $documentCards ?? false;
    $letterDownloadUrl = $letterDownloadUrl ?? fn ($agreement) => route('admin.loan-agreements.download', $agreement);
    $showEmbeddedPdf = $documentCards || $embedDocuments;
    $letterUrl = function ($agreement) use ($letterDownloadUrl) {
        $url = $letterDownloadUrl($agreement);
        $version = (string) (data_get($agreement->snapshot, 'document_version')
            ?: $agreement->updated_at?->timestamp
            ?: 'branded');

        return $url.(str_contains((string) $url, '?') ? '&' : '?').'v='.rawurlencode($version);
    };

    if (! $signedContract && $featureSignedContract) {
        $signedContract = ($finalContract?->file_path ? $finalContract : null)
            ?? ($loanContract && $loanContract->isSigned() && $loanContract->file_path ? $loanContract : null)
            ?? $finalContract
            ?? ($loanContract?->isSigned() ? $loanContract : null);
    }

    $signedId = $signedContract?->id;
    $showOriginalContract = $loanContract?->file_path
        && (! $featureSignedContract || (int) $loanContract->id !== (int) $signedId);
@endphp

<div class="space-y-5">
    @if ($documentCards)
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Documents</h3>
            <p class="text-xs text-gray-500 mt-0.5">Offer letter and contract as PDF — preview here or download.</p>
        </div>
    @endif
    @if ($rejectionLetter?->file_path)
        @php $url = $letterUrl($rejectionLetter); @endphp
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Rejected feedback letter</p>
                    <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $rejectionLetter->reference }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">Sent to the applicant · {{ optional($rejectionLetter->sent_at ?? $record->updated_at)->format('d M Y') }}</p>
                </div>
                <x-admin.letter-actions :url="$url" preview-label="Open letter" :use-admin-preview="$useAdminPreview" />
            </div>
            @if ($showEmbeddedPdf)
                <iframe src="{{ $url }}"
                        class="w-full min-h-[80vh] bg-gray-50"
                        title="Rejected feedback letter"></iframe>
            @endif
        </div>
    @elseif (($closedStatus ?? $record?->closedStatus()) === 'rejected')
        <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 text-sm text-amber-950">
            <p class="font-semibold">No rejection letter is on file yet</p>
            <p class="mt-1 text-amber-900/80">The decision reason below is still on the file. The PDF is generated when the rejection is sent to the applicant.</p>
        </div>
    @endif

    @if ($featureSignedContract)
        @if ($signedContract?->file_path)
            @php
                $signedTitle = $signedContract->document_type === 'final_loan_contract'
                    ? 'Signed contract'
                    : 'Signed loan contract';
                $url = $letterUrl($signedContract);
            @endphp
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">{{ $signedTitle }}</p>
                        <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $signedContract->reference }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $signedContract->document_type === 'final_loan_contract' ? 'Executed contract + repayment schedule' : 'Accepted by borrower' }}
                            @if ($signedContract->signed_at)
                                · {{ $signedContract->signed_at->format('d M Y') }}
                            @endif
                        </p>
                    </div>
                    <x-admin.letter-actions :url="$url" preview-label="Open signed contract" :use-admin-preview="$useAdminPreview" />
                </div>
                @if ($showEmbeddedPdf)
                    <iframe src="{{ $url }}"
                            class="w-full min-h-[80vh] bg-gray-50"
                            title="Signed contract"></iframe>
                @endif
            </div>
        @else
            <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 text-sm text-amber-950">
                <p class="font-semibold">No signed contract on file yet</p>
                <p class="mt-1 text-amber-900/80">The executed contract is generated at disbursement. The offer letter below is still on the file.</p>
            </div>
        @endif
    @endif

    @if ($offerLetter)
        @php $url = $offerLetter->file_path ? $letterUrl($offerLetter) : null; @endphp
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Offer letter</p>
                    <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $offerLetter->reference }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $offerLetter->isSigned() ? 'Accepted by borrower' : display_label($offerLetter->status, 'agreement_status') }}
                        @if ($record?->offer_responded_at)
                            · {{ $record->offer_responded_at->format('d M Y') }}
                        @endif
                    </p>
                </div>
                @if ($url)
                    <x-admin.letter-actions :url="$url" preview-label="Open offer letter" :use-admin-preview="$useAdminPreview" />
                @endif
            </div>
            @if ($url && $showEmbeddedPdf)
                <iframe src="{{ $url }}"
                        class="w-full min-h-[80vh] bg-gray-50"
                        title="Offer letter"></iframe>
            @endif
        </div>
    @elseif (! $rejectionLetter)
        <div class="rounded-2xl bg-gray-50 ring-1 ring-gray-200 px-5 py-4 text-sm text-gray-700">
            No offer letter on this file yet.
        </div>
    @endif

    @if ($showOriginalContract)
        @php $url = $letterUrl($loanContract); @endphp
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Loan contract</p>
                    <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $loanContract->reference }}</p>
                </div>
                <x-admin.letter-actions :url="$url" preview-label="Open contract" :use-admin-preview="$useAdminPreview" />
            </div>
            @if ($showEmbeddedPdf)
                <iframe src="{{ $url }}"
                        class="w-full min-h-[80vh] bg-gray-50"
                        title="Loan contract"></iframe>
            @endif
        </div>
    @endif

    @if ($allowMutations)
        @include('admin.loan-applications.review._contract')
    @endif
</div>
