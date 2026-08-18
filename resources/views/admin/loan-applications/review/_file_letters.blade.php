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
    $letterDownloadUrl = $letterDownloadUrl ?? fn ($agreement) => route('admin.loan-agreements.download', $agreement);

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

@php
    $letterPreview = function ($agreement, string $label) use ($letterDownloadUrl) {
        return [
            'url' => $letterDownloadUrl($agreement),
            'label' => $label,
        ];
    };
@endphp

<div class="space-y-5">
    @if ($rejectionLetter?->file_path)
        @php $preview = $letterPreview($rejectionLetter, 'Open letter'); @endphp
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Rejected feedback letter</p>
                    <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $rejectionLetter->reference }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">Sent to the applicant · {{ optional($rejectionLetter->sent_at ?? $record->updated_at)->format('d M Y') }}</p>
                </div>
                @if ($useAdminPreview)
                    <x-admin.document-preview :url="$preview['url']" :label="$preview['label']" />
                @else
                    <x-site.document-view-button :url="$preview['url']" type="pdf" :label="$preview['label']" class="text-brand hover:underline text-xs font-semibold" />
                @endif
            </div>
            @if ($embedDocuments)
                <iframe src="{{ $preview['url'] }}"
                        class="w-full min-h-[70vh] bg-gray-50"
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
                $preview = $letterPreview($signedContract, 'Open signed contract');
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
                    @if ($useAdminPreview)
                        <x-admin.document-preview :url="$preview['url']" :label="$preview['label']" />
                    @else
                        <x-site.document-view-button :url="$preview['url']" type="pdf" :label="$preview['label']" class="text-brand hover:underline text-xs font-semibold" />
                    @endif
                </div>
                @if ($embedDocuments)
                    <iframe src="{{ $preview['url'] }}"
                            class="w-full min-h-[70vh] bg-gray-50"
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
        @php $preview = $offerLetter->file_path ? $letterPreview($offerLetter, 'Open offer letter') : null; @endphp
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
                @if ($preview)
                    @if ($useAdminPreview)
                        <x-admin.document-preview :url="$preview['url']" :label="$preview['label']" />
                    @else
                        <x-site.document-view-button :url="$preview['url']" type="pdf" :label="$preview['label']" class="text-brand hover:underline text-xs font-semibold" />
                    @endif
                @endif
            </div>
            @if ($preview && $embedDocuments)
                <iframe src="{{ $preview['url'] }}"
                        class="w-full min-h-[70vh] bg-gray-50"
                        title="Offer letter"></iframe>
            @endif
        </div>
    @elseif (! $rejectionLetter)
        <div class="rounded-2xl bg-gray-50 ring-1 ring-gray-200 px-5 py-4 text-sm text-gray-700">
            No offer letter on this file yet.
        </div>
    @endif

    @if ($showOriginalContract)
        @php $preview = $letterPreview($loanContract, 'Open contract'); @endphp
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Loan contract</p>
                    <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $loanContract->reference }}</p>
                </div>
                @if ($useAdminPreview)
                    <x-admin.document-preview :url="$preview['url']" :label="$preview['label']" />
                @else
                    <x-site.document-view-button :url="$preview['url']" type="pdf" :label="$preview['label']" class="text-brand hover:underline text-xs font-semibold" />
                @endif
            </div>
            @if ($embedDocuments)
                <iframe src="{{ $preview['url'] }}"
                        class="w-full min-h-[70vh] bg-gray-50"
                        title="Loan contract"></iframe>
            @endif
        </div>
    @endif

    @if ($allowMutations)
        @include('admin.loan-applications.review._contract')
    @endif
</div>
