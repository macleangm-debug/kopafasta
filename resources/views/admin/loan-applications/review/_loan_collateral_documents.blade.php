@php
    $docService = $docService ?? app(\App\Services\ApplicationDocumentRequestService::class);
    collect($documentRequests ?? [])->each(fn ($req) => $req->loadMissing(['requester', 'uploads', 'subjectCustomer']));
    $loanCollateralRequests = $docService->collateralRequestsForLoan($documentRequests ?? []);
    $canSeeInternal = in_array($viewer ?? 'screening', ['screening', 'committee', 'management'], true);
@endphp

<div class="rounded-2xl ring-1 ring-gray-200 p-4 space-y-3">
    <div>
        <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">Documents requested for this loan</p>
        <p class="text-[11px] text-gray-500 mt-0.5">Only items asked for this application — not the asset’s permanent file.</p>
    </div>

    @forelse ($loanCollateralRequests as $docReq)
        @php
            $status = $docService->operationalStatusLabel($docReq);
            $received = $docService->receivedAtLabel($docReq);
        @endphp
        <div class="rounded-xl bg-white ring-1 ring-gray-100 px-3 py-3 space-y-1">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <p class="text-sm font-semibold text-gray-900">{{ $docReq->label }}</p>
                <span @class([
                    'inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold',
                    'bg-emerald-100 text-emerald-900' => $docReq->status === 'satisfied',
                    'bg-amber-100 text-amber-950' => in_array($docReq->status, ['pending', 'uploaded'], true),
                    'bg-rose-100 text-rose-900' => $docReq->status === 'rejected',
                    'bg-gray-100 text-gray-600' => ! in_array($docReq->status, ['satisfied', 'pending', 'uploaded', 'rejected'], true),
                ])>{{ $status }}</span>
            </div>
            <p class="text-xs text-gray-600">{{ $docReq->subjectRoleLabel($groupReview ?? null) }}</p>
            @if ($canSeeInternal)
                <p class="text-[11px] text-gray-500">
                    @if ($docReq->requester)
                        Requested by {{ $docReq->requester->name }}
                    @endif
                    @if ($docReq->created_at)
                        {{ $docReq->requester ? ' · ' : '' }}{{ $docReq->created_at->timezone(config('app.timezone'))->format('d M Y') }}
                    @endif
                    @if ($received)
                        · Received {{ $received }}
                    @endif
                </p>
            @endif
            @if ($docReq->status === 'rejected' && filled($docReq->admin_notes))
                <p class="text-xs text-rose-800">{{ $docReq->admin_notes }}</p>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-600">No additional collateral documents have been requested for this loan.</p>
    @endforelse
</div>
