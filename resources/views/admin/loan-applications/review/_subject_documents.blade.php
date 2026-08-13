@php
    $customer = $review['customer'] ?? null;
    $docs = collect($review['profile_documents'] ?? $review['kyc_documents'] ?? []);
    $docsByCategory = $docs
        ->groupBy(fn ($doc) => strtolower((string) ($doc->documentType?->category ?: 'kyc')))
        ->sortKeys();
    $docReviewService = app(\App\Services\ApplicationDocumentReviewService::class);
    $appReviews = $docReviewService->reviewsForApplication($record);
    $roleLabel = match (true) {
        (bool) ($review['is_member_subject'] ?? false) => (($review['member_row']['role'] ?? '') === 'leader' ? 'Leader' : 'Member'),
        (bool) ($review['is_guarantor_subject'] ?? false) => 'Guarantor',
        default => (($groupReview['is_group'] ?? false) ? 'Leader' : 'Borrower'),
    };
    $docSubjectLabel = match (true) {
        (bool) ($review['is_member_subject'] ?? false) => 'Member documents',
        (bool) ($review['is_guarantor_subject'] ?? false) => 'Guarantor documents',
        default => 'Subject documents',
    };
    $pendingCount = $docs->filter(function ($doc) use ($docReviewService, $record, $appReviews) {
        $status = $appReviews->get($doc->id)?->status
            ?? $docReviewService->statusFor($record, $doc);

        return ! in_array($status, ['verified', 'approved'], true);
    })->count();
@endphp

<section id="review-documents" class="rounded-2xl ring-1 ring-brand/10 bg-white overflow-hidden shadow-sm scroll-mt-24">
    <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand via-brand-light to-brand text-white">
        <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">{{ $docSubjectLabel }}</p>
        <h2 class="text-sm font-semibold text-white mt-0.5">Uploaded documents</h2>
        <p class="text-xs text-white/75 mt-0.5">
            @if ($customer)
                {{ $customer->full_name ?? 'This subject' }} · {{ $roleLabel }}
            @endif
            — open each file, then Mark reviewed or Fail for <span class="text-brand-gold font-semibold">this application only</span>.
            @if ($pendingCount > 0)
                · {{ $pendingCount }} still pending review
            @endif
        </p>
    </div>
    <div class="p-5 space-y-4">
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-3 text-sm text-amber-950">
            These are profile uploads (KYC / income / business). Reviewing them here does not permanently clear them for future applications — a new application starts them as Pending review again.
        </div>

        @if (! $customer)
            <p class="text-sm text-gray-500">Profile documents unlock after this subject finishes onboarding.</p>
        @elseif ($docs->isEmpty())
            <p class="text-sm text-gray-500">No documents on this profile yet.</p>
        @else
            @foreach ($docsByCategory as $cat => $catDocs)
                <div class="rounded-xl ring-1 ring-brand/10 overflow-hidden">
                    <div class="px-3.5 py-2 bg-gradient-to-r from-brand-muted/60 to-white border-b border-brand/10">
                        <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ $cat }}</p>
                    </div>
                    <div class="p-3 grid md:grid-cols-2 gap-3">
                        @foreach ($catDocs as $doc)
                            @include('admin.loan-applications.review._document_review_card', [
                                'doc' => $doc,
                                'appReview' => $appReviews->get($doc->id),
                                'reviewPerson' => $person ?? request('review_person', 'borrower'),
                                'reviewG' => $guarantorLinkId ?? request('review_g'),
                                'reviewM' => $memberId ?? request('review_m'),
                            ])
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif

        <div class="pt-2">
            @include('admin.loan-applications.review._document-requests')
        </div>
    </div>
</section>
