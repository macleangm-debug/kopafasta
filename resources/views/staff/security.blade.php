<x-staff.layout title="Security">
    <div class="relative -mx-4 -mt-8 mb-8 px-4 pt-8 pb-10 sm:rounded-b-3xl overflow-hidden"
         style="background-color: #0B3D32; color: #fff;">
        <div class="absolute inset-0 opacity-40 pointer-events-none"
             style="background-image: radial-gradient(circle at 18% 20%, rgba(251,191,36,0.35), transparent 42%), radial-gradient(circle at 92% 75%, rgba(255,255,255,0.12), transparent 40%);"></div>
        <div class="relative max-w-3xl">
            <p class="text-[10px] uppercase tracking-[0.22em] text-white/55 font-semibold">{{ brand_name() }} · Staff</p>
            <h1 class="mt-3 text-3xl sm:text-4xl font-bold tracking-tight">Account security</h1>
            <p class="mt-3 text-sm text-white/70 max-w-xl">
                Authenticator protection for every staff sign-in — the same calm, branded security used at login.
            </p>
        </div>
    </div>

    <div class="relative -mt-2">
        <div class="absolute inset-0 -mx-4 -z-10 opacity-70 pointer-events-none"
             style="background-image: linear-gradient(180deg, rgba(11,61,50,0.06), transparent 45%), radial-gradient(circle at 85% 0%, rgba(251,191,36,0.10), transparent 40%);"></div>

        @if (session('status'))
            <div class="mb-4 rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-2xl bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($twoFactorOn)
            @if (session('two_factor_just_enabled'))
                <div class="mb-6 rounded-3xl bg-white/95 p-6 sm:p-8 shadow-[0_24px_80px_rgba(11,61,50,0.10)] ring-1 ring-[#0B3D32]/10">
                    <p class="text-[10px] uppercase tracking-[0.2em] font-semibold" style="color: #0B3D32;">Setup complete</p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-gray-900">Your account is protected</h2>
                    <p class="mt-2 text-sm text-gray-600 max-w-2xl">
                        Every future staff sign-in will ask for a 6-digit code from your authenticator app.
                        Keep the recovery codes you saved during setup somewhere offline — each one works only once.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route('staff.dashboard') }}"
                           class="inline-flex items-center rounded-xl bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold text-sm px-4 py-2.5">
                            Back to dashboard
                        </a>
                        @if ($hasConsole ?? false)
                            <a href="{{ route('admin.dashboard') }}"
                               class="inline-flex items-center rounded-xl bg-white ring-1 ring-gray-200 hover:ring-[#0B3D32]/30 text-sm font-semibold px-4 py-2.5 text-gray-800">
                                Open admin console
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            <div class="rounded-3xl bg-white/95 p-6 sm:p-8 shadow-[0_24px_80px_rgba(11,61,50,0.10)] ring-1 ring-[#0B3D32]/10 mb-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.2em] text-gray-500 font-semibold">Two-factor authentication</p>
                        <h2 class="mt-2 text-2xl font-bold tracking-tight text-gray-900">Protected with authenticator</h2>
                        <p class="mt-2 text-sm text-gray-600 max-w-xl">
                            @if ($required)
                                Required for all staff accounts. After your password, you’ll enter a fresh 6-digit code from your app.
                            @else
                                After your password, you’ll enter a fresh 6-digit code from your authenticator app.
                            @endif
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-semibold bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200">
                        <span class="size-2 rounded-full bg-emerald-500"></span>
                        Enabled
                    </span>
                </div>
                @if ($confirmedAt)
                    <p class="mt-4 text-xs text-gray-400">Confirmed {{ $confirmedAt->timezone(config('app.timezone'))->format('d M Y') }}</p>
                @endif

                <div class="mt-6 grid sm:grid-cols-2 gap-4">
                    <div class="rounded-2xl bg-[#F4F7F5] ring-1 ring-[#0B3D32]/10 p-5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">At every sign-in</p>
                        <p class="mt-2 font-semibold text-gray-900">Password + 6-digit code</p>
                        <p class="mt-1.5 text-sm text-gray-600">Open your authenticator app and enter the current code. Codes refresh about every 30 seconds.</p>
                    </div>
                    <div class="rounded-2xl bg-[#F4F7F5] ring-1 ring-[#0B3D32]/10 p-5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Recovery codes</p>
                        <p class="mt-2 font-semibold text-gray-900">{{ $recoveryRemaining }} remaining</p>
                        <p class="mt-1.5 text-sm text-gray-600">Use one if you lose your phone. Each code works once. Generate a new set below if you’ve lost them.</p>
                    </div>
                </div>
            </div>

            @if (session('fresh_recovery_codes'))
                @php
                    $fresh = array_values(session('fresh_recovery_codes'));
                    $recoveryLines = collect($fresh)->map(fn ($code, $i) => ($i + 1).'. '.$code)->implode("\n");
                    $recoveryClipboard = "Kopafasta recovery codes\n"
                        ."Use one if you lose your phone or authenticator device. Each code works once. Store offline.\n\n"
                        .$recoveryLines;
                @endphp
                <div class="mb-6 rounded-3xl bg-white/95 p-6 sm:p-8 shadow-[0_24px_80px_rgba(11,61,50,0.10)] ring-1 ring-amber-200"
                     x-data="{
                        copied: false,
                        text: @js($recoveryClipboard),
                        async copyAll() {
                            await navigator.clipboard.writeText(this.text);
                            this.copied = true;
                            setTimeout(() => this.copied = false, 1600);
                        }
                     }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] uppercase tracking-[0.2em] text-amber-700 font-semibold">New recovery codes</p>
                            <h2 class="mt-2 text-xl font-bold tracking-tight text-gray-900">Save these now — shown once</h2>
                            <p class="mt-1 text-sm text-gray-500">Previous codes no longer work.</p>
                        </div>
                        <button type="button"
                                @click="copyAll()"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold text-sm px-4 py-2.5">
                            <span x-text="copied ? 'Copied' : 'Copy all'"></span>
                        </button>
                    </div>
                    <ol class="mt-5 grid sm:grid-cols-2 gap-2 font-mono text-sm text-gray-900">
                        @foreach ($fresh as $i => $code)
                            <li class="rounded-xl bg-[#F4F7F5] ring-1 ring-[#0B3D32]/10 px-3 py-2.5">{{ $i + 1 }}. {{ $code }}</li>
                        @endforeach
                    </ol>
                </div>
            @endif

            <div class="rounded-3xl bg-white/95 p-6 sm:p-8 shadow-[0_24px_80px_rgba(11,61,50,0.10)] ring-1 ring-[#0B3D32]/10">
                <p class="text-[10px] uppercase tracking-[0.2em] text-gray-500 font-semibold">Backup access</p>
                <h2 class="mt-2 text-xl font-bold tracking-tight text-gray-900">Generate new recovery codes</h2>
                <p class="mt-2 text-sm text-gray-600 max-w-2xl">
                    If you lost your saved codes, or used most of them, create a fresh set.
                    Enter a current authenticator code to confirm it’s you.
                </p>
                <form method="POST" action="{{ route('staff.security.regenerate-recovery') }}" class="mt-5 flex flex-col sm:flex-row gap-3 sm:items-end max-w-md">
                    @csrf
                    <div class="flex-1">
                        <label for="regen_code" class="block text-xs font-semibold uppercase tracking-wide text-gray-600 mb-1.5">Authenticator code</label>
                        <input id="regen_code"
                               name="code"
                               type="text"
                               inputmode="numeric"
                               autocomplete="one-time-code"
                               required
                               class="w-full rounded-xl border-0 ring-1 ring-gray-200 focus:ring-2 focus:ring-[#0B3D32] px-3.5 py-2.5 text-sm bg-white"
                               placeholder="6-digit code">
                    </div>
                    <button type="submit"
                            class="inline-flex justify-center rounded-xl font-semibold text-sm px-4 py-2.5 text-white"
                            style="background-color: #0B3D32;">
                        Generate new codes
                    </button>
                </form>
            </div>
        @else
            <div class="rounded-3xl bg-white/95 p-6 sm:p-8 shadow-[0_24px_80px_rgba(11,61,50,0.10)] ring-1 ring-amber-200 max-w-2xl">
                <p class="text-[10px] uppercase tracking-[0.2em] text-amber-700 font-semibold">
                    {{ $required ? 'Required' : 'Recommended' }}
                </p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight text-gray-900">Set up two-factor authentication</h2>
                <p class="mt-2 text-sm text-gray-600">
                    @if ($required)
                        Staff accounts must enrol an authenticator app before using the workspace.
                    @else
                        Add an authenticator app so a stolen password alone cannot open your account.
                    @endif
                </p>
                <a href="{{ route('auth.two-factor.setup', ['context' => 'staff']) }}"
                   class="inline-flex mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold text-sm px-4 py-2.5 rounded-xl">
                    Set up authenticator app
                </a>
            </div>
        @endif
    </div>
</x-staff.layout>
