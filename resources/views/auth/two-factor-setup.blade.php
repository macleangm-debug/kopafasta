{{-- Set up two-factor with scannable QR. Expects: $secret, $provisioning_uri, $recovery_codes, $context --}}
@php
    $recoveryLines = collect(array_values($recovery_codes))
        ->map(fn ($code, $i) => ($i + 1).'. '.$code)
        ->implode("\n");
    $recoveryClipboard = "Kopafasta recovery codes\n"
        ."Use one if you lose your phone or authenticator device. Each code works once. Store offline.\n\n"
        .$recoveryLines;
@endphp
<x-site.console-auth-shell
    title="{{ brand_title('Set up two-factor') }}"
    aside-eyebrow="Secure access"
    aside-title="Protect every staff sign-in with a second factor."
    aside-body="Scan once, confirm once. Your authenticator app will show a fresh 6-digit code every 30 seconds."
    card-class="max-w-lg"
    error-title="Could not enable 2FA"
>
    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">One-time setup</p>
    <h2 class="mt-1 text-2xl font-bold tracking-tight text-gray-900">Set up two-factor authentication</h2>
    <p class="mt-2 text-sm text-gray-500">Scan the QR with your authenticator app, then enter a code below.</p>

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
                <p class="text-[11px] text-amber-900/80 mt-0.5">Shown once. Use one if you lose your phone — each code works once.</p>
            </div>
            <button type="button" @click="copyAll()"
                    class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-[11px] font-semibold text-amber-950 ring-1 ring-amber-200 hover:bg-amber-100 transition">
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
</x-site.console-auth-shell>
