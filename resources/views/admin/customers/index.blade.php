<x-admin.layout title="Members" heading="" subheading="">

    @php
        $counts = [
            'total' => \App\Models\Customer::query()->where('status', '!=', 'pending')->count(),
            'pending' => \App\Models\Customer::query()->where('status', 'pending')->count(),
            'active' => \App\Models\Customer::query()->where('status', 'active')->count(),
            'with_loans' => \App\Models\Customer::query()
                ->where('status', '!=', 'pending')
                ->whereHas('loans', fn ($q) => $q->whereIn('status', ['active', 'disbursed', 'arrears']))
                ->count(),
            'new_month' => \App\Models\Customer::query()
                ->where('status', '!=', 'pending')
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
        ];
        $statusFilter = request('status', '');
    @endphp

    <section class="mb-6">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-6 text-white">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Borrower registry</p>
                <h1 class="text-2xl sm:text-3xl font-bold mt-1">Members</h1>
                <p class="text-sm text-white/75 mt-2 max-w-2xl">
                    Open a customer for the full loan-officer dossier — profile, documents, and applications.
                    Incomplete registrations stay pending until they resume — they are not Members yet.
                </p>
            </div>
            <div class="bg-white px-6 py-5 grid sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <a href="{{ route('admin.customers.index') }}"
                   class="rounded-xl px-4 py-4 ring-1 transition {{ $statusFilter === '' ? 'bg-brand text-white ring-brand' : 'bg-brand-muted/50 ring-brand/10 hover:ring-brand/30' }}">
                    <p class="text-[10px] uppercase tracking-widest font-semibold {{ $statusFilter === '' ? 'text-brand-gold' : 'text-brand' }}">Members</p>
                    <p class="text-3xl font-bold mt-2 tabular-nums {{ $statusFilter === '' ? 'text-white' : 'text-gray-900' }}">{{ number_format($counts['total']) }}</p>
                </a>
                <a href="{{ route('admin.customers.index', ['status' => 'pending']) }}"
                   class="rounded-xl px-4 py-4 ring-1 transition {{ $statusFilter === 'pending' ? 'bg-amber-600 text-white ring-amber-600' : 'bg-amber-50 ring-amber-100 hover:ring-amber-300' }}">
                    <p class="text-[10px] uppercase tracking-widest font-semibold {{ $statusFilter === 'pending' ? 'text-amber-100' : 'text-amber-800' }}">Incomplete registrations</p>
                    <p class="text-3xl font-bold mt-2 tabular-nums {{ $statusFilter === 'pending' ? 'text-white' : 'text-gray-900' }}">{{ number_format($counts['pending']) }}</p>
                </a>
                <a href="{{ route('admin.customers.index', ['status' => 'active']) }}"
                   class="rounded-xl px-4 py-4 ring-1 transition {{ $statusFilter === 'active' ? 'bg-emerald-700 text-white ring-emerald-700' : 'bg-emerald-50 ring-emerald-100 hover:ring-emerald-300' }}">
                    <p class="text-[10px] uppercase tracking-widest font-semibold {{ $statusFilter === 'active' ? 'text-emerald-100' : 'text-emerald-800' }}">Active</p>
                    <p class="text-3xl font-bold mt-2 tabular-nums {{ $statusFilter === 'active' ? 'text-white' : 'text-gray-900' }}">{{ number_format($counts['active']) }}</p>
                </a>
                <div class="rounded-xl bg-sky-50 ring-1 ring-sky-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-sky-800 font-semibold">With loans</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['with_loans']) }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 ring-1 ring-slate-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-slate-600 font-semibold">New this month</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['new_month']) }}</p>
                </div>
            </div>
        </div>
    </section>

    <x-admin.index-toolbar route="admin.customers" label="New customer" />
    @livewire('admin.customers-table')
</x-admin.layout>
