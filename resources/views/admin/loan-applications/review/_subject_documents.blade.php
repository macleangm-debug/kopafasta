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
    <div class="px-5 py-3 border-b border-brand/10 bg-gradient-to-r from-brand via-brand-light to-brand text-white flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="text-sm font-semibold text-white">{{ $customer->full_name ?? 'Subject' }} · {{ $roleLabel }}</h2>
            @if ($pendingCount > 0)
                <p class="text-[11px] text-white/80 mt-0.5 tabular-nums">{{ $pendingCount }} pending</p>
            @endif
        </div>
        @if ($pendingCount > 0 && auth()->user()?->hasPermission('applications.review'))
            <form method="POST"
                  action="{{ route('admin.loan-applications.documents.verify-all', $record) }}"
                  @submit.prevent="window.confirmForm($el, {
                      title: @js('Verify all pending?'),
                      message: @js('Marks every pending profile file for this person as reviewed on this application.'),
                      confirmLabel: @js('Verify all'),
                      confirmClass: 'bg-emerald-700 hover:bg-emerald-800 text-white',
                      tone: 'confirm',
                  })">
                @csrf
                <input type="hidden" name="review_person" value="{{ $person ?? request('review_person', 'borrower') }}">
                @if ($guarantorLinkId ?? request('review_g'))
                    <input type="hidden" name="review_g" value="{{ $guarantorLinkId ?? request('review_g') }}">
                @endif
                @if ($memberId ?? request('review_m'))
                    <input type="hidden" name="review_m" value="{{ $memberId ?? request('review_m') }}">
                @endif
                <button type="submit" class="inline-flex rounded-lg bg-brand-gold text-brand text-[11px] font-bold px-3 py-1.5">
                    Verify all ({{ $pendingCount }})
                </button>
            </form>
        @endif
    </div>
    <div class="p-5 space-y-4">
        @if (! $customer)
            <p class="text-sm text-gray-500">No profile yet.</p>
        @elseif ($docs->isEmpty())
            <p class="text-sm text-gray-500">No documents.</p>
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
