@php
    $offerLetter = $offerLetter ?? ($offer ?? null);
    $loanContract = $loanContract ?? ($contract ?? null);
    $rejectionLetter = $rejectionLetter ?? null;
    $allowMutations = $allowMutations ?? false;
@endphp

<div class="space-y-5">
    @if ($rejectionLetter?->file_path)
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Rejected feedback letter</p>
                    <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $rejectionLetter->reference }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">Sent to the applicant · {{ optional($rejectionLetter->sent_at ?? $record->updated_at)->format('d M Y') }}</p>
                </div>
                <x-admin.document-preview
                    :url="route('admin.loan-agreements.download', $rejectionLetter)"
                    label="Open letter" />
            </div>
            <iframe src="{{ route('admin.loan-agreements.download', $rejectionLetter) }}"
                    class="w-full min-h-[70vh] bg-gray-50"
                    title="Rejected feedback letter"></iframe>
        </div>
    @elseif (($closedStatus ?? $record->closedStatus()) === 'rejected')
        <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 text-sm text-amber-950">
            <p class="font-semibold">No rejection letter is on file yet</p>
            <p class="mt-1 text-amber-900/80">The decision reason below is still on the file. The PDF is generated when the rejection is sent to the applicant.</p>
        </div>
    @endif

    @if ($offerLetter)
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Offer letter</p>
                    <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $offerLetter->reference }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $offerLetter->isSigned() ? 'Accepted by borrower' : display_label($offerLetter->status, 'agreement_status') }}
                        @if ($record->offer_responded_at)
                            · {{ $record->offer_responded_at->format('d M Y') }}
                        @endif
                    </p>
                </div>
                @if ($offerLetter->file_path)
                    <x-admin.document-preview
                        :url="route('admin.loan-agreements.download', $offerLetter)"
                        label="Open offer letter" />
                @endif
            </div>
            @if ($offerLetter->file_path)
                <iframe src="{{ route('admin.loan-agreements.download', $offerLetter) }}"
                        class="w-full min-h-[70vh] bg-gray-50"
                        title="Offer letter"></iframe>
            @endif
        </div>
    @elseif (! $rejectionLetter)
        <div class="rounded-2xl bg-gray-50 ring-1 ring-gray-200 px-5 py-4 text-sm text-gray-700">
            No offer letter on this file yet.
        </div>
    @endif

    @if ($loanContract?->file_path)
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">Loan contract</p>
                    <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $loanContract->reference }}</p>
                </div>
                <x-admin.document-preview
                    :url="route('admin.loan-agreements.download', $loanContract)"
                    label="Open contract" />
            </div>
            <iframe src="{{ route('admin.loan-agreements.download', $loanContract) }}"
                    class="w-full min-h-[70vh] bg-gray-50"
                    title="Loan contract"></iframe>
        </div>
    @endif

    @if ($allowMutations)
        @include('admin.loan-applications.review._contract')
    @endif
</div>
