@php
    $requestable = $step['requestable'] ?? null;
    $dueDays = app(\App\Services\UnderwritingSettingsService::class)->documentRequestDefaultDueDays();
    $participant = $step['participant'] ?? [];
    $alternatives = $requestable['alternatives'] ?? [];
@endphp
@if (! empty($requestable['preset']))
    <div class="rounded-xl ring-1 ring-slate-200 px-3 py-3 space-y-2" x-data="{ open: false, preset: @js($requestable['preset']) }">
        <button type="button" @click="open = !open" class="text-sm font-bold text-brand underline">
            Request document
        </button>
        <form method="POST" action="{{ route('admin.loan-applications.document-requests.store', $record) }}"
              x-show="open" x-cloak class="space-y-2" x-data="{ step: 'review' }" data-no-draft>
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
            <p class="text-sm font-semibold text-slate-900" x-text="preset">{{ $requestable['label'] }}</p>
            @if ($alternatives !== [])
                <label class="block text-xs font-bold text-slate-600">Request another supporting document</label>
                <select x-model="preset" class="w-full rounded-xl border-slate-300 text-sm">
                    <option value="{{ $requestable['preset'] }}">{{ $requestable['label'] }}</option>
                    @foreach ($alternatives as $alt)
                        <option value="{{ $alt['preset'] }}">{{ $alt['label'] }}</option>
                    @endforeach
                </select>
            @endif
            <label class="block text-xs font-bold text-slate-600">Reason</label>
            <input type="text" name="request_reason" maxlength="500"
                   value="{{ $requestable['label'] }}"
                   class="w-full rounded-xl border-slate-300 text-sm">
            <p class="text-xs text-slate-600">Due in {{ $dueDays }} days — set by Screening policy</p>
            <button type="button" x-show="step === 'review'" @click="step = 'confirm'"
                    class="w-full rounded-xl bg-white ring-1 ring-brand/30 text-brand font-bold text-sm py-2.5">
                Review request
            </button>
            <div x-show="step === 'confirm'" x-cloak class="space-y-2">
                <p class="text-sm text-slate-800">Send this request and pause Screening until it is received?</p>
                <div class="flex gap-2">
                    <button type="button" @click="step = 'review'" class="flex-1 rounded-xl bg-white ring-1 ring-slate-200 font-bold text-sm py-2.5">Go back</button>
                    <button type="submit" data-loading-label="Sending…" class="flex-1 rounded-xl bg-brand text-white font-bold text-sm py-2.5">Send request</button>
                </div>
            </div>
        </form>
    </div>
@endif
