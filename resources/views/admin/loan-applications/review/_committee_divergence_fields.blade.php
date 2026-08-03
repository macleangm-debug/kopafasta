@php
    $atCommittee = ($record->current_stage ?? '') === 'pre_approval'
        && filled($record->recommendation_type);
    $showCommitteeDivergence = $atCommittee && ($committeeDiffers ?? true);
@endphp
@if ($showCommitteeDivergence)
    <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3 py-3 space-y-3">
        <p class="text-xs font-semibold text-amber-950">
            This differs from screening ({{ str_replace('_', ' ', $record->recommendation_type) }}) — explain why.
        </p>
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Why different from screening <span class="text-red-500">*</span></label>
            <select name="committee_rationale" required
                    class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">
                <option value="">Select…</option>
                @foreach (config('credit_recommendation.committee_rationales', []) as $code => $label)
                    <option value="{{ $code }}" @selected(old('committee_rationale') === $code)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Committee notes <span class="text-red-500">*</span></label>
            <textarea name="remarks" rows="2" maxlength="1000" required
                      placeholder="Explain how and why your decision differs from screening."
                      class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 focus:ring-brand focus:border-brand">{{ old('remarks') }}</textarea>
        </div>
    </div>
@endif
