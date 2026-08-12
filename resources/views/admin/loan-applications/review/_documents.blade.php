@php
    $requirements = collect($review['requirements'] ?? []);
    $uploads = $review['uploads'] ?? collect();
    $histories = $review['upload_histories'] ?? collect();
    $guidanceMap = $review['requirement_guidance'] ?? collect();
    $isMemberSubject = (bool) ($review['is_member_subject'] ?? false);
    $isGuarantorSubject = (bool) ($review['is_guarantor_subject'] ?? false);
    $subjectName = ($review['customer']->full_name ?? null)
        ?: ($review['member_row']['name'] ?? null)
        ?: ($review['guarantor_row']['name'] ?? null)
        ?: 'this subject';

    $rows = $requirements->map(function ($req) use ($uploads, $histories, $guidanceMap) {
        $upload = $uploads->get($req->id);
        $isApproved = $upload && in_array($upload->status, ['verified', 'approved'], true);
        $bucket = match (true) {
            $isApproved => 'verified',
            $upload && $upload->status === 'rejected' => 'rejected',
            (bool) $upload => 'uploaded',
            $req->is_required => 'missing',
            default => 'optional',
        };

        return [
            'req' => $req,
            'upload' => $upload,
            'history' => $histories->get($req->id, collect()),
            'guidance' => $guidanceMap->get($req->id, ['title' => 'What to verify', 'items' => []]),
            'bucket' => $bucket,
            'isApproved' => $isApproved,
            'badgeMap' => match ($bucket) {
                'verified' => 'bg-emerald-100 text-emerald-800',
                'rejected' => 'bg-red-100 text-red-800',
                'uploaded' => 'bg-amber-100 text-amber-800',
                'missing' => 'bg-rose-50 text-rose-800',
                default => 'bg-gray-100 text-gray-600',
            },
            'statusLabel' => match ($bucket) {
                'verified' => display_label($upload->status, 'document_status') ?: 'Verified',
                'rejected' => 'Rejected',
                'uploaded' => display_label($upload->status, 'document_status') ?: 'Uploaded',
                'missing' => 'Missing',
                default => 'Optional',
            },
        ];
    })->values();

    $counts = [
        'all' => $rows->count(),
        'action' => $rows->whereIn('bucket', ['uploaded', 'rejected', 'missing'])->count(),
        'uploaded' => $rows->where('bucket', 'uploaded')->count(),
        'missing' => $rows->where('bucket', 'missing')->count(),
        'verified' => $rows->where('bucket', 'verified')->count(),
    ];
    $defaultFilter = $counts['action'] > 0 ? 'action' : 'all';
@endphp

