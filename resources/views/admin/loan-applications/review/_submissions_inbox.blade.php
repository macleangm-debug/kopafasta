@php
    $docService = app(\App\Services\ApplicationDocumentRequestService::class);
    $inboxRequests = collect($documentRequests ?? [])
        ->filter(fn ($req) => ($req->status ?? '') === 'uploaded')
        ->sortByDesc('updated_at')
        ->values();
    $guarantorRows = collect($review['guarantors'] ?? []);
@endphp

@if ($inboxRequests->isNotEmpty())
    <section id="submissions-inbox" class="scroll-mt-24 rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-brand/10 bg-gradient-to-r from-emerald-50/90 to-white flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">New submissions</p>
                <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Requested documents just in</h2>
                <p class="text-xs text-gray-500 mt-0.5">Open the file to review. Collateral and income live on the profile tabs.</p>
            </div>
            <span class="inline-flex items-center rounded-full bg-emerald-100 text-emerald-900 text-xs font-semibold px-3 py-1 ring-1 ring-emerald-200">
                {{ $inboxRequests->count() }} waiting
            </span>
        </div>
        <div class="p-4 sm:p-5 grid md:grid-cols-2 gap-3">
            @foreach ($inboxRequests as $docReq)
                @php
                    $kind = $docService->borrowerActionKind($docReq);
                    $kindLabel = $docService->screeningKindLabel($docReq);
                    $reviewUrl = $docService->screeningReviewUrl($docReq, $record, $guarantorRows->all());
                @endphp
                <a href="{{ $reviewUrl }}"
                   class="rounded-xl ring-1 ring-emerald-200 bg-emerald-50/40 px-4 py-3 hover:bg-emerald-50 transition flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 text-sm">{{ $docReq->label }}</p>
                        <p class="text-xs text-brand mt-0.5">{{ $docReq->subjectRoleLabel($groupReview ?? null) }}</p>
                        <p class="text-xs text-emerald-900/80 mt-0.5">{{ $kindLabel }}</p>
                    </div>
                    <span class="shrink-0 inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-900">
                        Review
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif
