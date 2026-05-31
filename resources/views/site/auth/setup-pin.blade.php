<x-site.borrower-layout title="Set up PIN — Kopafasta" active="profile">
    <div class="max-w-md mx-auto">
        <h1 class="text-2xl font-bold mb-1">Create your PIN</h1>
        <p class="text-sm text-gray-500 mb-6">Use a 4-digit PIN to sign in quickly with your phone number.</p>

        <form method="POST" action="{{ route('site.borrower.setup-pin.post') }}" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">4-digit PIN</label>
                <input type="password" name="pin" inputmode="numeric" maxlength="4" pattern="\d{4}" required autocomplete="new-password"
                       class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-3 text-center text-lg tracking-[0.5em] font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Confirm PIN</label>
                <input type="password" name="pin_confirmation" inputmode="numeric" maxlength="4" pattern="\d{4}" required
                       class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-3 text-center text-lg tracking-[0.5em] font-mono">
            </div>
            <p class="text-xs text-gray-500">Do not share your PIN. After 5 failed attempts your account locks for 15 minutes.</p>
            <button class="w-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold py-2.5 rounded-full text-sm">Save PIN & continue</button>
        </form>
    </div>
</x-site.borrower-layout>
