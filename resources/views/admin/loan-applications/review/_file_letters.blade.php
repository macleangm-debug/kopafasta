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

    $previewTabs = [];
    if ($rejectionLetter?->file_path) {
        $previewTabs[] = [
            'key' => 'rejection',
            'label' => 'Decision letter',
            'eyebrow' => 'Rejected feedback letter',
            'reference' => $rejectionLetter->reference,
            'caption' => 'Sent to the applicant · '.optional($rejectionLetter->sent_at ?? $record->updated_at)->format('d M Y'),
            'url' => $letterUrl($rejectionLetter),
            'preview_label' => 'Open letter',
            'use_admin_preview' => $useAdminPreview,
        ];
    }
    if ($featureSignedContract && $signedContract?->file_path) {
        $previewTabs[] = [
            'key' => 'signed',
            'label' => $signedContract->document_type === 'final_loan_contract' ? 'Signed contract' : 'Signed loan contract',
            'eyebrow' => $signedContract->document_type === 'final_loan_contract' ? 'Signed contract' : 'Signed loan contract',
            'reference' => $signedContract->reference,
            'caption' => ($signedContract->document_type === 'final_loan_contract' ? 'Executed contract + repayment schedule' : 'Accepted by borrower')
                .($signedContract->signed_at ? ' · '.$signedContract->signed_at->format('d M Y') : ''),
            'url' => $letterUrl($signedContract),
            'preview_label' => 'Open signed contract',
            'use_admin_preview' => $useAdminPreview,
        ];
    }
    if ($offerLetter?->file_path) {
        $previewTabs[] = [
            'key' => 'offer',
            'label' => 'Offer letter',
            'eyebrow' => 'Offer letter',
            'reference' => $offerLetter->reference,
            'caption' => ($offerLetter->isSigned() ? 'Accepted by borrower' : display_label($offerLetter->status, 'agreement_status'))
                .($record?->offer_responded_at ? ' · '.$record->offer_responded_at->format('d M Y') : ''),
            'url' => $letterUrl($offerLetter),
            'preview_label' => 'Open offer letter',
            'use_admin_preview' => $useAdminPreview,
        ];
    }
    if ($showOriginalContract && ! ($featureSignedContract && $signedContract?->file_path)) {
        $previewTabs[] = [
            'key' => 'contract',
            'label' => 'Loan contract',
            'eyebrow' => 'Loan contract',
            'reference' => $loanContract->reference,
            'caption' => display_label($loanContract->status, 'agreement_status'),
            'url' => $letterUrl($loanContract),
            'preview_label' => 'Open contract',
            'use_admin_preview' => $useAdminPreview,
        ];
    }

    $defaultTab = collect($previewTabs)->pluck('key')->first();
@endphp

<div class="space-y-5">
    @if ($documentCards)
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Documents</h3>
            <p class="text-xs text-gray-500 mt-0.5">One document at a time in an A4 holder. Switch between the signed contract and the offer letter.</p>
        </div>
    @endif

    @if ($rejectionLetter?->file_path && ! $showEmbeddedPdf)
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Rejected feedback letter</p>
                    <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $rejectionLetter->reference }}</p>
                </div>
                <x-admin.letter-actions :url="$letterUrl($rejectionLetter)" preview-label="Open letter" :use-admin-preview="$useAdminPreview" />
            </div>
        </div>
    @elseif (($closedStatus ?? $record?->closedStatus()) === 'rejected' && ! $rejectionLetter?->file_path)
        <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 text-sm text-amber-950">
            <p class="font-semibold">No rejection letter is on file yet</p>
            <p class="mt-1 text-amber-900/80">The decision reason below is still on the file. The PDF is generated when the rejection is sent to the applicant.</p>
        </div>
    @endif

    @if ($featureSignedContract && ! $signedContract?->file_path)
        <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 text-sm text-amber-950">
            <p class="font-semibold">No signed contract on file yet</p>
            <p class="mt-1 text-amber-900/80">The executed contract is generated at disbursement. The offer letter is still on the file.</p>
        </div>
    @endif

    @if ($showEmbeddedPdf && $previewTabs !== [])
        <x-admin.document-holder :tabs="$previewTabs" :active="$defaultTab" />
    @elseif (! $showEmbeddedPdf)
        @if ($featureSignedContract && $signedContract?->file_path)
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden">
                <div class="px-5 py-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">
                            {{ $signedContract->document_type === 'final_loan_contract' ? 'Signed contract' : 'Signed loan contract' }}
                        </p>
                        <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $signedContract->reference }}</p>
                    </div>
                    <x-admin.letter-actions :url="$letterUrl($signedContract)" preview-label="Open signed contract" :use-admin-preview="$useAdminPreview" />
                </div>
            </div>
        @endif
        @if ($offerLetter?->file_path)
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden">
                <div class="px-5 py-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Offer letter</p>
                        <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $offerLetter->reference }}</p>
                    </div>
                    <x-admin.letter-actions :url="$letterUrl($offerLetter)" preview-label="Open offer letter" :use-admin-preview="$useAdminPreview" />
                </div>
            </div>
        @elseif (! $rejectionLetter)
            <div class="rounded-2xl bg-gray-50 ring-1 ring-gray-200 px-5 py-4 text-sm text-gray-700">
                No offer letter on this file yet.
            </div>
        @endif
        @if ($showOriginalContract)
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden">
                <div class="px-5 py-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Loan contract</p>
                        <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $loanContract->reference }}</p>
                    </div>
                    <x-admin.letter-actions :url="$letterUrl($loanContract)" preview-label="Open contract" :use-admin-preview="$useAdminPreview" />
                </div>
            </div>
        @endif
    @elseif (! $offerLetter && ! $rejectionLetter && ! ($featureSignedContract && $signedContract?->file_path) && ! $showOriginalContract)
        <div class="rounded-2xl bg-gray-50 ring-1 ring-gray-200 px-5 py-4 text-sm text-gray-700">
            No offer letter on this file yet.
        </div>
    @endif

    @if ($allowMutations)
        @include('admin.loan-applications.review._contract')
    @endif
</div>
