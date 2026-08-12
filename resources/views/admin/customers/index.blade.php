<x-admin.layout title="Customers" heading="" subheading="">

    @php
        $counts = [
            'total' => \App\Models\Customer::query()->count(),
            'active' => \App\Models\Customer::query()->where('status', 'active')->count(),
            'with_loans' => \App\Models\Customer::query()
                ->whereHas('loans', fn ($q) => $q->whereIn('status', ['active', 'disbursed', 'arrears']))
                ->count(),
            'new_month' => \App\Models\Customer::query()->where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    @endphp

    <section class="mb-6">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-6 text-white">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Borrower registry</p>
                <h1 class="text-2xl sm:text-3xl font-bold mt-1">Customers</h1>
                <p class="text-sm text-white/75 mt-2 max-w-2xl">
                    Open a customer for the full loan-officer dossier — profile, documents, and applications.
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
                    <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">New this month</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['new_month']) }}</p>
                </div>
            </div>
        </div>
    </section>

    <x-admin.index-toolbar route="admin.customers" label="New customer" />
    @livewire('admin.customers-table')
</x-admin.layout>
