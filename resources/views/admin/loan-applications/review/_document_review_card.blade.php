{{-- Application-scoped review card for a profile / library document.
     Expects: $record (LoanApplication), $doc (CustomerDocument), optional $appReview, $reviewPerson, $reviewG, $reviewM --}}
@php
    $docReviewService = app(\App\Services\ApplicationDocumentReviewService::class);
    $appStatus = $appReview->status
        ?? $docReviewService->statusFor($record, $doc);
    $canReviewDocs = auth()->user()?->hasPermission('applications.review');
    $failReasons = config('application_document_review.fail_reasons', []);
    $person = $reviewPerson ?? request('review_person', 'borrower');
    $gId = $reviewG ?? request('review_g');
    $mId = $reviewM ?? request('review_m');
    $statusTone = match ($appStatus) {
        'verified', 'approved' => 'bg-emerald-100 text-emerald-800',
        'rejected' => 'bg-red-100 text-red-800',
        default => 'bg-amber-100 text-amber-800',
    };
    $checklistLinks = app(\App\Services\ChecklistDocumentBridge::class)
        ->checklistLinksForDocumentCode((string) ($doc->documentType?->code ?? ''));
    $priorVersions = collect();
    if ($doc->customer_id && $doc->documentType?->code) {
        $priorVersions = app(\App\Services\ProfileDocumentService::class)
            ->replacedVersions($doc->customer, (string) $doc->documentType->code, 3);
    }
@endphp

<div class="rounded-xl ring-1 ring-brand/10 overflow-hidden bg-white p-3 flex flex-col sm:flex-row sm:items-start gap-3"
     x-data="{ openFail: false, failReason: '' }">
    @if ($doc->file_path)
        <x-admin.document-preview
            :url="asset('storage/'.$doc->file_path)"
            label="View"
            variant="thumbnail" />
    @endif
    <div class="min-w-0 flex-1">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="font-semibold text-sm text-gray-900 truncate">{{ $doc->displayName() }}</p>
                <p class="sm:hidden mt-0.5 text-[11px] text-gray-500">
                    {{ $doc->created_at ? format_app_date($doc->created_at) : '—' }}
                </p>
                <p class="hidden sm:block text-[11px] text-gray-500 mt-0.5">
                    @if ($doc->created_at)
                        Uploaded {{ format_app_date($doc->created_at) }}
                    @endif
                    <span class="text-gray-400"> · Profile file · reviewed per application</span>
                </p>
                @if ($checklistLinks !== [])
                    <p class="text-[10px] text-violet-800 mt-1.5">
                        Checklist:
                        @foreach ($checklistLinks as $i => $link)
                            @if ($i > 0)<span class="text-violet-400"> · </span>@endif
                            <span class="font-semibold">{{ \Illuminate\Support\Str::of($link['label'])->before(' (via') }}</span>
                        @endforeach
                    </p>
                @endif
            </div>
            <span class="shrink-0 inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold {{ $statusTone }}">
                {{ display_label($appStatus, 'document_status') ?: ucfirst(str_replace('_', ' ', $appStatus)) }}
            </span>
        </div>

        @if ($appReview?->isRejected())
            <p class="mt-2 text-[11px] text-rose-800 font-medium">
                Fail: {{ $appReview->failReasonLabel() }}
                @if ($appReview->remedy === 'request_again')
                    · Replacement requested
                @endif
            </p>
        @endif

        @if ($priorVersions->isNotEmpty())
            <div class="mt-3 rounded-lg bg-slate-50 ring-1 ring-slate-200 px-3 py-2">
                <p class="text-[10px] uppercase tracking-widest font-bold text-slate-600">Compare with previous</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($priorVersions as $prior)
                        @if ($prior->file_path)
                            <x-admin.document-preview
                                :url="asset('storage/'.$prior->file_path)"
                                :label="'Prior · '.($prior->updated_at?->format('d M Y') ?? 'old')"
                                variant="button" />
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-3 flex flex-wrap gap-2 items-center">
            @if ($doc->file_path)
                <x-admin.document-preview
                    :url="asset('storage/'.$doc->file_path)"
                    label="Open"
                    variant="button" />
            @endif

            @if ($canReviewDocs && ! in_array($appStatus, ['verified', 'approved'], true))
                <form method="POST" action="{{ route('admin.loan-applications.documents.verify', [$record, $doc]) }}" class="inline">
                    @csrf
                    <input type="hidden" name="review_person" value="{{ $person }}">
                    @if ($gId)<input type="hidden" name="review_g" value="{{ $gId }}">@endif
                    @if ($mId)<input type="hidden" name="review_m" value="{{ $mId }}">@endif
                    <button type="submit" class="text-[11px] font-semibold text-emerald-800 bg-emerald-50 ring-1 ring-emerald-200 px-2.5 py-1.5 rounded-lg">
                        Mark reviewed ✓
                    </button>
                </form>
                <button type="button"
                        @click="openFail = !openFail"
                        class="text-[11px] font-semibold text-rose-800 bg-rose-50 ring-1 ring-rose-200 px-2.5 py-1.5 rounded-lg">
                    Fail
                </button>
            @endif
        </div>

        @if ($canReviewDocs)
            <div x-show="openFail" x-cloak class="mt-3 rounded-xl bg-rose-50/80 ring-1 ring-rose-100 p-3 space-y-2">
                <form method="POST" action="{{ route('admin.loan-applications.documents.reject', [$record, $doc]) }}" class="space-y-2">
                    @csrf
                    <input type="hidden" name="review_person" value="{{ $person }}">
                    @if ($gId)<input type="hidden" name="review_g" value="{{ $gId }}">@endif
                    @if ($mId)<input type="hidden" name="review_m" value="{{ $mId }}">@endif

                    <label class="block text-[10px] uppercase tracking-widest text-rose-800 font-semibold">Fail reason</label>
                    <select name="fail_reason_code" x-model="failReason" required
                            class="w-full rounded-lg border-rose-200 text-sm">
                        <option value="">Select reason…</option>
                        @foreach ($failReasons as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <textarea name="fail_reason_custom" rows="2" placeholder="Custom reason…"
                              x-show="failReason === 'custom'" x-cloak
                              class="w-full rounded-lg border-rose-200 text-sm"></textarea>

                    <label class="block text-[10px] uppercase tracking-widest text-rose-800 font-semibold">Remedy</label>
                    <select name="remedy" class="w-full rounded-lg border-rose-200 text-sm">
                        <option value="request_again">Request a new upload</option>
                        <option value="none">Fail only (no new request)</option>
                    </select>
                    <input type="text" name="request_again_label"
                           value="{{ $doc->documentType?->name }}"
                           placeholder="Request label"
                           class="w-full rounded-lg border-rose-200 text-sm">
                    <textarea name="notes" rows="2" placeholder="Extra note for the borrower (optional)"
                              class="w-full rounded-lg border-rose-200 text-sm"></textarea>
                    <button type="submit" class="inline-flex rounded-lg bg-rose-700 text-white text-xs font-bold px-3 py-2 hover:bg-rose-800">
                        Fail for this application
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
