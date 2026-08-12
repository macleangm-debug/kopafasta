<x-admin.layout title="Customers" heading="" subheading="">

    <section class="mb-8">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-7 text-white">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Customer desk</p>
                <h1 class="text-2xl sm:text-3xl font-bold mt-1">Customers</h1>
                <p class="text-sm text-white/75 mt-2 max-w-2xl">
                    Profiles, KYC, face verification, and guarantors — separate from lending queues.
                </p>
            </div>
            <div class="bg-white px-6 py-5 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/10 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Total</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['total']) }}</p>
                </div>
                <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">Active</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['active']) }}</p>
                </div>
                <div class="rounded-xl bg-sky-50 ring-1 ring-sky-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-sky-800 font-semibold">With loans</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['with_loans']) }}</p>
                </div>
                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Suspended</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['suspended']) }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 sm:p-6 flex flex-wrap gap-3">
        <a href="{{ route('admin.customers.index') }}"
           class="inline-flex items-center gap-2 bg-brand-gold hover:brightness-95 text-brand font-bold text-sm px-5 py-2.5 rounded-xl shadow-sm ring-1 ring-brand/15">
            All customers
        </a>
        <a href="{{ route('admin.face-verifications.index') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 hover:text-gray-900 px-4 py-2.5 rounded-xl ring-1 ring-gray-200 bg-gray-50">
            Face verifications
        </a>
        <a href="{{ route('admin.guarantors.index') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 hover:text-gray-900 px-4 py-2.5 rounded-xl ring-1 ring-gray-200 bg-gray-50">
            Guarantors
        </a>
        <a href="{{ route('admin.customer-kycs.index') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-gray-900 px-4 py-2.5">
            KYC records →
        </a>
    </div>

</x-admin.layout>
