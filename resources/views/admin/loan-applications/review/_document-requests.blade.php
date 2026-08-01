@perm('applications.request_documents')
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
    $groups = $groupedDocumentRequests ?? [
        'pending' => collect(),
        'uploaded' => collect(),
        'completed' => collect(),
        'rejected' => collect(),
    ];
    $needsReview = ($groups['uploaded'] ?? collect());
    $awaiting = ($groups['pending'] ?? collect());
    $closed = ($groups['completed'] ?? collect())->merge($groups['rejected'] ?? collect());
    $docSectionOpen = $needsReview->isNotEmpty() || $awaiting->isNotEmpty();
@endphp
<x-admin.review-section
    id="review-document-requests"
    title="Document requests"
    subtitle="Review uploads first, then request anything still missing — arranged by status"
    collapsible
    :open="$docSectionOpen"
>

    {{-- 1. Ready for review (with thumbnails) --}}
    @if ($needsReview->isNotEmpty())
        <div class="mb-6">
            <p class="text-xs font-semibold uppercase tracking-widest text-amber-700 mb-3">Ready for review · {{ $needsReview->count() }}</p>
            <div class="space-y-3">
                @foreach ($needsReview as $docReq)
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
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- 2. Awaiting borrower --}}
    @if ($awaiting->isNotEmpty())
        <div class="mb-6">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-3">Awaiting borrower · {{ $awaiting->count() }}</p>
            <ul class="divide-y divide-gray-100 rounded-xl ring-1 ring-gray-200 bg-white overflow-hidden">
                @foreach ($awaiting as $docReq)
                    <li class="px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 text-sm">{{ $docReq->label }}</p>
                            @if ($docReq->instructions)
                                <p class="text-xs text-gray-500 mt-0.5">{{ $docReq->instructions }}</p>
                            @endif
                        </div>
                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-600">Pending</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 3. Request form — grouped checklist --}}
    <details class="mb-6 rounded-xl ring-1 ring-brand/15 bg-white overflow-hidden" @if ($needsReview->isEmpty() && $awaiting->isEmpty()) open @endif>
        <summary class="cursor-pointer px-4 py-3 bg-brand-muted/30 text-sm font-semibold text-brand flex items-center justify-between gap-2">
            <span>Request document re-upload</span>
            <span class="text-xs font-normal text-brand/70">Select type → send</span>
        </summary>
        <form method="POST" action="{{ route('admin.loan-applications.document-requests.store', $record) }}" class="p-4 space-y-5 border-t border-brand/10">
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
                    <p class="text-xs text-gray-500 mb-2">Borrower is deep-linked to My Collaterals.</p>
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
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Identity / photos</p>
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
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Income &amp; other</p>
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
    </details>

    {{-- 4. Closed history --}}
    @if ($closed->isNotEmpty())
        <details class="rounded-xl ring-1 ring-gray-100 overflow-hidden">
            <summary class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-500 bg-gray-50">
                Completed / rejected · {{ $closed->count() }}
            </summary>
            <ul class="divide-y divide-gray-50 bg-white">
                @foreach ($closed as $docReq)
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
    @elseif ($documentRequests->isEmpty())
        <p class="text-sm text-gray-500">No re-upload requests yet. Use the form above when a document needs to be re-submitted.</p>
    @endif
</x-admin.review-section>
@endperm
