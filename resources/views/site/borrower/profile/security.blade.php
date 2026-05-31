<x-site.borrower-layout title="Profile — Security — Kopafasta" active="profile">

    <div class="max-w-3xl">
        <h1 class="text-2xl font-bold mb-1">Profile</h1>
        <p class="text-sm text-gray-500 mb-6">Manage your PIN and trusted devices.</p>

        @include('site.borrower.profile._tabs', ['active' => 'security'])

        <div class="grid gap-6">
            <section class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="font-semibold mb-1">{{ auth()->user()->pin_set_at ? 'Change PIN' : 'Set PIN' }}</h2>
                <p class="text-sm text-gray-500 mb-4">Your 4-digit PIN is used for phone sign-in.</p>

                <form method="POST" action="{{ route('site.borrower.profile.pin.update') }}" class="grid sm:grid-cols-2 gap-4 max-w-lg">
                    @csrf @method('PUT')
                    @if (auth()->user()->pin_set_at)
                        <div class="sm:col-span-2">
                            <label class="block text-xs text-gray-600 mb-1">Current PIN</label>
                            <input type="password" name="current_pin" inputmode="numeric" maxlength="4" pattern="\d{4}" required
                                   class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm font-mono">
                        </div>
                    @endif
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">New PIN</label>
                        <input type="password" name="pin" inputmode="numeric" maxlength="4" pattern="\d{4}" required
                               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">Confirm PIN</label>
                        <input type="password" name="pin_confirmation" inputmode="numeric" maxlength="4" pattern="\d{4}" required
                               class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm font-mono">
                    </div>
                    <div class="sm:col-span-2">
                        <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">Update PIN</button>
                    </div>
                </form>
            </section>

            <section class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="font-semibold mb-1">Trusted devices</h2>
                <p class="text-sm text-gray-500 mb-4">Devices you marked as trusted during sign-in (30 days).</p>

                @if ($trustedDevices->isEmpty())
                    <p class="text-sm text-gray-500">No trusted devices yet. Check “Trust this device” when signing in.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($trustedDevices as $device)
                            <li class="py-3 flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $device->name ?? 'Web browser' }}</p>
                                    <p class="text-xs text-gray-500">
                                        Last used {{ optional($device->last_used_at)->diffForHumans() ?? '—' }}
                                        · Expires {{ optional($device->expires_at)->format('d M Y') }}
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('site.borrower.profile.devices.revoke', $device) }}">
                                    @csrf @method('DELETE')
                                    <button class="text-xs font-semibold text-red-600 hover:underline">Remove</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @unless (config('auth_portal.biometric_enabled'))
                <section class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3 text-sm text-gray-600">
                    Biometric authentication (Face ID / fingerprint) will be available in a future update.
                </section>
            @endunless
        </div>
    </div>

</x-site.borrower-layout>
