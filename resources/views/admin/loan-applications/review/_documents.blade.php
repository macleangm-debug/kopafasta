<x-admin.review-section id="review-documents" title="Document review" subtitle="Product requirements, reviewer guidance, and verification for this application">
    <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
        <div class="text-sm text-gray-600">
            <span class="font-semibold text-gray-900">{{ $review['satisfied_docs'] }}</span> of
            <span class="font-semibold text-gray-900">{{ $review['required_docs'] }}</span> required documents verified
            @if (($review['uploaded_docs'] ?? 0) < ($review['required_docs'] ?? 0))
                · <span class="text-amber-700">{{ ($review['required_docs'] ?? 0) - ($review['uploaded_docs'] ?? 0) }} missing upload(s)</span>
            @endif
        </div>
        <div class="h-2 w-48 max-w-full bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-brand-gold transition-all" style="width: {{ $review['document_progress'] }}%"></div>
        </div>
    </div>

    @if ($review['requirements']->isEmpty())
        <p class="text-sm text-gray-500">No document requirements configured for this loan product.</p>
    @else
        <div class="space-y-4">
            @foreach ($review['requirements'] as $req)
                @php
                    $upload = $review['uploads']->get($req->id);
                    $history = ($review['upload_histories'] ?? collect())->get($req->id, collect());
                    $guidance = ($review['requirement_guidance'] ?? collect())->get($req->id, ['title' => 'What to verify', 'items' => []]);
                    $isApproved = $upload && in_array($upload->status, ['verified', 'approved'], true);
                    $badgeMap = match (true) {
                        $isApproved => 'bg-emerald-100 text-emerald-800',
                        $upload && $upload->status === 'rejected' => 'bg-red-100 text-red-800',
                        (bool) $upload => 'bg-amber-100 text-amber-800',
                        default => 'bg-gray-100 text-gray-600',
                    };
                    $statusLabel = $upload
                        ? display_label($upload->status, 'document_status')
                        : ($req->is_required ? 'Missing' : 'Optional');
                @endphp
                <div class="rounded-xl ring-1 ring-gray-200 overflow-hidden">
                    <div class="p-4 bg-gray-50/80 border-b border-gray-100 flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-gray-900">{{ $req->name }}</p>
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-semibold {{ $badgeMap }}">{{ $statusLabel }}</span>
                                @if ($req->is_required)
                                    <span class="text-[10px] uppercase tracking-widest font-semibold text-gray-500">Required</span>
                                @endif
                            </div>
                            @if ($req->description)
                                <p class="text-xs text-gray-500 mt-1">{{ $req->description }}</p>
                            @endif
                        </div>
                        @if ($upload?->file_path)
                            <x-admin.document-preview
                                :url="asset('storage/'.$upload->file_path)"
                                label="Preview file" />
                        @endif
                    </div>

                    @if (! empty($guidance['items']))
                        <div class="px-4 py-3 bg-sky-50/80 border-b border-sky-100">
                            <p class="text-[10px] uppercase tracking-widest font-semibold text-sky-800 mb-2">{{ $guidance['title'] ?? 'What to verify' }}</p>
                            <ul class="space-y-1">
                                @foreach ($guidance['items'] as $item)
                                    <li class="text-xs text-sky-900 flex items-start gap-2">
                                        <span class="text-sky-600 shrink-0">✓</span>
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="p-4">
                        @if ($upload)
                            <div class="flex flex-wrap gap-2 mb-3">
                                @if (! in_array($upload->status, ['verified', 'approved'], true))
                                    <form method="POST" action="{{ route('admin.loan-applications.documents.verify', [$record, $upload]) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-emerald-800 bg-emerald-50 ring-1 ring-emerald-200 px-3 py-1.5 rounded-lg hover:bg-emerald-100">
                                            Verify
                                        </button>
                                    </form>
                                @endif
                                @if ($upload->status !== 'rejected')
                                    <form method="POST" action="{{ route('admin.loan-applications.documents.reject', [$record, $upload]) }}" class="inline"
                                          onsubmit="return confirm('Reject this document? The borrower may need to re-upload.');">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-red-700 bg-red-50 ring-1 ring-red-200 px-3 py-1.5 rounded-lg hover:bg-red-100">
                                            Reject
                                        </button>
                                    </form>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500">Latest upload · {{ $upload->created_at?->format('d M Y, H:i') }}</p>
                        @else
                            <p class="text-sm text-gray-500">No file uploaded for this requirement yet.</p>
                        @endif

                        @if ($history->count() > 1)
                            <details class="mt-3">
                                <summary class="text-xs font-semibold text-gray-600 cursor-pointer">{{ $history->count() }} upload version(s)</summary>
                                <ul class="mt-2 space-y-1 text-xs text-gray-600">
                                    @foreach ($history as $version)
                                        <li class="flex flex-wrap items-center gap-2">
                                            <span>{{ $version->created_at?->format('d M Y, H:i') }}</span>
                                            <span class="font-medium">{{ display_label($version->status, 'document_status') }}</span>
                                            @if ($version->file_path)
                                                <x-admin.document-preview
                                                    :url="asset('storage/'.$version->file_path)"
                                                    label="Preview"
                                                    variant="link" />
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-admin.review-section>

