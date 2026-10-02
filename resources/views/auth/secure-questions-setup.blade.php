<x-site.console-auth-shell
    title="{{ brand_title('Security questions') }}"
    aside-eyebrow="Secure access"
    aside-title="Choose three questions only you can answer."
    aside-body="Answers are stored securely and never shown again. One question will be asked on each new sign-in."
    card-class="max-w-lg"
    error-title="Could not save questions"
>
    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">One-time setup</p>
    <h2 class="mt-1 text-2xl font-bold tracking-tight text-gray-900">Set security questions</h2>
    <p class="mt-2 text-sm text-gray-500">Select three different questions and answer each one.</p>

    <form method="POST" action="{{ route('auth.secure.questions.setup.store') }}" class="mt-6 space-y-5">
        @csrf
        <input type="hidden" name="context" value="{{ $context }}">

        @foreach ([0, 1, 2] as $index)
            <div class="rounded-2xl ring-1 ring-gray-200 bg-gray-50/80 p-4 space-y-3">
                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600">Question {{ $index + 1 }}</label>
                <select name="question_keys[{{ $index }}]" required
                        class="block w-full rounded-xl border-0 ring-1 ring-gray-200 focus:ring-2 focus:ring-brand text-sm px-3 py-2.5 bg-white">
                    @foreach ($bank as $key => $meta)
                        <option value="{{ $key }}" @selected(($questionKeys[$index] ?? null) === $key)>
                            {{ __($meta['prompt_key']) }}
                        </option>
                    @endforeach
                </select>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Your answer</label>
                    <input type="text"
                           name="answer_values[{{ $index }}]"
                           value="{{ old('answer_values.'.$index) }}"
                           required
                           autocomplete="off"
                           maxlength="120"
                           class="block w-full rounded-xl border-0 ring-1 ring-gray-200 focus:ring-2 focus:ring-brand text-base px-3.5 py-2.5 bg-white">
                </div>
            </div>
        @endforeach

        <button type="submit" data-loading-label="Saving…"
                class="w-full bg-brand-gold hover:bg-yellow-400 text-brand font-bold rounded-xl py-3 shadow-sm transition">
            Save security questions
        </button>
    </form>
</x-site.console-auth-shell>