<x-admin.review-section
    id="review-documents"
    title="Application documents"
    :subtitle="$isMemberSubject || $isGuarantorSubject
        ? 'Loan product uploads for this application (shared file). Profile library for '.$subjectName.' is below.'
        : 'Product checklist files for this loan application — compact review desk'"
    collapsible
    :open="true">
    <div
        class="space-y-4"
        x-data="{
            filter: @js($defaultFilter),
            openId: null,
            match(bucket, hasUpload) {
                if (this.filter === 'all') return true;
                if (this.filter === 'action') return ['uploaded', 'rejected', 'missing'].includes(bucket);
                return this.filter === bucket;
            }
        }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-gray-600">
                <span class="font-semibold text-gray-900">{{ $review['satisfied_docs'] ?? 0 }}</span> of
                <span class="font-semibold text-gray-900">{{ $review['required_docs'] ?? 0 }}</span> required verified
                @if (($review['uploaded_docs'] ?? 0) < ($review['required_docs'] ?? 0))
                    · <span class="text-amber-700">{{ ($review['required_docs'] ?? 0) - ($review['uploaded_docs'] ?? 0) }} missing</span>
                @endif
            </div>
            <div class="h-2 w-40 max-w-full bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-brand-gold transition-all" style="width: {{ $review['document_progress'] ?? 0 }}%"></div>
            </div>
        </div>

        <div class="flex flex-wrap gap-1.5">
            @foreach ([
                'action' => 'Needs action ('.$counts['action'].')',
                'missing' => 'Missing ('.$counts['missing'].')',
                'uploaded' => 'To verify ('.$counts['uploaded'].')',
                'verified' => 'Done ('.$counts['verified'].')',
                'all' => 'All ('.$counts['all'].')',
            ] as $key => $label)
                <button type="button"
                        @click="filter = @js($key); openId = null"
                        :class="filter === @js($key)
                            ? 'bg-brand text-white ring-brand'
                            : 'bg-white text-gray-700 ring-gray-200 hover:bg-brand-muted/40'"
                        class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold ring-1 transition">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500">No document requirements configured for this loan product.</p>
        @else
            <div class="rounded-xl ring-1 ring-gray-200 divide-y divide-gray-100 overflow-hidden bg-white">
                @foreach ($rows as $row)
                    @php
                        $req = $row['req'];
                        $upload = $row['upload'];
                        $history = $row['history'];
                        $guidance = $row['guidance'];
                    @endphp
                    <div x-show="match(@js($row['bucket']), {{ $upload ? 'true' : 'false' }})" x-cloak class="bg-white">
                        <button type="button"
                                class="w-full px-3.5 py-3 flex items-center gap-3 text-left hover:bg-gray-50/80 transition"
                                @click="openId = openId === {{ (int) $req->id }} ? null : {{ (int) $req->id }}">
                            <div class="shrink-0 w-12 h-12 rounded-lg overflow-hidden ring-1 ring-gray-200 bg-gray-50 flex items-center justify-center">
                                @if ($upload?->file_path)
                                    <x-admin.document-preview
                                        :url="asset('storage/'.$upload->file_path)"
                                        label="View"
                                        variant="thumbnail" />
                                @else
                                    <span class="text-[10px] font-bold uppercase tracking-wide text-gray-400">No file</span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $req->name }}</p>
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold {{ $row['badgeMap'] }}">{{ $row['statusLabel'] }}</span>
                                    @if ($req->is_required)
                                        <span class="text-[10px] uppercase tracking-widest font-semibold text-gray-400">Req</span>
                                    @endif
                                </div>
                                @if ($req->description)
                                    <p class="text-[11px] text-gray-500 mt-0.5 truncate">{{ $req->description }}</p>
                                @endif
                            </div>
                            <svg class="size-4 text-gray-400 shrink-0 transition" :class="openId === {{ (int) $req->id }} ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                        </button>

                        <div x-show="openId === {{ (int) $req->id }}" x-cloak class="px-3.5 pb-3.5 space-y-3 border-t border-gray-50 bg-gray-50/40">
                            @if (! empty($guidance['items']))
                                <div class="pt-3">
                                    <p class="text-[10px] uppercase tracking-widest font-semibold text-sky-800 mb-1.5">{{ $guidance['title'] ?? 'What to verify' }}</p>
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

                            @if ($upload)
                                <div class="flex flex-wrap gap-2">
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
                                              @submit.prevent="window.confirmForm($el, {
                                                  title: @js('Reject this document?'),
                                                  message: @js('Reject this document? The borrower may need to re-upload.'),
                                                  confirmLabel: @js('Reject'),
                                                  confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                                                  tone: 'warning',
                                              })">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-red-700 bg-red-50 ring-1 ring-red-200 px-3 py-1.5 rounded-lg hover:bg-red-100">
                                                Reject
                                            </button>
                                        </form>
                                    @endif
                                    @if ($upload->file_path)
                                        <x-admin.document-preview
                                            :url="asset('storage/'.$upload->file_path)"
                                            label="Open full"
                                            variant="link" />
                                    @endif
                                </div>
                                <p class="text-[11px] text-gray-500">Latest upload · {{ $upload->created_at?->format('d M Y, H:i') }}</p>
                            @else
                                <p class="text-sm text-gray-500 pt-3">No file uploaded for this requirement yet.</p>
                            @endif

                            @if ($history->count() > 1)
                                <details class="text-xs">
                                    <summary class="font-semibold text-gray-600 cursor-pointer">{{ $history->count() }} upload version(s)</summary>
                                    <ul class="mt-2 space-y-1 text-gray-600">
                                        @foreach ($history as $version)
                                            <li class="flex flex-wrap items-center gap-2">
                                                <span>{{ $version->created_at?->format('d M Y, H:i') }}</span>
                                                <span class="font-medium">{{ display_label($version->status, 'document_status') }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="text-[11px] text-gray-500">Tip: start on <span class="font-semibold">Needs action</span>. Expand a row only when you need verify guidance or reject.</p>
        @endif
    </div>
</x-admin.review-section>

@php
    $profileDocs = collect($review['profile_documents'] ?? []);
@endphp
<x-admin.review-section
    id="review-kyc-documents"
    title="{{ $isMemberSubject ? 'Member document library' : ($isGuarantorSubject ? 'Guarantor document library' : 'Borrower document library') }}"
    :subtitle="'Profile documents for '.$subjectName.' — onboarding uploads, separate from application checklist'"
    collapsible
    :open="$profileDocs->isNotEmpty()">
    @if ($profileDocs->isEmpty())
        <p class="text-sm text-gray-500">No profile documents on file yet for {{ $subjectName }}.</p>
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach ($profileDocs as $doc)
                @php
                    $docGuidance = app(\App\Services\ApplicationDocumentReviewService::class)->guidanceForDocument($doc);
                @endphp
                <div class="rounded-xl ring-1 ring-gray-200 overflow-hidden bg-gray-50/50 p-3 flex gap-3">
                    @if ($doc->file_path)
                        <x-admin.document-preview
                            :url="asset('storage/'.$doc->file_path)"
                            label="View"
                            variant="thumbnail" />
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-sm text-gray-900 truncate">{{ $doc->documentType?->name ?? 'Supporting document' }}</p>
                        <p class="text-[11px] text-gray-500 mt-0.5 capitalize">{{ $doc->documentType?->category ?? 'kyc' }} · {{ $doc->created_at?->format('d M Y') }}</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <x-admin.badge :value="$doc->status ?? 'pending'" group="document_status"
                                :map="[
                                    'verified' => 'bg-emerald-100 text-emerald-800',
                                    'approved' => 'bg-emerald-100 text-emerald-800',
                                    'pending_review' => 'bg-amber-100 text-amber-800',
                                    'pending' => 'bg-amber-100 text-amber-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                ]" />
                            @if (! in_array($doc->status, ['verified', 'approved'], true))
                                <form method="POST" action="{{ route('admin.loan-applications.documents.verify', [$record, $doc]) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-[11px] font-semibold text-emerald-800 bg-emerald-50 ring-1 ring-emerald-200 px-2 py-1 rounded-md">Verify</button>
                                </form>
                            @endif
                        </div>
                        @if (! empty($docGuidance['items']))
                            <p class="text-[10px] text-gray-500 mt-1.5 line-clamp-2">{{ implode(' · ', array_slice($docGuidance['items'], 0, 2)) }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-admin.review-section>
