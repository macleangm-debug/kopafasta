<x-site.borrower-layout :title="brand_title('Set up PIN')" active="profile" content-width="narrow">
    <div class="max-w-md mx-auto py-6">
        <h1 class="text-2xl font-bold mb-1">Create your PIN</h1>
        <p class="text-sm text-gray-500 mb-6">Use a 4-digit PIN to sign in quickly with your phone number.</p>

        <form method="POST" action="{{ route('site.borrower.setup-pin.post') }}" class="glass-card p-6 space-y-4">
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
            <button class="w-full bg-brand-gold hover:bg-yellow-400 text-brand font-bold py-2.5 rounded-xl text-sm">Save PIN & continue</button>
        </form>
    </div>
</x-site.borrower-layout>
