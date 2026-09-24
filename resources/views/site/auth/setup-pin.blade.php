@php
    $needsPin = $needsPin ?? true;
    $questions = $questions ?? [];
    $phase = $phase ?? ($needsPin ? 'pin' : 'questions');
    $secureAccountPost = $secureAccountPost ?? route('site.borrower.setup-pin.post');
    $secureAccountSwap = $secureAccountSwap ?? route('site.borrower.setup-pin.swap');
    $partnerContext = $partnerContext ?? null;
    $isQuestions = $phase === 'questions';
    $badge = $isQuestions ? __('site.auth.shell.secure') : __('site.auth.shell.create_pin');
    $heading = $isQuestions ? __('site.auth.pin_recovery.recovery_only_title') : __('site.auth.pin_recovery.setup_title');
    $support = $isQuestions ? __('site.auth.shell.secure_support') : __('site.auth.pin_recovery.setup_pin_only_body');
    $identity = $partnerContext?->name;
@endphp
<x-site.auth-shell
    :title="brand_title($isQuestions ? __('site.auth.pin_recovery.recovery_only_title') : __('site.auth.pin_recovery.setup_title'))"
    :badge="$badge"
    :heading="$heading"
    :support="$support"
    :identity="$identity"
    :aside-eyebrow="__('site.auth.pin_recovery.setup_eyebrow')"
    :aside-title="__('site.auth.pin_recovery.setup_aside_title')"
    :aside-body="__('site.auth.pin_recovery.setup_aside_body')"
>
    @if ($isQuestions)
        <form method="POST" action="{{ $secureAccountPost }}" class="kf-auth-form" autocomplete="off" data-no-draft>
            @csrf
            <input type="hidden" name="phase" value="questions">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('site.auth.pin_recovery.enroll_questions_label') }}</p>
            @foreach ($questions as $index => $question)
                <div>
                    <div class="flex items-start justify-between gap-3 mb-1">
                        <label class="kf-auth-label mb-0">
                            <span class="text-xs font-semibold text-brand mr-1">{{ $index + 1 }}.</span>
                            {{ $question['prompt'] }}
                        </label>
                        <button
                            type="submit"
                            formaction="{{ $secureAccountSwap }}"
                            formmethod="post"
                            name="index"
                            value="{{ $index }}"
                            formnovalidate
                            class="shrink-0 text-xs font-semibold text-brand hover:underline"
                        >
                            {{ __('site.auth.pin_recovery.change_question') }}
                        </button>
                    </div>
                    <input
                        type="text"
                        name="answers[{{ $question['key'] }}]"
                        value="{{ old('answers.'.$question['key']) }}"
                        required
                        autocomplete="off"
                        @if (($question['input'] ?? '') === 'digits')
                            inputmode="numeric"
                            data-digits-only
                            maxlength="{{ $question['digits'] ?? 4 }}"
                            pattern="{{ '\\d{'.($question['digits'] ?? 4).'}' }}"
                        @endif
                        class="kf-auth-input"
                    >
                    @error('answers.'.$question['key'])
                        <p class="kf-auth-error">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
            <p class="kf-auth-help">{{ __('site.auth.pin_recovery.setup_hint') }}</p>
            <button class="kf-auth-btn-gold">{{ __('site.auth.pin_recovery.recovery_only_cta') }}</button>
        </form>
    @else
        <form method="POST" action="{{ $secureAccountPost }}" class="kf-auth-form" autocomplete="off" data-no-draft>
            @csrf
            <input type="hidden" name="phase" value="pin">
            <x-site.auth-pin name="pin" autocomplete="one-time-code" />
            <x-site.auth-pin name="pin_confirmation" :label="__('site.auth.pin_recovery.confirm_pin')" autocomplete="one-time-code" />
            <p class="kf-auth-help">{{ __('site.auth.pin_recovery.setup_pin_only_hint') }}</p>
            <button class="kf-auth-btn-gold">{{ __('site.auth.pin_recovery.setup_pin_only_cta') }}</button>
        </form>
    @endif
</x-site.auth-shell>
