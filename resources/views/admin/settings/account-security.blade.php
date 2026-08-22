@php
    $setupContext = $setupContext ?? 'admin';
@endphp

<x-admin.layout
    title="Account security"
    heading="Account security"
    subheading="Change your password and manage two-factor authentication"
>
    @if ($canManageSettings ?? false)
        @include('admin.settings._tabs', ['active' => 'account-security'])
    @endif

@if ($errors->any())
        <div class="mb-4 rounded-xl bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-800">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 mb-6 max-w-2xl">
        <h3 class="text-sm font-semibold text-gray-900">Password</h3>
        <p class="mt-1 text-sm text-gray-500">Change the password you use to sign in to the console.</p>
        <form method="POST" action="{{ route('admin.settings.account-security.password') }}" class="mt-4 space-y-4" autocomplete="off" data-no-draft>
            @csrf
            <x-admin.input name="current_password" label="Current password" type="password" required autocomplete="current-password" />
            <x-admin.input name="password" label="New password" type="password" required autocomplete="new-password" help="At least 6 characters." />
            <x-admin.input name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
            <button type="submit" class="inline-flex justify-center rounded-lg bg-brand hover:bg-brand-light text-white font-semibold text-sm px-4 py-2.5">
                Update password
            </button>
        </form>
    </div>

    @if ($twoFactorOn)
        @if (session('two_factor_just_enabled'))
            <div class="mb-6 bg-white rounded-xl shadow-sm ring-1 ring-emerald-200 p-6">
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Setup complete</p>
                <h3 class="mt-1 text-lg font-semibold text-gray-900">Your account is protected</h3>
                <p class="mt-2 text-sm text-gray-600 max-w-2xl">
                    Every future sign-in will ask for a 6-digit code from your authenticator app.
                    Keep the recovery codes you saved during setup somewhere offline — each one works only once.
                </p>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 mb-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Two-factor authentication</h3>
                    <p class="mt-1 text-sm text-gray-500 max-w-xl">
                        @if ($required)
                            Required for your portal. After your password, enter a fresh 6-digit code from your authenticator app.
                        @else
                            After your password, enter a fresh 6-digit code from your authenticator app.
                        @endif
                    </p>
                </div>
                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200">
                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                    Enabled
                </span>
            </div>
            @if ($confirmedAt)
                <p class="mt-3 text-xs text-gray-400">Confirmed {{ $confirmedAt->timezone(config('app.timezone'))->format('d M Y') }}</p>
            @endif

            <div class="mt-5 grid sm:grid-cols-2 gap-4">
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">At every sign-in</p>
                    <p class="mt-1.5 text-sm font-semibold text-gray-900">Password + 6-digit code</p>
                    <p class="mt-1 text-xs text-gray-500">Codes refresh about every 30 seconds in your authenticator app.</p>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Recovery codes</p>
                    <p class="mt-1.5 text-sm font-semibold text-gray-900">{{ $recoveryRemaining }} remaining</p>
                    <p class="mt-1 text-xs text-gray-500">Use one if you lose your phone. Each code works once.</p>
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
            <div class="mb-6 bg-white rounded-xl shadow-sm ring-1 ring-amber-200 p-6"
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
                        <h3 class="text-sm font-semibold text-gray-900">New recovery codes</h3>
                        <p class="mt-1 text-sm text-gray-500">Save these now — shown once. Previous codes no longer work.</p>
                    </div>
                    <button type="button"
                            @click="copyAll()"
                            class="inline-flex items-center rounded-lg bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold text-sm px-3 py-2">
                        <span x-text="copied ? 'Copied' : 'Copy all'"></span>
                    </button>
                </div>
                <ol class="mt-4 grid sm:grid-cols-2 gap-2 font-mono text-sm text-gray-900">
                    @foreach ($fresh as $i => $code)
                        <li class="rounded-lg bg-gray-50 ring-1 ring-gray-200 px-3 py-2">{{ $i + 1 }}. {{ $code }}</li>
                    @endforeach
                </ol>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6"
             x-data="{ confirming: {{ $errors->has('code') ? 'true' : 'false' }} }">
            <h3 class="text-sm font-semibold text-gray-900">Generate new recovery codes</h3>
            <p class="mt-1 text-sm text-gray-500 max-w-2xl">
                If you lost your saved codes, or used most of them, create a fresh set.
            </p>

            <div x-show="!confirming" class="mt-4">
                <button type="button"
                        @click="confirming = true"
                        class="inline-flex justify-center rounded-lg bg-brand hover:bg-brand-light text-white font-semibold text-sm px-4 py-2.5">
                    Generate new codes
                </button>
            </div>

            <form method="POST"
                  action="{{ route('admin.settings.account-security.regenerate') }}"
                  class="mt-4 max-w-md space-y-4"
                  data-no-draft
                  x-show="confirming"
                  x-cloak>
                @csrf
                <p class="text-sm text-gray-600">
                    Enter a current authenticator code to confirm it’s you.
                </p>
                <x-auth.otp-digits name="code" :length="6" :autofocus="false" label="Authenticator code" />
                <div class="flex flex-wrap gap-3">
                    <button type="submit"
                            class="inline-flex justify-center rounded-lg bg-brand hover:bg-brand-light text-white font-semibold text-sm px-4 py-2.5">
                        Confirm and generate
                    </button>
                    <button type="button"
                            @click="confirming = false"
                            class="inline-flex justify-center rounded-lg bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold text-sm px-4 py-2.5">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-amber-200 p-6 max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">
                {{ $required ? 'Required' : 'Recommended' }}
            </p>
            <h3 class="mt-1 text-lg font-semibold text-gray-900">Two-factor authentication is not set up</h3>
            <p class="mt-2 text-sm text-gray-600">
                @if ($required)
                    Your account must enrol an authenticator app before continuing to use the console.
                @else
                    Add an authenticator app so a stolen password alone cannot open your account.
                @endif
            </p>
            <a href="{{ route('auth.two-factor.setup', ['context' => $setupContext]) }}"
               class="inline-flex mt-5 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold text-sm px-4 py-2.5 rounded-lg">
                Set up authenticator app
            </a>
        </div>
    @endif
</x-admin.layout>
