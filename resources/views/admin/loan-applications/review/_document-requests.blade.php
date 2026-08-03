@php
    $docService = app(\App\Services\ApplicationDocumentRequestService::class);
    $presets = $docService::PRESET_LABELS;
    $assetPresets = $docService::ASSET_BACKED_PRESET_LABELS;
    $collateralPresets = $docService::COLLATERAL_PRESET_LABELS;
    $identityPresets = ['Updated National ID', 'New National ID photo', 'New face verification photo', 'Identity verification photo', 'Image Not Clear'];
    $generalPresets = array_values(array_diff($presets, $assetPresets, $collateralPresets, $identityPresets));
    $record->loadMissing('product');
    $isAssetProduct = app(\App\Services\AssetBackedLoanService::class)->isAssetBackedApplication($record)
        || app(\App\Services\AssetLendingService::class)->isAssetLendingApplication($record);
    $documentRequests = collect($documentRequests ?? []);
    $canRequestDocs = auth()->user()?->hasPermission('applications.request_documents');

    $isLoanFileRequest = fn ($req) => ! $docService->isProfileGuidedRequest($req);

    $loanRequests = $documentRequests->filter($isLoanFileRequest)->values();
    $profileRequests = $documentRequests->reject($isLoanFileRequest)->values();

    $loanReady = $loanRequests->where('status', 'uploaded')->values();
    $loanCompleted = $loanRequests->where('status', 'satisfied')->values();
    $loanAwaiting = $loanRequests->filter(fn ($r) => in_array($r->status, ['pending', 'rejected'], true))->values();
    $profileOpen = $profileRequests->filter(fn ($r) => in_array($r->status, ['pending', 'uploaded', 'rejected'], true))->values();

    $openRequestCount = $loanReady->count()
        + $loanAwaiting->count()
        + $profileOpen->filter(fn ($r) => $r->needsBorrowerAction() || $r->status === 'uploaded')->count();
@endphp

{{-- Loan-specific uploads from underwriting requests (not profile identity/face/signature/collateral) --}}
@if ($loanReady->isNotEmpty() || $loanCompleted->filter(fn ($r) => $r->uploads->isNotEmpty())->isNotEmpty())
    <section id="review-loan-request-uploads" class="scroll-mt-24 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">This application</p>
            <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Submitted for this loan</h2>
            <p class="text-xs text-gray-500 mt-0.5">
                Extra files the borrower uploaded for this file — not profile KYC (those stay under Personal / Face / the library above).
            </p>
        </div>
        <div class="p-5 sm:p-6 space-y-4">
            @if ($loanReady->isNotEmpty())
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-amber-700 mb-3">Ready for review · {{ $loanReady->count() }}</p>
                    <div class="space-y-3">
                        @foreach ($loanReady as $docReq)
                            <div class="rounded-xl ring-1 ring-amber-200 bg-amber-50/40 p-4">
                                <div class="flex flex-wrap items-start gap-4">
                                    @php $latestUpload = $docReq->uploads->sortByDesc('id')->first(); @endphp
                                    @if ($latestUpload?->file_path)
                                        <x-admin.document-preview
                                            :url="asset('storage/'.$latestUpload->file_path)"
                                            label="View"
                                            variant="thumbnail" />
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-semibold text-gray-900">{{ $docReq->label }}</p>
                                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800">Uploaded</span>
                                        </div>
                                        @if ($docReq->instructions)
                                            <p class="text-sm text-gray-600 mt-1">{{ $docReq->instructions }}</p>
                                        @endif
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @if ($latestUpload?->file_path)
                                                <x-admin.document-preview
                                                    :url="asset('storage/'.$latestUpload->file_path)"
                                                    label="Open full size" />
                                            @endif
                                            @if ($canRequestDocs)
                                                <form method="POST" action="{{ route('admin.loan-application-document-requests.satisfy', $docReq) }}">
                                                    @csrf
                                                    <button type="submit" class="text-xs font-semibold text-emerald-800 bg-emerald-100 hover:bg-emerald-200 px-3 py-1.5 rounded-lg">
                                                        Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.loan-application-document-requests.reject', $docReq) }}" class="flex items-center gap-2 flex-wrap">
                                                    @csrf
                                                    <input type="text" name="notes" required maxlength="500" placeholder="Reason for rejection"
                                                           class="rounded-lg border-gray-300 text-xs ring-1 ring-gray-200 px-3 py-2 w-48 max-w-full">
                                                    <button type="submit" class="text-xs font-semibold text-red-800 bg-red-100 hover:bg-red-200 px-3 py-1.5 rounded-lg">
                                                        Reject
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @php $closedWithFiles = $loanCompleted->filter(fn ($r) => $r->uploads->isNotEmpty()); @endphp
            @if ($closedWithFiles->isNotEmpty())
                <details class="rounded-xl ring-1 ring-gray-100 overflow-hidden" @if ($loanReady->isEmpty()) open @endif>
                    <summary class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-500 bg-gray-50">
                        Completed / rejected · {{ $closedWithFiles->count() }}
                    </summary>
                    <ul class="divide-y divide-gray-50 bg-white">
                        @foreach ($closedWithFiles as $docReq)
                            @php
                                $statusClass = match ($docReq->status) {
                                    'satisfied' => 'bg-emerald-100 text-emerald-700',
                                    'rejected'  => 'bg-red-100 text-red-700',
                                    default     => 'bg-gray-100 text-gray-600',
                                };
                                $latestUpload = $docReq->uploads->sortByDesc('id')->first();
                            @endphp
                            <li class="px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                                <div class="min-w-0 flex items-center gap-3">
                                    @if ($latestUpload?->file_path)
                                        <x-admin.document-preview
                                            :url="asset('storage/'.$latestUpload->file_path)"
                                            label="View"
                                            variant="link" />
                                    @endif
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $docReq->label }}</p>
                                        @if ($docReq->admin_notes && $docReq->status === 'rejected')
                                            <p class="text-xs text-red-700 mt-0.5">{{ $docReq->admin_notes }}</p>
                                        @endif
                                    </div>
                                </div>
                                <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $statusClass }}">{{ ucfirst($docReq->status === 'satisfied' ? 'completed' : $docReq->status) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    </section>
