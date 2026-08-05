{{-- Public auth shell (not inside borrower dashboard) --}}
<x-site.layout :title="brand_title('Create your PIN')">
    <section class="min-h-[calc(100dvh-4rem)] md:min-h-[calc(100dvh-6.5rem)] grid lg:grid-cols-2 premium-gradient">
        <aside class="hidden lg:flex relative overflow-hidden bg-brand text-white p-12 flex-col justify-between">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_bottom_left,_#f5c842,_transparent_50%)]"></div>
            <a href="{{ route('site.home') }}" class="relative"><x-site.brand-mark variant="light" /></a>
            <div class="relative">
                <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">Almost there</p>
                <h2 class="mt-2 text-4xl font-bold tracking-tight leading-tight">Create a 4-digit PIN</h2>
                <p class="mt-4 text-white/70 max-w-md">Use it to sign in quickly with your phone number. After this, your dashboard opens — pay the membership fee when you are ready for loans.</p>
            </div>
            <p class="relative text-xs text-white/50">© {{ date('Y') }} {{ brand_name() }}</p>
        </aside>

        <div class="flex items-center justify-center px-4 py-10 sm:px-12">
            <div class="w-full max-w-md">
                <a href="{{ route('site.home') }}" class="lg:hidden inline-block mb-6"><x-site.brand-mark size="md" /></a>
                <div class="glass-card p-8 sm:p-10">
                    @if (session('status'))
                        <div class="mb-5 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
                    @endif
                    <h1 class="text-2xl font-bold text-gray-900">Create your PIN</h1>
                    <p class="mt-1 text-sm text-gray-600">Use a 4-digit PIN to sign in quickly with your phone number.</p>

                    <form method="POST" action="{{ route('site.borrower.setup-pin.post') }}" class="mt-6 space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">4-digit PIN</label>
                            <input type="password" name="pin" inputmode="numeric" maxlength="4" pattern="\d{4}" required autocomplete="new-password"
                                   class="w-full rounded-xl border border-gray-200 px-3.5 py-3 text-center text-lg tracking-[0.5em] font-mono outline-none focus:border-brand focus:ring-2 focus:ring-brand/10">
                            @error('pin')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm PIN</label>
                            <input type="password" name="pin_confirmation" inputmode="numeric" maxlength="4" pattern="\d{4}" required autocomplete="new-password"
                                   class="w-full rounded-xl border border-gray-200 px-3.5 py-3 text-center text-lg tracking-[0.5em] font-mono outline-none focus:border-brand focus:ring-2 focus:ring-brand/10">
                        </div>
                        <p class="text-xs text-gray-500">Do not share your PIN. After 5 failed attempts your account locks for 15 minutes.</p>
                        <button class="w-full bg-brand-gold hover:bg-yellow-400 text-brand font-bold py-3.5 rounded-xl text-sm shadow-sm">Save PIN &amp; continue</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-site.layout>
