@php
    $approvalReasons = config('credit_recommendation.approval_reasons', []);
@endphp

<div>
    <label class="block text-xs font-semibold text-gray-700 mb-1.5">
        Reason for approval <span class="text-red-500">*</span>
    </label>
    <select name="approval_reason_code"
            x-model="approvalReason"
            required
            class="w-full rounded-xl border-0 text-sm ring-1 ring-brand/15 px-4 py-3 focus:ring-2 focus:ring-brand/30">
        <option value="">Select…</option>
        @foreach ($approvalReasons as $code => $label)
            <option value="{{ $code }}" @selected(old('approval_reason_code') === $code)>{{ $label }}</option>
        @endforeach
    </select>
</div>

<div>
    <label class="block text-xs font-semibold text-gray-700 mb-1.5">
        <span x-text="approvalReason === 'custom' ? 'Custom approval reason' : 'Additional notes'"></span>
        <span class="text-red-500" x-show="approvalReason === 'custom'" x-cloak>*</span>
        <span class="font-normal text-gray-400" x-show="approvalReason !== 'custom'">(optional)</span>
    </label>
    <textarea name="approval_reason_notes" rows="3" maxlength="1000"
              :required="approvalReason === 'custom'"
              :placeholder="approvalReason === 'custom' ? 'Explain why the committee is approving…' : 'Anything else for the file…'"
              class="w-full rounded-xl border-0 text-sm ring-1 ring-brand/15 px-4 py-3 focus:ring-2 focus:ring-brand/30">{{ old('approval_reason_notes') }}</textarea>
</div>
