<x-site.investor-layout title="Support — Investor" active="support">
    <h1 class="text-2xl lg:text-3xl font-bold tracking-tight mb-1">Investor support</h1>
    <p class="text-slate-500 text-sm mb-6">Your dedicated relationship team is one tap away.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6">
            <div class="size-12 grid place-items-center rounded-xl bg-emerald-100 text-emerald-700 font-bold">AM</div>
            <h2 class="font-bold mt-3">Your account manager</h2>
            <p class="text-sm text-slate-500 mt-1">Personal advisor for large capital partners.</p>
            <a href="tel:+255700000000" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2 text-sm">Call advisor</a>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6">
            <div class="size-12 grid place-items-center rounded-xl bg-sky-100 text-sky-700 font-bold">@</div>
            <h2 class="font-bold mt-3">Email investor desk</h2>
            <p class="text-sm text-slate-500 mt-1">investors@kopafasta.com — replies within 1 business day.</p>
            <a href="mailto:investors@kopafasta.com" class="mt-3 inline-flex items-center gap-2 rounded-lg border border-slate-300 hover:bg-slate-50 font-semibold px-4 py-2 text-sm">Send email</a>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6">
            <div class="size-12 grid place-items-center rounded-xl bg-amber-100 text-amber-700 font-bold">?</div>
            <h2 class="font-bold mt-3">Investment inquiries</h2>
            <p class="text-sm text-slate-500 mt-1">Questions about pools, terms or pricing.</p>
            <a href="{{ route('site.faq') }}" class="mt-3 inline-flex items-center gap-2 rounded-lg border border-slate-300 hover:bg-slate-50 font-semibold px-4 py-2 text-sm">Browse FAQ</a>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6">
            <div class="size-12 grid place-items-center rounded-xl bg-slate-100 text-slate-700 font-bold">T</div>
            <h2 class="font-bold mt-3">Open a support ticket</h2>
            <p class="text-sm text-slate-500 mt-1">Track issues with our support team.</p>
            <a href="mailto:support@kopafasta.com" class="mt-3 inline-flex items-center gap-2 rounded-lg border border-slate-300 hover:bg-slate-50 font-semibold px-4 py-2 text-sm">New ticket</a>
        </div>
    </div>
</x-site.investor-layout>
