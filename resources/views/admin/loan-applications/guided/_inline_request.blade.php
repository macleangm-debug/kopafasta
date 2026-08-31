@php
    $requestable = $step['requestable'] ?? null;
    $dueDays = app(\App\Services\UnderwritingSettingsService::class)->documentRequestDefaultDueDays();
    $participant = $step['participant'] ?? [];
    $alternatives = $requestable['alternatives'] ?? [];
    $itemKey = (string) ($step['item_key'] ?? '');
    $requests = $record->relationLoaded('documentRequests')
        ? $record->documentRequests
        : $record->documentRequests()->orderByDesc('id')->get();
    $openRequest = $requests
        ->first(function ($row) use ($itemKey, $participant) {
            if ($itemKey !== '' && (string) $row->checklist_item === $itemKey && $row->needsBorrowerAction()) {
                return true;
            }
            $m = (int) ($participant['m'] ?? 0);

            return ($participant['person'] ?? '') === 'member'
                && $m > 0
                && (int) $row->loan_group_member_id === $m
                && $row->needsBorrowerAction()
                && app(\App\Services\ApplicationDocumentRequestService::class)->borrowerActionKind($row) === 'identity';
        });
    $showComposer = ! empty($requestable['preset'])
        && (
            ! empty($nationalIdMissing)
            || ($step['type'] ?? '') === 'request'
            || ($step['verdict'] ?? null) === 'fail'
        );
    $headline = $requestable['headline'] ?? 'National ID required';
    $reason = $requestable['reason'] ?? 'National ID is not on this member\'s profile.';
@endphp
@if ($openRequest)
    <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 space-y-2">
        <p class="text-sm font-bold text-emerald-800">Request sent ✓</p>
        <p class="text-base font-bold text-slate-900">{{ $openRequest->label }}</p>
        <p class="text-sm font-semibold text-amber-950">Waiting for member</p>
        @if ($openRequest->request_reason)
            <p class="text-xs text-slate-700">Reason: {{ $openRequest->request_reason }}</p>
        @endif
        @if ($openRequest->due_at)
            <p class="text-xs text-slate-600">Deadline: <span class="font-bold text-slate-900">{{ $dueDays }} days · set by Screening policy</span></p>
        @endif
    </div>
@elseif ($showComposer)
    <div class="rounded-2xl bg-white ring-1 ring-brand/15 px-4 py-4 space-y-3" x-data="{ open: true, preset: @js($requestable['preset']), step: 'review' }">
        <p class="text-base font-bold text-slate-900">{{ $headline }}</p>
        <form method="POST" action="{{ route('admin.loan-applications.document-requests.store', $record) }}"
              class="space-y-3" data-no-draft>
            @csrf
            <input type="hidden" name="type" value="document">
            <input type="hidden" name="presets[]" :value="preset">
            <input type="hidden" name="subject_kind" value="{{ $participant['person'] ?? 'borrower' }}">
            @if (! empty($participant['m']))
                <input type="hidden" name="loan_group_member_id" value="{{ $participant['m'] }}">
            @endif
            <input type="hidden" name="return_workspace" value="guided">
            <input type="hidden" name="confirmed" value="1">
            <input type="hidden" name="open_item" value="{{ $step['item_key'] ?? '' }}">
            <input type="hidden" name="gate" value="{{ $step['gate'] ?? '' }}">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Reason</p>
                <input type="text" name="request_reason" maxlength="500"
                       value="{{ $reason }}"
                       class="mt-1 w-full rounded-xl border-slate-300 text-sm">
            </div>
            @if ($alternatives !== [])
                <label class="block text-xs font-bold text-slate-600">Request another supporting document</label>
                <select x-model="preset" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="{{ $requestable['preset'] }}">{{ $requestable['label'] }}</option>
                    @foreach ($alternatives as $alt)
                        <option value="{{ $alt['preset'] }}">{{ $alt['label'] }}</option>
                    @endforeach
                </select>
            @endif
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Deadline</p>
                <p class="mt-1 text-sm font-bold text-slate-900">{{ $dueDays }} days · set by Screening policy</p>
            </div>
            <button type="button" x-show="step === 'review'" @click="step = 'confirm'"
                    class="w-full rounded-xl bg-brand text-white font-bold text-sm py-2.5 hover:bg-brand-light">
                Review & send request
            </button>
            <div x-show="step === 'confirm'" x-cloak class="space-y-2">
                <p class="text-sm text-slate-800">Send this request and pause Screening until it is received?</p>
                <div class="flex gap-2">
                    <button type="button" @click="step = 'review'" class="flex-1 rounded-xl bg-white ring-1 ring-brand/30 text-brand font-bold text-sm py-2.5">Go back</button>
                    <button type="submit" data-loading-label="Sending…" class="flex-1 rounded-xl bg-brand text-white font-bold text-sm py-2.5">Send request</button>
                </div>
            </div>
        </form>
    </div>
@endif
