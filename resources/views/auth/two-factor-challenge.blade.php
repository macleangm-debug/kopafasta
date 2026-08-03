<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-factor verification · {{ brand_name() }}</title>
    @vite(['resources/css/app.css'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="h-full antialiased" x-data="{ mode: 'otp' }">
<div class="min-h-full grid lg:grid-cols-2">
    <aside class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-[#0B3D32] text-white px-10 py-12">
        <div class="absolute inset-0 opacity-40" style="background-image: radial-gradient(circle at 20% 20%, rgba(251,191,36,0.35), transparent 42%), radial-gradient(circle at 90% 80%, rgba(255,255,255,0.12), transparent 40%);"></div>
        <div class="relative">
            <img src="{{ asset(brand('logo_url_light') ?: brand('logo_url') ?: 'images/brand/kopafasta-logo-light.svg') }}"
                 alt="{{ brand_name() }}"
                 class="h-10 w-auto object-contain">
            <p class="mt-3 text-xs uppercase tracking-[0.2em] text-white/60">Every sign-in</p>
            <h1 class="mt-16 text-4xl font-bold leading-tight max-w-md">Confirm it’s you before opening the console.</h1>
            <p class="mt-4 text-sm text-white/70 max-w-sm">Open your authenticator app for a fresh 6-digit code. Codes change every ~30 seconds — the secret itself stays the same.</p>
        </div>
        <p class="relative text-xs text-white/50">© {{ date('Y') }} {{ brand('legal_name') }}</p>
    </aside>

    <main class="relative flex items-center justify-center px-4 py-10 sm:px-6 bg-[#F4F7F5]">
        <div class="absolute inset-0 opacity-60" style="background-image: linear-gradient(180deg, rgba(11,61,50,0.04), transparent 40%), radial-gradient(circle at 80% 10%, rgba(251,191,36,0.12), transparent 35%);"></div>
        <div class="relative w-full max-w-md rounded-3xl bg-white/95 p-6 sm:p-8 shadow-[0_24px_80px_rgba(11,61,50,0.12)] ring-1 ring-[#0B3D32]/10">
            <div class="lg:hidden mb-6">
                <img src="{{ asset(brand('logo_url') ?: 'images/brand/kopafasta-logo.svg') }}"
                     alt="{{ brand_name() }}"
                     class="h-9 w-auto object-contain">
            </div>

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
        </div>
    </main>
</div>

<x-site.feedback-modal name="default" />
@if ($errors->any())
    <script>
        document.addEventListener('alpine:initialized', () => {
            window.dispatchEvent(new CustomEvent('open-feedback-default', {
                detail: {
                    tone: 'error',
                    title: 'Verification failed',
                    message: @js($errors->first()),
                },
            }));
        });
    </script>
@endif
@vite('resources/js/alpine-init.js')
</body>
</html>
