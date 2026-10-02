@php
    $isSw = str_starts_with(app()->getLocale(), 'sw');
    // Match frozen enrollment size (server validates array size). Progress reflects that count.
    $enrollCount = max(1, count($questionKeys ?: [0, 1, 2]));
    $indices = range(0, $enrollCount - 1);
@endphp
<x-site.console-auth-shell
    title="{{ brand_title($isSw ? 'Maswali ya usalama' : 'Security questions') }}"
    :badge="$isSw ? 'Linda akaunti yako' : 'Secure your account'"
    :heading="$isSw ? 'Linda akaunti yako' : 'Secure your account'"
    :support="$isSw ? 'Chagua maswali yako ya usalama kwa kuingia baadaye.' : 'Choose your security questions for future sign-ins.'"
    :aside-eyebrow="$isSw ? 'Ufikiaji salama' : 'Secure access'"
    aside-title="Choose questions only you can answer."
    aside-body="Answers are stored securely and never shown again. One question will be asked on each new sign-in."
    card-class="max-w-lg"
    :error-title="$isSw ? 'Imeshindikana kuhifadhi maswali' : 'Could not save questions'"
>
    <form method="POST" action="{{ route('auth.secure.questions.setup.store') }}"
          class="kf-auth-form"
          x-ref="kbaForm"
          x-data="{
              step: 0,
              total: {{ $enrollCount }},
              next() {
                  const form = this.$refs.kbaForm;
                  const sel = form.querySelector('select[name=\"question_keys[' + this.step + ']\"]');
                  const ans = form.querySelector('input[name=\"answer_values[' + this.step + ']\"]');
                  if (sel && ! sel.value) { sel.focus(); sel.reportValidity(); return; }
                  if (ans && ! String(ans.value || '').trim()) { ans.focus(); ans.reportValidity(); return; }
                  this.step = Math.min(this.total - 1, this.step + 1);
              },
              back() { this.step = Math.max(0, this.step - 1); }
          }">
        @csrf
        <input type="hidden" name="context" value="{{ $context }}">

        <div class="flex items-center justify-between gap-3">
            <p class="text-xs font-semibold text-brand tabular-nums"
               x-text="'{{ $isSw ? 'Swali' : 'Question' }} ' + (step + 1) + ' {{ $isSw ? 'kati ya' : 'of' }} ' + total"></p>
            <div class="flex gap-1.5" aria-hidden="true">
                @foreach ($indices as $i)
                    <span class="h-1.5 w-6 rounded-full transition"
                          :class="{{ $i }} <= step ? 'bg-brand' : 'bg-gray-200'"></span>
                @endforeach
            </div>
        </div>

        @foreach ($indices as $index)
            <div x-show="step === {{ $index }}" @if ($index > 0) x-cloak @endif class="space-y-3.5 rounded-2xl ring-1 ring-brand/10 bg-brand-muted/20 p-4">
                <div>
                    <label class="kf-auth-label">{{ $isSw ? 'Chagua swali' : 'Choose a question' }}</label>
                    <select name="question_keys[{{ $index }}]"
                            class="kf-auth-input"
                            :required="step === {{ $index }}">
                        @foreach ($bank as $key => $meta)
                            <option value="{{ $key }}" @selected(($questionKeys[$index] ?? null) === $key)>
                                {{ __($meta['prompt_key']) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="kf-auth-label">{{ $isSw ? 'Jibu lako' : 'Your answer' }}</label>
                    <input type="text"
                           name="answer_values[{{ $index }}]"
                           value="{{ old('answer_values.'.$index) }}"
                           autocomplete="off"
                           maxlength="120"
                           class="kf-auth-input"
                           :required="step === {{ $index }}">
                </div>
            </div>
        @endforeach

        <div class="flex gap-2">
            <button type="button" x-show="step > 0" x-cloak @click="back()"
                    class="flex-1 rounded-xl bg-white ring-1 ring-brand/20 text-brand font-bold py-3.5 text-sm hover:bg-brand-muted/40 transition">
                {{ $isSw ? 'Nyuma' : 'Back' }}
            </button>
            <button type="button" x-show="step < total - 1" @click="next()"
                    class="flex-1 kf-auth-btn">
                {{ $isSw ? 'Endelea' : 'Continue' }}
            </button>
            <button type="submit" x-show="step === total - 1" x-cloak
                    data-loading-label="{{ $isSw ? 'Inahifadhi maswali…' : 'Saving security questions…' }}"
                    class="flex-1 kf-auth-btn-gold">
                {{ $isSw ? 'Hifadhi maswali' : 'Save security questions' }}
            </button>
        </div>
    </form>
</x-site.console-auth-shell>
