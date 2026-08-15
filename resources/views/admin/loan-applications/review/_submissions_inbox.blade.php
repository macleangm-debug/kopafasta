@php
    $docService = app(\App\Services\ApplicationDocumentRequestService::class);
    $inboxRequests = collect($documentRequests ?? [])
        ->filter(fn ($req) => ($req->status ?? '') === 'uploaded')
        ->sortByDesc('updated_at')
        ->values();
    $guarantorRows = collect($review['guarantors'] ?? []);
    $inboxCount = $inboxRequests->count();
@endphp

@if ($inboxRequests->isNotEmpty())
    <details id="submissions-inbox" class="group scroll-mt-24 rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm overflow-hidden">
        <summary class="cursor-pointer list-none px-5 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3 bg-gradient-to-r from-emerald-50/90 to-white [&::-webkit-details-marker]:hidden">
            <div class="flex items-center gap-2 min-w-0">
                <p class="text-sm font-semibold text-gray-900">New submissions</p>
                <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-900 text-xs font-bold px-2 py-0.5 ring-1 ring-emerald-200 tabular-nums">
                    {{ $inboxCount }}
                </span>
            </div>
            <span class="text-[11px] text-gray-500 group-open:hidden">Tap to expand</span>
            <span class="text-[11px] text-gray-500 hidden group-open:inline">Tap to collapse</span>
        </summary>
        <div class="px-5 sm:px-6 pb-3 text-xs text-gray-500">
            {{ $inboxCount === 1 ? '1 new file is waiting for review.' : $inboxCount.' new files are waiting for review.' }}
            Income statements open on Documents.
        </div>
        <div class="p-4 sm:p-5 pt-0 grid md:grid-cols-2 gap-3">
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
    </details>
@endif
