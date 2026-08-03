<x-staff.layout title="Dashboard">
    <h1 class="text-2xl font-bold mb-1">Welcome, {{ $user->name }}</h1>
    <p class="text-sm text-gray-600 mb-6">{{ $roleLabel }} workspace — open the tools you are permitted to use.</p>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('warning'))
        <div class="mb-4 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">{{ session('warning') }}</div>
    @endif

        @if ($twoFactorRequired && ! $twoFactorOn)
        <div class="mb-6 rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4 text-sm text-amber-900">
            Two-factor authentication is required for staff accounts.
            <a href="{{ route('admin.settings.account-security') }}" class="font-semibold underline ml-1">Set up 2FA</a>
        </div>
    @endif

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($shortcuts as $item)
            <a href="{{ route($item['route']) }}" class="block rounded-xl bg-white ring-1 ring-gray-200 p-5 hover:ring-amber-300 transition">
                <p class="font-semibold">{{ $item['label'] }}</p>
                <p class="text-sm text-gray-500 mt-1">{{ $item['description'] }}</p>
            </a>
        @empty
            <div class="sm:col-span-2 lg:col-span-3 rounded-xl bg-white ring-1 ring-gray-200 p-8 text-center text-gray-500">
                No workspace shortcuts are configured for your permissions. Contact an administrator.
            </div>
        @endforelse
    </div>
</x-staff.layout>