@endif

{{-- Open tracking: loan uploads still pending + profile-guided (no loan file gallery) --}}
@if ($loanAwaiting->isNotEmpty() || $profileOpen->isNotEmpty())
    <section id="review-document-requests" class="scroll-mt-24 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Outstanding</p>
                <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Open document requests</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    Waiting on the borrower. Profile updates (ID, face, signature, collateral) stay on their profile — they will not appear as loan uploads here.
                </p>
            </div>
            @if ($openRequestCount > 0)
                <span class="inline-flex items-center rounded-full bg-amber-100 text-amber-900 text-xs font-semibold px-3 py-1 ring-1 ring-amber-200">
                    {{ $openRequestCount }} open
                </span>
            @endif
        </div>
        <div class="p-5 sm:p-6 space-y-5">
            @if ($loanAwaiting->isNotEmpty())
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Loan file uploads · {{ $loanAwaiting->count() }}</p>
                    <ul class="divide-y divide-gray-100 rounded-xl ring-1 ring-gray-200 bg-white overflow-hidden">
                        @foreach ($loanAwaiting as $docReq)
                            <li class="px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 text-sm">{{ $docReq->label }}</p>
                                    @if ($docReq->instructions)
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $docReq->instructions }}</p>
                                    @endif
                                </div>
                                <span @class([
                                    'inline-flex px-2 py-0.5 rounded text-xs font-semibold',
                                    'bg-red-100 text-red-800' => $docReq->status === 'rejected',
                                    'bg-gray-100 text-gray-600' => $docReq->status !== 'rejected',
                                ])>{{ $docReq->status === 'rejected' ? 'Re-upload needed' : 'Pending' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($profileOpen->isNotEmpty())
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-sky-700 mb-3">Profile updates · {{ $profileOpen->count() }}</p>
                    <ul class="divide-y divide-sky-50 rounded-xl ring-1 ring-sky-100 bg-sky-50/40 overflow-hidden">
                        @foreach ($profileOpen as $docReq)
                            @php $kind = $docService->borrowerActionKind($docReq); @endphp
                            <li class="px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 text-sm">{{ $docReq->label }}</p>
                                    <p class="text-xs text-sky-800/80 mt-0.5">
                                        Borrower updates this in their profile ({{ $kind }}) — review it under Personal / Face / Assets, not as a loan upload.
                                    </p>
                                </div>
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-sky-100 text-sky-900">
                                    {{ $docReq->status === 'uploaded' ? 'Done in profile' : ($docReq->status === 'rejected' ? 'Retry' : 'Awaiting') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>
@endif

@if ($canRequestDocs)
    <section class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden" x-data="{ open: {{ $errors->hasAny(['presets', 'label', 'instructions', 'type']) ? 'true' : 'false' }} }">
        <div class="px-5 sm:px-6 py-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Need more?</p>
                <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Request a document</h2>
                <p class="text-xs text-gray-500 mt-0.5">Send a loan upload request, or ask for a profile update (ID / face / collateral).</p>
            </div>
            <button type="button"
                    @click="open = !open"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl shadow-sm ring-1 ring-brand/15">
                <span x-text="open ? 'Hide form' : 'Request document'"></span>
                <svg class="size-4 transition" :class="open && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 8l5 5 5-5z"/></svg>
            </button>
        </div>
        <div x-show="open" x-cloak class="border-t border-brand/10">
            <form method="POST" action="{{ route('admin.loan-applications.document-requests.store', $record) }}" class="p-5 sm:p-6 space-y-5">
                @csrf
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Type</label>
                        <select name="type" class="w-full rounded-xl border-brand/15 text-sm ring-1 ring-brand/10 px-3 py-2.5 focus:border-brand focus:ring-brand/15">
                            <option value="document">Document upload</option>
                            <option value="clarification">Clarification</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Due date (optional)</label>
                        <input type="date" name="due_at" class="w-full rounded-xl border-brand/15 text-sm ring-1 ring-brand/10 px-3 py-2.5 focus:border-brand focus:ring-brand/15">
                    </div>
                </div>

                @if ($isAssetProduct)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Asset / lending</p>
                        <div class="grid sm:grid-cols-2 gap-2">
                            @foreach ($assetPresets as $preset)
                                <label class="flex items-start gap-2 text-sm text-gray-700 bg-brand-muted/50 rounded-xl px-3 py-2 ring-1 ring-brand/10">
                                    <input type="checkbox" name="presets[]" value="{{ $preset }}" class="mt-0.5 rounded border-gray-300 text-brand">
                                    <span>{{ $preset }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Collateral</p>
                        <p class="text-xs text-gray-500 mb-2">Borrower is deep-linked to My Collaterals — fulfilment stays on the profile.</p>
                        <div class="grid sm:grid-cols-2 gap-2">
                            @foreach ($collateralPresets as $preset)
                                <label class="flex items-start gap-2 text-sm text-gray-700 bg-emerald-50/80 rounded-xl px-3 py-2 ring-1 ring-brand/10">
                                    <input type="checkbox" name="presets[]" value="{{ $preset }}" class="mt-0.5 rounded border-gray-300 text-brand">
                                    <span>{{ $preset }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Identity / photos <span class="font-normal normal-case text-gray-400">(profile — not a loan upload)</span></p>
                    <div class="grid sm:grid-cols-2 gap-2">
                        @foreach ($identityPresets as $preset)
                            <label class="flex items-start gap-2 text-sm text-gray-700 bg-sky-50 rounded-xl px-3 py-2 ring-1 ring-sky-100">
                                <input type="checkbox" name="presets[]" value="{{ $preset }}" class="mt-0.5 rounded border-gray-300 text-brand">
                                <span>{{ $preset }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Income &amp; other <span class="font-normal normal-case text-gray-400">(shown under Submitted for this loan)</span></p>
                    <div class="grid sm:grid-cols-2 gap-2">
                        @foreach ($generalPresets as $preset)
                            <label class="flex items-start gap-2 text-sm text-gray-700 bg-gray-50 rounded-xl px-3 py-2 ring-1 ring-gray-100">
                                <input type="checkbox" name="presets[]" value="{{ $preset }}" class="mt-0.5 rounded border-gray-300 text-brand">
                                <span>{{ $preset }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Custom document label</label>
                    <input type="text" name="label" maxlength="120" placeholder="e.g. Ownership certificate"
                           class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 px-3 py-2">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Reason (shown to borrower)</label>
                    <textarea name="instructions" rows="2" maxlength="2000" placeholder="e.g. Image not clear — please re-upload a sharper photo"
                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 px-3 py-2"></textarea>
                </div>

                <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl">
                    Send request
                </button>
            </form>
        </div>
    </section>
@elseif ($documentRequests->isEmpty())
    <p class="text-sm text-gray-500">No application-specific document requests on this file.</p>
@endif
