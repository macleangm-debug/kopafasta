<x-staff.layout title="Security">
    <h1 class="text-2xl font-bold mb-6">Security</h1>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="rounded-xl bg-white ring-1 ring-gray-200 p-6 max-w-xl">
        <h2 class="text-sm font-semibold mb-2">Two-factor authentication</h2>
        <p class="text-sm text-gray-600 mb-4">
            Status:
            <span class="font-semibold {{ $twoFactorOn ? 'text-emerald-700' : 'text-amber-700' }}">
                {{ $twoFactorOn ? 'Enabled' : ($required ? 'Required — not set up' : 'Not enabled') }}
            </span>
        </p>

        @unless ($twoFactorOn)
            <a href="{{ route('auth.two-factor.setup', ['context' => 'staff']) }}"
               class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold text-sm px-4 py-2 rounded-lg">
                Set up authenticator app
            </a>
        @endunless
    </div>
</x-staff.layout>
