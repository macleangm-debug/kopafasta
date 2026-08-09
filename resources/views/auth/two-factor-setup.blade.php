{{-- Set up two-factor with scannable QR. Expects: $secret, $provisioning_uri, $recovery_codes, $context --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set up two-factor · {{ brand_name() }}</title>
    @vite(['resources/css/app.css'])
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="h-full antialiased" x-data>
<div class="min-h-full grid lg:grid-cols-2">
    <aside class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-brand text-white px-10 py-12"
           style="background-color: #0B3D32; color: #fff;">
        <div class="absolute inset-0 opacity-40 pointer-events-none" style="background-image: radial-gradient(circle at 20% 20%, rgba(251,191,36,0.35), transparent 42%), radial-gradient(circle at 90% 80%, rgba(255,255,255,0.12), transparent 40%);"></div>
        <div class="relative">
            <x-site.brand-mark size="lg" variant="light" />
            <p class="mt-3 text-xs uppercase tracking-[0.2em] text-white/60">Secure access</p>
            <h1 class="mt-16 text-4xl font-bold leading-tight max-w-md">Protect every staff sign-in with a second factor.</h1>
            <p class="mt-4 text-sm text-white/70 max-w-sm">Scan once, confirm once. Your authenticator app will show a fresh 6-digit code every 30 seconds.</p>
        </div>
        <p class="relative text-xs text-white/50">© {{ date('Y') }} {{ brand('legal_name') }}</p>
    </aside>

    <main class="relative flex items-center justify-center px-4 py-10 sm:px-6 bg-[#F4F7F5]">
        <div class="absolute inset-0 opacity-60" style="background-image: linear-gradient(180deg, rgba(11,61,50,0.04), transparent 40%), radial-gradient(circle at 80% 10%, rgba(251,191,36,0.12), transparent 35%);"></div>
        <div class="relative w-full max-w-lg rounded-3xl bg-white/95 p-6 sm:p-8 shadow-[0_24px_80px_rgba(11,61,50,0.12)] ring-1 ring-[#0B3D32]/10">
            <div class="lg:hidden mb-6">
                <x-site.brand-mark size="md" />
            </div>

            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">One-time setup</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-gray-900">Set up two-factor authentication</h2>
            <p class="mt-2 text-sm text-gray-500">Scan the QR with your authenticator app, then enter a code below.</p>

            @php
                $recoveryLines = collect(array_values($recovery_codes))
                    ->map(fn ($code, $i) => ($i + 1).'. '.$code)
                    ->implode("\n");
                $recoveryClipboard = "Kopafasta recovery codes\n"
                    ."Use one if you lose your phone or authenticator device. Each code works once. Store offline.\n\n"
                    .$recoveryLines;
            @endphp

            <div class="mt-6 flex flex-col sm:flex-row gap-4 items-center">
                <div class="shrink-0 rounded-2xl bg-white ring-1 ring-brand/15 p-3 shadow-sm">
                    <img
                        src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&ecc=M&data={{ urlencode($provisioning_uri) }}"
                        width="180"
                        height="180"
                        alt="Two-factor QR code"
                        class="block rounded-xl"
                    >
                </div>
                <div class="min-w-0 flex-1 w-full" x-data="{ copied: false }">
                    <div class="flex items-center justify-between gap-2 mb-1.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Or enter this key</p>
                        <button type="button"
                                @click="navigator.clipboard.writeText(@js($secret)); copied = true; setTimeout(() => copied = false, 1600)"
                                class="inline-flex items-center gap-1 text-[11px] font-semibold text-brand hover:text-brand-light">
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <span x-text="copied ? 'Copied' : 'Copy'"></span>
                        </button>
                    </div>
                    <p class="font-mono text-sm break-all bg-brand-muted/40 rounded-xl px-3 py-2.5 ring-1 ring-brand/10 text-gray-900">{{ $secret }}</p>
                </div>
            </div>

            <div class="mt-5 rounded-2xl bg-amber-50 ring-1 ring-amber-200/80 px-4 py-3.5"
                 x-data="{
                    copied: false,
                    text: @js($recoveryClipboard),
                    async copyAll() {
                        await navigator.clipboard.writeText(this.text);
                        this.copied = true;
                        setTimeout(() => this.copied = false, 1800);
                    }
                 }">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-amber-950">Recovery codes</p>
                        <p class="text-[11px] text-amber-900/80 mt-0.5">Shown once. Use one if you lose your phone or authenticator — each code works once.</p>
                    </div>
                    <button type="button"
                            @click="copyAll()"
                            class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-[11px] font-semibold text-amber-950 ring-1 ring-amber-200 hover:bg-amber-100 transition">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <span x-text="copied ? 'Copied' : 'Copy all'"></span>
                    </button>
                </div>
                <ol class="mt-3 text-xs font-mono grid grid-cols-1 sm:grid-cols-2 gap-1.5 text-amber-950 list-none">
                    @foreach ($recovery_codes as $i => $code)
                        <li class="rounded-lg bg-white/70 px-2.5 py-1.5 ring-1 ring-amber-100 tracking-wide flex items-center gap-2">
                            <span class="text-[10px] font-sans font-semibold text-amber-700/70 tabular-nums w-4 shrink-0">{{ $i + 1 }}.</span>
                            <span>{{ $code }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>

            <form method="POST" action="{{ route('auth.two-factor.confirm-setup') }}" class="mt-6 space-y-5">
                @csrf
                <input type="hidden" name="context" value="{{ $context }}">
                <x-auth.otp-digits name="code" :length="6" :autofocus="true" label="Enter the 6-digit code from your app" />
                <button type="submit"
                        class="w-full bg-brand-gold hover:bg-yellow-400 text-brand font-bold rounded-xl py-3 shadow-sm transition">
                    Enable 2FA
                </button>
            </form>
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
                    title: 'Could not enable 2FA',
                    message: @js($errors->first()),
                },
            }));
        });
    </script>
@endif
@vite('resources/js/alpine-init.js')
</body>
</html>
