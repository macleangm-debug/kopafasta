<x-site.layout :title="brand_title('Create your PIN')">
    <section class="min-h-[calc(100vh-4rem)] flex items-center justify-center px-4 py-12 premium-gradient">
        <div class="w-full max-w-md glass-card p-8 sm:p-10">
            <a href="{{ route('site.home') }}" class="mb-8 inline-block">
                <x-site.brand-mark size="md" />
            </a>

            <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-2">{{ __('site.auth.partner_portal') }}</p>
            <h1 class="text-3xl font-bold tracking-tight text-gray-900">Create your PIN</h1>
            <p class="mt-2 text-sm text-gray-600">Use a 4-digit PIN to sign in quickly with your phone number.</p>

            @if (session('status'))
                <div class="mt-6 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-sm text-emerald-700">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('site.partner.setup-pin.post') }}" class="mt-6 space-y-5" autocomplete="off">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">4-digit PIN</label>
                    <input type="password" name="pin" inputmode="numeric" maxlength="4" pattern="\d{4}" required
                           autocomplete="new-password" placeholder="••••"
                           class="w-full px-3 py-3 rounded-xl bg-white border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-center text-lg tracking-[0.5em] font-mono outline-none">
                    @error('pin')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm PIN</label>
                    <input type="password" name="pin_confirmation" inputmode="numeric" maxlength="4" pattern="\d{4}" required
                           autocomplete="new-password" placeholder="••••"
                           class="w-full px-3 py-3 rounded-xl bg-white border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-center text-lg tracking-[0.5em] font-mono outline-none">
                </div>
                <p class="text-xs text-gray-500">Do not share your PIN. After 5 failed attempts your account locks for 15 minutes.</p>
                <button type="submit" class="w-full bg-brand hover:bg-brand-light text-white font-bold py-3.5 rounded-xl transition shadow-md">
                    Save PIN &amp; continue
                </button>
            </form>
        </div>
    </section>
    <x-site.celebration-confetti />
</x-site.layout>
