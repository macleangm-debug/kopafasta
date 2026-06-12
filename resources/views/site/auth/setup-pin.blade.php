<x-site.layout :title="brand_title('Set up PIN')">
    <div class="min-h-screen flex items-center justify-center px-4 py-12 bg-gray-50">
        <div class="w-full max-w-md">
            <div class="mb-6 text-center">
                <a href="{{ route('site.home') }}" class="inline-flex items-center gap-2 font-bold text-gray-900">
                    <span class="size-9 grid place-items-center rounded-lg bg-amber-500 text-gray-900 font-extrabold">K</span>
                    Kopafasta
                </a>
            </div>

            <h1 class="text-2xl font-bold mb-1 text-center">Create your PIN</h1>
            <p class="text-sm text-gray-500 mb-6 text-center">Use a 4-digit PIN to sign in quickly with your phone number.</p>

            @if (session('status'))
                <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('site.borrower.setup-pin.post') }}" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">4-digit PIN</label>
                    <input type="password" name="pin" inputmode="numeric" maxlength="4" pattern="\d{4}" required autocomplete="new-password"
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-3 text-center text-lg tracking-[0.5em] font-mono">
                    @error('pin')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Confirm PIN</label>
                    <input type="password" name="pin_confirmation" inputmode="numeric" maxlength="4" pattern="\d{4}" required autocomplete="new-password"
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-3 text-center text-lg tracking-[0.5em] font-mono">
                </div>
                <p class="text-xs text-gray-500">Do not share your PIN. After 5 failed attempts your account locks for 15 minutes.</p>
                <button class="w-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold py-2.5 rounded-full text-sm">Save PIN & continue</button>
            </form>
        </div>
    </div>
</x-site.layout>
