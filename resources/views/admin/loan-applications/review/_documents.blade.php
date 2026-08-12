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

    $missingRows = $rows->where('bucket', 'missing')->values();
    $verifyRows = $rows->where('bucket', 'uploaded')->values();
    $rejectedRows = $rows->where('bucket', 'rejected')->values();
    $counts = [
        'all' => $rows->count(),
        'action' => $rows->whereIn('bucket', ['uploaded', 'rejected', 'missing'])->count(),
        'uploaded' => $verifyRows->count(),
        'missing' => $missingRows->count(),
        'verified' => $rows->where('bucket', 'verified')->count(),
        'rejected' => $rejectedRows->count(),
    ];
    $defaultFilter = match (true) {
        $counts['missing'] > 0 => 'missing',
        $counts['uploaded'] > 0 || $counts['rejected'] > 0 => 'action',
        default => 'all',
    };
    $docsOpenByDefault = $counts['action'] > 0;
@endphp

<div
    class="space-y-3"
    x-data="{
        docsOpen: @js($docsOpenByDefault),
        missingOpen: false,
        filter: @js($defaultFilter),
        openId: null,
        match(bucket) {
            if (this.filter === 'all') return true;
            if (this.filter === 'action') return ['uploaded', 'rejected', 'missing'].includes(bucket);
            return this.filter === bucket;
        }
    }">
    {{-- Compact missing count — expand only when needed --}}
    @if ($missingRows->isNotEmpty())
        <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 overflow-hidden">
            <button type="button"
                    class="w-full px-3.5 py-3 flex flex-wrap items-center justify-between gap-2 text-left hover:bg-rose-100/50 transition"
                    @click="missingOpen = !missingOpen"
                    :aria-expanded="missingOpen.toString()">
                <p class="text-sm font-bold text-rose-900">
                    {{ $missingRows->count() }} missing document{{ $missingRows->count() === 1 ? '' : 's' }}
                    <span class="font-medium text-rose-800/80">· tap to
                        <span x-text="missingOpen ? 'hide names' : 'see names'"></span>
                    </span>
                </p>
                <span class="text-[11px] font-semibold text-rose-800">
                    @if ($verifyRows->isNotEmpty() || $rejectedRows->isNotEmpty())
                        Also {{ $verifyRows->count() }} to verify
                        @if ($rejectedRows->isNotEmpty()) · {{ $rejectedRows->count() }} rejected @endif
                    @else
                        Required
                    @endif
                </span>
            </button>
            <div x-show="missingOpen" x-cloak class="px-3.5 pb-3.5 border-t border-rose-200/80">
                <ul class="grid sm:grid-cols-2 gap-1.5 pt-3">
                    @foreach ($missingRows as $row)
                        <li class="flex items-start gap-2 text-sm text-rose-900">
                            <span class="mt-1.5 size-1.5 rounded-full bg-rose-500 shrink-0"></span>
                            <span>
                                <span class="font-semibold">{{ $row['req']->name }}</span>
                                @if ($row['req']->is_required)
                                    <span class="text-[10px] uppercase tracking-wide text-rose-700/80 font-bold ml-1">Required</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
                <button type="button"
                        @click="docsOpen = true; filter = 'missing'; openId = null"
                        class="mt-3 text-[11px] font-semibold text-rose-800 underline underline-offset-2">
                    Open missing in document list ↓
                </button>
                <p class="text-[11px] text-rose-800/80 mt-2">Your action: request these below, or wait for the borrower to upload, then verify.</p>
            </div>
        </div>
    @elseif ($counts['action'] > 0)
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3.5 py-3 text-sm text-amber-950">
            <span class="font-bold">{{ $counts['action'] }} document{{ $counts['action'] === 1 ? '' : 's' }} need attention</span>
            — {{ $counts['uploaded'] }} to verify
            @if ($counts['rejected'] > 0)
                · {{ $counts['rejected'] }} rejected
            @endif
            <span class="block text-[11px] mt-1 font-medium">Your action: open each “To verify” row and Verify or Reject.</span>
        </div>
    @else
        <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-3.5 py-3 text-sm text-emerald-950">
            <span class="font-bold">No missing required uploads</span>
            <span class="block text-[11px] mt-1 font-medium">Your action: confirm Documents Pass / Fail on the Checks tab, or request extras below if needed.</span>
        </div>
    @endif

    <div class="rounded-2xl ring-1 ring-brand/10 bg-white overflow-hidden">
        <button type="button"
                class="w-full px-4 py-3.5 flex items-start justify-between gap-3 text-left bg-gradient-to-r from-brand-muted/40 to-white hover:from-brand-muted/60 transition"
                @click="docsOpen = !docsOpen"
                :aria-expanded="docsOpen.toString()">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold text-gray-900">Application documents</h2>
                    <svg class="w-4 h-4 text-brand/50 transition shrink-0" :class="docsOpen ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ $review['satisfied_docs'] ?? 0 }}/{{ $review['required_docs'] ?? 0 }} required verified
                    @if ($counts['missing'] > 0)
                        · <span class="text-rose-700 font-semibold">{{ $counts['missing'] }} missing</span>
                    @endif
                    · click to {{-- label updated by Alpine visually --}}
                    <span x-text="docsOpen ? 'collapse' : 'expand'"></span>
                </p>
            </div>
            <div class="h-2 w-28 max-w-[30%] bg-gray-100 rounded-full overflow-hidden shrink-0 mt-1.5">
                <div class="h-full bg-brand-gold transition-all" style="width: {{ $review['document_progress'] ?? 0 }}%"></div>
            </div>
        </button>

        <div x-show="docsOpen" class="border-t border-gray-100">
            <div class="p-4 space-y-3">
                <div class="flex flex-wrap gap-1.5">
                    @foreach ([
                        'missing' => 'Missing ('.$counts['missing'].')',
                        'action' => 'Needs action ('.$counts['action'].')',
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
                            {{-- No x-cloak so missing rows are never blanked before Alpine boots --}}
                            <div x-show="match(@js($row['bucket']))" class="bg-white">
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
                                            <span class="text-[10px] font-bold uppercase tracking-wide text-rose-500">Missing</span>
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
                                        <p class="text-sm text-gray-500 pt-3">No file uploaded for this requirement yet. Ask the borrower to upload, or use Request documents below.</p>
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
                    <p class="text-[11px] text-gray-500" x-show="filter === 'missing' && {{ $counts['missing'] }} === 0">
                        No missing documents.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

@php
    $profileDocs = collect($review['profile_documents'] ?? []);
@endphp
<div class="mt-4" x-data="{ libraryOpen: @js($profileDocs->isNotEmpty() && $counts['action'] === 0) }">
    <div class="rounded-2xl ring-1 ring-brand/10 bg-white overflow-hidden">
        <button type="button"
                class="w-full px-4 py-3.5 flex items-center justify-between gap-3 text-left bg-gradient-to-r from-brand-muted/40 to-white hover:from-brand-muted/60 transition"
                @click="libraryOpen = !libraryOpen"
                :aria-expanded="libraryOpen.toString()">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold text-gray-900">
                        {{ $isMemberSubject ? 'Member document library' : ($isGuarantorSubject ? 'Guarantor document library' : 'Borrower document library') }}
                    </h2>
                    <svg class="w-4 h-4 text-brand/50 transition shrink-0" :class="libraryOpen ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">
                    Profile documents for {{ $subjectName }} · {{ $profileDocs->count() }} on file ·
                    <span x-text="libraryOpen ? 'collapse' : 'expand'"></span>
                </p>
            </div>
        </button>
        <div x-show="libraryOpen" class="border-t border-gray-100 p-4">
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
        </div>
    </div>
</div>
