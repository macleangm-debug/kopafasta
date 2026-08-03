@php
    $adviceOptions = $adviceOptions
        ?? $rejectionAdviceOptions
        ?? app(\App\Services\LoanRejectionReasonService::class)->adviceOptions();
    $selectedCodes = old('rejection_reason_codes', $selectedRejectionCodes ?? []);
    if (! is_array($selectedCodes)) {
        $selectedCodes = filled($selectedCodes) ? [(string) $selectedCodes] : [];
    }
    if ($selectedCodes === [] && filled(old('rejection_reason_code', $fallbackRejectionCode ?? null))) {
        $selectedCodes = [(string) old('rejection_reason_code', $fallbackRejectionCode)];
    }
@endphp

<div>
    <label class="block text-xs font-semibold text-gray-700 mb-1.5">
        Rejection reasons <span class="text-red-500">*</span>
    </label>
    <p class="text-[11px] text-gray-500 mb-2">Tick all that apply — the borrower sees these in their language.</p>
    <div class="max-h-56 overflow-y-auto rounded-xl ring-1 ring-brand/15 bg-white divide-y divide-gray-100">
        @foreach (($rejectionReasons ?? []) as $category => $reasons)
            <div class="px-3 py-2.5">
                <p class="text-[10px] uppercase tracking-widest font-semibold text-brand mb-2">{{ $category }}</p>
                <div class="grid sm:grid-cols-2 gap-1.5">
                    @foreach ($reasons as $reason)
                        <label class="flex items-start gap-2 text-sm text-gray-800 rounded-lg px-2 py-1.5 hover:bg-brand-muted/40">
                            <input type="checkbox"
                                   name="rejection_reason_codes[]"
                                   value="{{ $reason['code'] }}"
                                   @checked(in_array($reason['code'], $selectedCodes, true))
                                   class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand/30"
                                   @if (! empty($disabledWhen)) :disabled="{{ $disabledWhen }}" @endif>
                            <span>{{ $reason['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

<div>
    <label class="block text-xs font-semibold text-gray-700 mb-1.5">
        Advice for borrower <span class="font-normal text-gray-400">(optional)</span>
    </label>
    <select name="rejection_advice_code"
            x-model="advice"
            class="w-full rounded-xl border-0 text-sm ring-1 ring-brand/15 px-4 py-3 focus:ring-2 focus:ring-brand/30"
            @if (! empty($disabledWhen)) :disabled="{{ $disabledWhen }}" @endif>
        <option value="">No preset advice</option>
        @foreach ($adviceOptions as $code => $label)
            @if ($code !== 'custom')
                <option value="{{ $code }}">{{ $label }}</option>
            @endif
        @endforeach
        <option value="custom">Custom advice only</option>
    </select>
    <p class="mt-1 text-[11px] text-gray-500">Preset advice is translated to the borrower’s language automatically.</p>
</div>

<div>
    <label class="block text-xs font-semibold text-gray-700 mb-1.5">
        Open field advice <span class="font-normal text-gray-400">(optional)</span>
    </label>
    <textarea name="rejection_advice" rows="3" maxlength="2000"
              placeholder="Extra guidance for the borrower (shown as written)"
              class="w-full rounded-xl border-0 text-sm ring-1 ring-brand/15 px-4 py-3 focus:ring-2 focus:ring-brand/30"
              @if (! empty($disabledWhen)) :disabled="{{ $disabledWhen }}" @endif>{{ old('rejection_advice') }}</textarea>
</div>

<div>
    <label class="block text-xs font-semibold text-gray-700 mb-1.5">
        Internal notes <span class="font-normal text-gray-400">(optional)</span>
    </label>
    <textarea name="rejection_internal_notes" rows="2" maxlength="2000"
              placeholder="Private notes for staff only"
              class="w-full rounded-xl border-0 text-sm ring-1 ring-brand/15 px-4 py-3 focus:ring-2 focus:ring-brand/30"
              @if (! empty($disabledWhen)) :disabled="{{ $disabledWhen }}" @endif>{{ old('rejection_internal_notes') }}</textarea>
</div>
