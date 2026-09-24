@php
    $step = (int) ($step ?? old('step', 1));
    $questions = $questions ?? [];
    $requiredCorrect = (int) ($requiredCorrect ?? 2);
    $totalQuestions = max(count($questions), 2);
    $expiresAt = (int) ($expiresAt ?? 0);
    $badge = $step === 3 ? __('site.auth.shell.reset_pin') : ($step === 2 ? __('site.auth.shell.secure') : __('site.auth.shell.reset_pin'));
    $heading = $step === 3
        ? __('site.auth.pin_recovery.pin_step_title')
        : ($step === 2 ? __('site.auth.pin_recovery.questions_title') : __('site.auth.shell.forgot_heading'));
    $support = $step === 3
        ? __('site.auth.pin_recovery.pin_step_intro')
        : ($step === 2
            ? __('site.auth.pin_recovery.kba_intro', ['count' => max(2, $requiredCorrect), 'total' => $totalQuestions])
            : __('site.auth.shell.forgot_support'));
@endphp
<x-site.auth-shell
    :title="brand_title(__('site.auth.pin_recovery.title'))"
    :badge="$badge"
    :heading="$heading"
    :support="$support"
    :aside-eyebrow="__('site.auth.pin_recovery.aside_eyebrow')"
    :aside-title="__('site.auth.pin_recovery.aside_title')"
    :aside-body="__('site.auth.pin_recovery.aside_body')"
>
    @if (in_array($step, [2, 3], true) && $expiresAt > 0)
        <div
            class="mb-4"
            x-data="{
                endsAt: {{ $expiresAt }} * 1000,
                left: 0,
                total: Math.max(1, {{ $expiresAt }} - Math.floor(Date.now() / 1000)),
                tick() {
                    this.left = Math.max(0, Math.ceil((this.endsAt - Date.now()) / 1000));
                    if (this.left <= 0) {
                        window.location = @js(route('site.forgot-pin'));
                    }
                },
                label() {
                    const m = Math.floor(this.left / 60);
                    const s = String(this.left % 60).padStart(2, '0');
                    return m + ':' + s;
                },
                init() {
                    this.tick();
                    setInterval(() => this.tick(), 250);
                }
            }"
        >
            <div class="flex items-center justify-between gap-3 text-xs font-semibold text-gray-600">
                <span>{{ __('site.auth.pin_recovery.timer_label') }}</span>
                <span class="tabular-nums text-brand" x-text="label()"></span>
            </div>
            <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full rounded-full bg-brand transition-[width] duration-200"
                     :style="'width:' + Math.max(0, Math.min(100, (left / total) * 100)) + '%'"></div>
            </div>
        </div>
    @endif

    @if ($step === 1)
        <form method="POST" action="{{ route('site.forgot-pin.start') }}" class="kf-auth-form">
            @csrf
            <x-site.phone-input name="phone" :label="__('site.auth.partner_phone_label')" :value="old('phone', $prefillPhone ?? null)" variant="rounded" :required="true" :show-errors="false" />
            <x-site.turnstile action="forgot-pin" />
            <button type="submit" class="kf-auth-btn">{{ __('site.auth.pin_recovery.continue') }}</button>
        </form>
    @elseif ($step === 2)
        <form method="POST" action="{{ route('site.forgot-pin.verify-challenge') }}" class="kf-auth-form">
            @csrf
            <input type="hidden" name="token" value="{{ $challengeToken }}">
            <input type="hidden" name="phone" value="{{ old('phone', $prefillPhone) }}">
            @foreach ($questions as $index => $question)
                @php $field = 'answers.'.$question['key']; @endphp
                <div>
                    @if (($question['input'] ?? '') === 'date' || str_contains($question['key'], 'dob'))
                        <x-site.date-input
                            :name="'answers['.$question['key'].']'"
                            :label="($index + 1).'. '.$question['prompt']"
                            :value="old($field)"
                            :required="true"
                            min="1940-01-01"
                            :max="now()->subYears(18)->format('Y-m-d')"
                        />
                    @else
                        <label class="kf-auth-label">
                            <span class="text-xs font-semibold text-brand mr-1">{{ $index + 1 }}.</span>
                            {{ $question['prompt'] }}
                        </label>
                        <input type="text"
                               name="answers[{{ $question['key'] }}]"
                               value="{{ old($field) }}"
                               autocomplete="off"
                               @if (($question['input'] ?? '') === 'digits')
                                   inputmode="numeric"
                                   maxlength="{{ $question['digits'] ?? 4 }}"
                               @endif
                               class="kf-auth-input">
                    @endif
                </div>
            @endforeach
            <button type="submit" class="kf-auth-btn">{{ __('site.auth.pin_recovery.verify_cta') }}</button>
        </form>
    @else
        <form method="POST" action="{{ route('site.forgot-pin.reset-challenge') }}" class="kf-auth-form" autocomplete="off">
            @csrf
            <input type="hidden" name="token" value="{{ $challengeToken }}">
            <input type="hidden" name="phone" value="{{ old('phone', $prefillPhone) }}">
            <x-site.auth-pin name="pin" :label="__('site.auth.pin_recovery.new_pin')" />
            <x-site.auth-pin name="pin_confirmation" :label="__('site.auth.pin_recovery.confirm_pin')" />
            <button type="submit" class="kf-auth-btn">{{ __('site.auth.pin_recovery.save_pin_cta') }}</button>
        </form>
    @endif

    <p class="mt-5 text-sm text-gray-600">
        <a href="{{ route('site.login', array_filter(['phone' => $prefillPhone ?? null])) }}" class="text-brand font-semibold hover:underline">
            ← {{ __('site.auth.pin_recovery.back_login') }}
        </a>
    </p>
</x-site.auth-shell>
