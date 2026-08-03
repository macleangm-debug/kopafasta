<x-staff.layout title="Security">
    <h1 class="text-2xl font-bold mb-1">Security</h1>
    <p class="text-sm text-gray-600 mb-6">Authenticator protection for your staff sign-in.</p>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-800">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($twoFactorOn)
        @if (session('two_factor_just_enabled'))
            <div class="mb-6 rounded-xl bg-white ring-1 ring-emerald-200 p-5 sm:p-6">
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Setup complete</p>
                <h2 class="mt-1 text-lg font-semibold text-gray-900">Your account is protected</h2>
                <p class="mt-2 text-sm text-gray-600 max-w-2xl">
                    Every future staff sign-in will ask for a 6-digit code from your authenticator app.
                    Keep the recovery codes you saved during setup somewhere offline — each one works only once.
                </p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('staff.dashboard') }}"
                       class="inline-flex items-center rounded-lg bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold text-sm px-4 py-2">
                        Back to dashboard
                    </a>
                    @if ($hasConsole ?? false)
                        <a href="{{ route('admin.dashboard') }}"
                           class="inline-flex items-center rounded-lg bg-white ring-1 ring-gray-200 hover:ring-amber-300 text-sm font-semibold px-4 py-2">
                            Open admin console
                        </a>
                    @endif
                </div>
            </div>
        @endif

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="rounded-xl bg-white ring-1 ring-gray-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Status</p>
                <p class="mt-2 text-lg font-semibold text-emerald-700">Enabled</p>
                <p class="mt-1 text-sm text-gray-500">
                    @if ($required)
                        Required for all staff accounts.
                    @else
                        Active on this account.
                    @endif
                </p>
                @if ($confirmedAt)
                    <p class="mt-3 text-xs text-gray-400">Confirmed {{ $confirmedAt->timezone(config('app.timezone'))->format('d M Y') }}</p>
                @endif
            </div>

            <div class="rounded-xl bg-white ring-1 ring-gray-200 p-5">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">At sign-in</p>
                <p class="mt-2 font-semibold text-gray-900">Authenticator code</p>
                <p class="mt-1 text-sm text-gray-500">
                    After your password, enter the current 6-digit code from your authenticator app.
                </p>
            </div>

            <div class="rounded-xl bg-white ring-1 ring-gray-200 p-5 sm:col-span-2 lg:col-span-1">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Recovery codes</p>
                <p class="mt-2 font-semibold text-gray-900">
                    {{ $recoveryRemaining }} remaining
                </p>
                <p class="mt-1 text-sm text-gray-500">
                    Use one if you lose your phone. Each code is single-use.
                </p>
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
            <div class="mb-6 rounded-xl bg-white ring-1 ring-amber-200 p-5 sm:p-6"
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
                        <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">New recovery codes</p>
                        <h2 class="mt-1 text-lg font-semibold text-gray-900">Save these now — shown once</h2>
                        <p class="mt-1 text-sm text-gray-500">Previous codes no longer work.</p>
                    </div>
                    <button type="button"
                            @click="copyAll()"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold text-sm px-3 py-2">
                        <span x-text="copied ? 'Copied' : 'Copy all'"></span>
                    </button>
                </div>
                <ol class="mt-4 grid sm:grid-cols-2 gap-2 font-mono text-sm text-gray-900">
                    @foreach ($fresh as $i => $code)
                        <li class="rounded-lg bg-slate-50 ring-1 ring-gray-200 px-3 py-2">{{ $i + 1 }}. {{ $code }}</li>
                    @endforeach
                </ol>
            </div>
        @endif

        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-5 sm:p-6">
            <h2 class="font-semibold text-gray-900">Generate new recovery codes</h2>
            <p class="mt-1 text-sm text-gray-500 max-w-2xl">
                If you lost your saved codes, or used most of them, create a fresh set.
                Enter a current authenticator code to confirm it’s you.
            </p>
            <form method="POST" action="{{ route('staff.security.regenerate-recovery') }}" class="mt-4 flex flex-col sm:flex-row gap-3 sm:items-end max-w-md">
                @csrf
                <div class="flex-1">
                    <label for="regen_code" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1.5">Authenticator code</label>
                    <input id="regen_code"
                           name="code"
                           type="text"
                           inputmode="numeric"
                           autocomplete="one-time-code"
                           required
                           class="w-full rounded-lg border-0 ring-1 ring-gray-300 focus:ring-2 focus:ring-amber-500 px-3 py-2 text-sm"
                           placeholder="6-digit code">
                </div>
                <button type="submit"
                        class="inline-flex justify-center rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm px-4 py-2.5">
                    Generate new codes
                </button>
            </form>
        </div>
    @else
        <div class="rounded-xl bg-white ring-1 ring-amber-200 p-5 sm:p-6 max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">
                {{ $required ? 'Required' : 'Recommended' }}
            </p>
            <h2 class="mt-1 text-lg font-semibold text-gray-900">Two-factor authentication is not set up</h2>
            <p class="mt-2 text-sm text-gray-600">
                @if ($required)
                    Staff accounts must enrol an authenticator app before using the workspace.
                @else
                    Add an authenticator app so a stolen password alone cannot open your account.
                @endif
            </p>
            <a href="{{ route('auth.two-factor.setup', ['context' => 'staff']) }}"
               class="inline-flex mt-5 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold text-sm px-4 py-2.5 rounded-lg">
                Set up authenticator app
            </a>
        </div>
    @endif
</x-staff.layout>
