<x-site.console-auth-shell
    x-data="{ mode: 'otp' }"
    title="{{ brand_title('Two-factor verification') }}"
    aside-eyebrow="Every sign-in"
    aside-title="Confirm it’s you before opening the console."
    aside-body="Open your authenticator app for a fresh 6-digit code. Codes change every ~30 seconds."
    error-title="Verification failed"
>
    <h2 class="text-2xl font-bold tracking-tight text-gray-900">Two-factor verification</h2>
    <p class="mt-2 text-sm text-gray-500" x-show="mode === 'otp'" x-cloak>
        Enter the 6-digit code from your authenticator app.
    </p>
    <p class="mt-2 text-sm text-gray-500" x-show="mode === 'recovery'" x-cloak>
        Enter one unused recovery code from when you set up 2FA. Each recovery code works once.
    </p>

    <form method="POST" action="{{ route('auth.two-factor.verify') }}" class="mt-6 space-y-5">
        @csrf
        <input type="hidden" name="context" value="{{ $context }}">

        <fieldset x-show="mode === 'otp'" x-cloak class="min-w-0 border-0 p-0 m-0" :disabled="mode !== 'otp'">
            <x-auth.otp-digits name="code" :length="6" :autofocus="true" label="Authentication code" />
        </fieldset>

        <fieldset disabled x-show="mode === 'recovery'" x-cloak class="min-w-0 border-0 p-0 m-0" :disabled="mode !== 'recovery'">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-2">Recovery code</label>
            <input type="text" name="code"
                   autocomplete="off" spellcheck="false"
                   class="block w-full rounded-xl border-0 ring-1 ring-gray-200 focus:ring-2 focus:ring-brand text-base px-3.5 py-2.5 bg-white font-mono tracking-wide"
                   placeholder="e.g. 2oxinuh7pk">
        </fieldset>

        <button type="submit"
                class="w-full bg-brand-gold hover:bg-yellow-400 text-brand font-bold rounded-xl py-3 shadow-sm transition">
            Verify
        </button>
    </form>

    <p class="mt-5 text-center text-sm text-gray-600">
        <button type="button" class="font-semibold text-brand hover:underline"
                @click="mode = mode === 'otp' ? 'recovery' : 'otp'"
                x-text="mode === 'otp' ? 'Lost your phone? Use a recovery code' : 'Use authenticator app code instead'">
        </button>
    </p>
</x-site.console-auth-shell>
