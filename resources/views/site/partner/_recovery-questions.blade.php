@php
    $bank = $questions ?? [];
    $options = collect($bank)->mapWithKeys(fn ($meta, $key) => [$key => __($meta['prompt_key'] ?? $key)]);
@endphp

<div class="space-y-4" x-data="{ q1: @js(old('question_keys.0', '')), q2: @js(old('question_keys.1', '')) }">
    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('site.auth.pin_recovery.enroll_questions_label') }}</p>
    @foreach ([1, 2] as $n)
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('site.auth.partner_question_'.$n) }}</label>
            <select name="question_keys[]" x-model="q{{ $n }}" required
                    class="w-full rounded-xl border border-gray-200 px-3.5 py-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/10">
                <option value="">{{ __('site.auth.partner_pick_question') }}</option>
                @foreach ($options as $key => $label)
                    <option value="{{ $key }}" :disabled="q{{ $n === 1 ? 2 : 1 }} === @js($key)">{{ $label }}</option>
                @endforeach
            </select>
            <input type="text"
                   :name="q{{ $n }} ? ('answers[' + q{{ $n }} + ']') : 'answers[_empty_{{ $n }}]'"
                   value="{{ old('answers.'.old('question_keys.'.($n - 1), '')) }}"
                   required
                   autocomplete="off"
                   class="mt-2 w-full rounded-xl border border-gray-200 px-3.5 py-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/10"
                   :disabled="!q{{ $n }}">
        </div>
    @endforeach
    @error('question_keys')
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
    @error('answers')
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
