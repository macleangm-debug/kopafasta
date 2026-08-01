<x-site.vendor-layout title="Support" active="support">
    <h1 class="text-2xl font-extrabold mb-1">Support</h1>
    <p class="text-sm text-gray-500 mb-5">Need help with a task, payment or document? Reach us here.</p>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="tel:+255700000000" class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 hover:shadow-sm">
            <div class="size-10 rounded-full bg-indigo-100 text-brand grid place-items-center mb-3"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.86 19.86 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.86 19.86 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 13 13 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 13 13 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
            <p class="font-bold">Call us</p>
            <p class="text-xs text-gray-500 mt-1">+255 700 000 000</p>
        </a>
        <a href="https://wa.me/255700000000" class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 hover:shadow-sm">
            <div class="size-10 rounded-full bg-emerald-100 text-emerald-700 grid place-items-center mb-3"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20 4a10 10 0 0 0-15 13l-1 4 4-1a10 10 0 0 0 12-16zM12 20a8 8 0 0 1-4-1l-3 1 1-3a8 8 0 1 1 6 3zm4-6c0-1-2-2-3-2s-1 1-1 1-2 0-3-2-1-3-1-3 1 0 1-1-1-3-2-3-2 2-2 2c0 4 6 9 9 9 0 0 2-1 2-1z"/></svg></div>
            <p class="font-bold">WhatsApp</p>
            <p class="text-xs text-gray-500 mt-1">Chat with the operations team</p>
        </a>
        <a href="mailto:vendors@kopafasta.test" class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 hover:shadow-sm">
            <div class="size-10 rounded-full bg-sky-100 text-sky-700 grid place-items-center mb-3"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/><path d="m22 6-10 7L2 6"/></svg></div>
            <p class="font-bold">Email</p>
            <p class="text-xs text-gray-500 mt-1">vendors@kopafasta.test</p>
        </a>
        <a href="{{ route('site.faq') }}" class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 hover:shadow-sm">
            <div class="size-10 rounded-full bg-amber-100 text-amber-700 grid place-items-center mb-3"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 1 1 4.4 3.4c-1 .6-1.5 1.2-1.5 2.6M12 18v.01"/></svg></div>
            <p class="font-bold">FAQ</p>
            <p class="text-xs text-gray-500 mt-1">Browse frequent answers</p>
        </a>
    </div>
</x-site.vendor-layout>