@if (($review['profile_documents'] ?? collect())->isNotEmpty())
    <x-admin.review-section id="review-kyc-documents" title="Borrower document library" subtitle="Profile and application documents — verify without leaving this review">
        <div class="grid md:grid-cols-2 gap-4">
            @foreach ($review['profile_documents'] as $doc)
                @php
                    $docGuidance = app(\App\Services\ApplicationDocumentReviewService::class)->guidanceForDocument($doc);
                @endphp
                <div class="rounded-xl ring-1 ring-gray-200 overflow-hidden bg-gray-50/50">
                    <div class="p-4 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-sm text-gray-900">{{ $doc->documentType?->name ?? 'Supporting document' }}</p>
                            <p class="text-xs text-gray-500 mt-0.5 capitalize">{{ $doc->documentType?->category ?? 'kyc' }} · {{ $doc->created_at?->format('d M Y, H:i') }}</p>
                            @if ($doc->notes)
                                <p class="text-xs text-gray-600 mt-1">{{ $doc->notes }}</p>
                            @endif
                        </div>
                        <x-admin.badge :value="$doc->status ?? 'pending'" group="document_status"
                            :map="[
                                'verified' => 'bg-emerald-100 text-emerald-800',
                                'approved' => 'bg-emerald-100 text-emerald-800',
                                'pending_review' => 'bg-amber-100 text-amber-800',
                                'pending' => 'bg-amber-100 text-amber-800',
                                'rejected' => 'bg-red-100 text-red-800',
                            ]" />
                    </div>
                    @if (! empty($docGuidance['items']))
                        <div class="px-4 pb-3">
                            <ul class="text-[11px] text-gray-600 space-y-0.5">
                                @foreach (array_slice($docGuidance['items'], 0, 3) as $item)
                                    <li>· {{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="px-4 pb-4 flex flex-wrap gap-2">
                        @if ($doc->file_path)
                            <x-admin.document-preview
                                :url="asset('storage/'.$doc->file_path)"
                                label="Preview file" />
                        @endif
                        @if (! in_array($doc->status, ['verified', 'approved'], true))
                            <form method="POST" action="{{ route('admin.loan-applications.documents.verify', [$record, $doc]) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-xs font-semibold text-emerald-800 bg-emerald-50 ring-1 ring-emerald-200 px-3 py-1.5 rounded-lg hover:bg-emerald-100">Verify</button>
                            </form>
                        @endif
                        @if ($doc->status !== 'rejected')
                            <form method="POST" action="{{ route('admin.loan-applications.documents.reject', [$record, $doc]) }}" class="inline"
                                  onsubmit="return confirm('Reject this document?');">
                                @csrf
                                <button type="submit" class="text-xs font-semibold text-red-700 bg-red-50 ring-1 ring-red-200 px-3 py-1.5 rounded-lg hover:bg-red-100">Reject</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-admin.review-section>
@endif
