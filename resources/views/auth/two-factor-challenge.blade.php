@php
    $isSw = str_starts_with(app()->getLocale(), 'sw');
@endphp
<x-site.console-auth-shell
    x-data="{ mode: 'otp' }"
    title="{{ brand_title($isSw ? 'Uthibitishaji wa hatua mbili' : 'Two-factor verification') }}"
    :badge="$isSw ? 'Kila kuingia' : 'Every sign-in'"
    :heading="$isSw ? 'Thibitisha ni wewe' : 'Verify it’s you'"
    :support="$isSw ? 'Weka msimbo kutoka programu yako ya authenticator ili uendelee.' : 'Enter the code from your authenticator app to continue.'"
    :aside-eyebrow="$isSw ? 'Kila kuingia' : 'Every sign-in'"
    aside-title="Confirm it’s you before opening the console."
    aside-body="Open your authenticator app for a fresh 6-digit code. Codes change every ~30 seconds."
    :error-title="$isSw ? 'Uthibitishaji umeshindikana' : 'Verification failed'"
>
    <p class="text-sm text-gray-600" x-show="mode === 'otp'" x-cloak>
        {{ $isSw ? 'Weka msimbo wa tarakimu 6 kutoka programu yako ya authenticator.' : 'Enter the 6-digit code from your authenticator app.' }}
    </p>
    <p class="text-sm text-gray-600" x-show="mode === 'recovery'" x-cloak>
        {{ $isSw ? 'Weka msimbo mmoja wa urejesho ambao haujatumika. Kila msimbo hutumika mara moja.' : 'Enter one unused recovery code from when you set up 2FA. Each recovery code works once.' }}
    </p>

    <form method="POST" action="{{ route('auth.two-factor.verify') }}" class="kf-auth-form mt-4">
        @csrf
        <input type="hidden" name="context" value="{{ $context }}">

        <fieldset x-show="mode === 'otp'" x-cloak class="min-w-0 border-0 p-0 m-0" :disabled="mode !== 'otp'">
            <x-auth.otp-digits name="code" :length="6" :autofocus="true" :label="$isSw ? 'Msimbo wa uthibitishaji' : 'Authentication code'" />
        </fieldset>

        <fieldset disabled x-show="mode === 'recovery'" x-cloak class="min-w-0 border-0 p-0 m-0" :disabled="mode !== 'recovery'">
            <label class="kf-auth-label">{{ $isSw ? 'Msimbo wa urejesho' : 'Recovery code' }}</label>
            <input type="text" name="code"
                   autocomplete="off" spellcheck="false"
                   class="kf-auth-input font-mono tracking-wide"
                   placeholder="e.g. 2oxinuh7pk">
        </fieldset>

        <button type="submit"
                data-loading-label="{{ $isSw ? 'Inathibitisha…' : 'Verifying…' }}"
                class="kf-auth-btn-gold">
            {{ $isSw ? 'Thibitisha' : 'Verify' }}
        </button>
    </form>

    <p class="mt-5 text-center text-sm text-gray-600">
        <button type="button" class="font-semibold text-brand hover:underline"
                @click="mode = mode === 'otp' ? 'recovery' : 'otp'"
                x-text="mode === 'otp'
                    ? @js($isSw ? 'Umepoteza simu? Tumia msimbo wa urejesho' : 'Lost your phone? Use a recovery code')
                    : @js($isSw ? 'Tumia msimbo wa authenticator badala yake' : 'Use authenticator app code instead')">
        </button>
    </p>
</x-site.console-auth-shell>
