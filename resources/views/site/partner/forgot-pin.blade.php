@php
    $badge = $step === 3 ? __('site.auth.shell.reset_pin') : ($step === 2 ? __('site.auth.shell.secure') : __('site.auth.shell.partner'));
    $heading = $step === 3
        ? __('site.auth.pin_recovery.pin_step_title')
        : ($step === 2 ? __('site.auth.pin_recovery.questions_title') : __('site.auth.partner_forgot_title'));
    $support = $step === 3
        ? __('site.auth.pin_recovery.pin_step_intro')
        : ($step === 2
            ? __('site.auth.pin_recovery.kba_intro', ['count' => max(2, $requiredCorrect), 'total' => count($questions)])
            : __('site.auth.partner_forgot_intro'));
@endphp
<x-site.auth-shell
    :title="brand_title(__('site.auth.partner_forgot_title'))"
    :badge="$badge"
    :heading="$heading"
    :support="$support"
    :aside-eyebrow="__('site.auth.partner_portal')"
    :aside-title="__('site.auth.partner_forgot_title')"
    :aside-body="__('site.auth.partner_forgot_intro')"
>
    @if (session('feedback.message'))
        <div class="mb-4 rounded-xl px-3 py-3 text-sm {{ (session('feedback.tone') ?? 'error') === 'success' ? 'bg-emerald-50 ring-1 ring-emerald-200 text-emerald-900' : 'bg-red-50 ring-1 ring-red-200 text-red-800' }}">
            {{ session('feedback.message') }}
        </div>
    @endif

    @if ($step === 1)
        <form method="POST" action="{{ route('site.partner.forgot-pin.start') }}" class="kf-auth-form" data-no-draft>
            @csrf
            <x-site.turnstile action="forgot-pin" />
            <div>
                <label class="kf-auth-label">{{ __('site.auth.partner_account_number') }}</label>
                <input name="partner_code" value="{{ $prefillCode }}" required autocomplete="off"
                       class="kf-auth-input font-mono uppercase">
                @error('partner_code')
                    <p class="kf-auth-error">{{ $message }}</p>
                @enderror
            </div>
            <x-site.phone-input name="phone" :label="__('site.auth.partner_phone_label')" :value="$prefillPhone" variant="rounded" :required="true" />
            <button class="kf-auth-btn">{{ __('site.auth.pin_recovery.continue') }}</button>
        </form>
    @elseif ($step === 2)
        <form method="POST" action="{{ route('site.partner.forgot-pin.verify') }}" class="kf-auth-form" data-no-draft>
            @csrf
            <input type="hidden" name="token" value="{{ $challengeToken }}">
            @foreach ($questions as $question)
                <div>
                    <label class="kf-auth-label">{{ $question['prompt'] }}</label>
                    <input type="text" name="answers[{{ $question['key'] }}]" required autocomplete="off" class="kf-auth-input">
                </div>
            @endforeach
            <button class="kf-auth-btn">{{ __('site.auth.pin_recovery.verify_cta') }}</button>
        </form>
    @else
        <form method="POST" action="{{ route('site.partner.forgot-pin.reset') }}" class="kf-auth-form" autocomplete="off" data-no-draft>
            @csrf
            <input type="hidden" name="token" value="{{ $challengeToken }}">
            <x-site.auth-pin name="pin" :label="__('site.auth.pin_recovery.new_pin')" />
            <x-site.auth-pin name="pin_confirmation" :label="__('site.auth.pin_recovery.confirm_pin')" />
            <button class="kf-auth-btn-gold">{{ __('site.auth.pin_recovery.save_pin_cta') }}</button>
        </form>
    @endif

    <p class="mt-5 text-center text-xs text-gray-500">
        <a href="{{ route('site.login', ['portal' => 'partner']) }}" class="font-semibold text-brand hover:underline">← {{ __('site.auth.pin_recovery.back_login') }}</a>
    </p>
</x-site.auth-shell>
