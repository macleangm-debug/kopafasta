<x-site.supplier-layout title="Supplier dashboard" active="dashboard">
    <section class="mb-6 rounded-2xl bg-gradient-to-br from-brand to-brand-light text-white ring-1 ring-brand/30 p-5 sm:p-6 relative overflow-hidden">
        <div class="relative max-w-xl space-y-3">
            <p class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-tight">{{ $vendor->name }}</p>
            <p class="text-sm font-mono text-white/75">{{ $vendor->vendor_number ?? $vendor->partner_number ?? 'PTR' }}</p>
            <p class="text-sm text-white/90">Manage assets, reservations, and settlements from one place.</p>
            <div class="flex flex-wrap gap-2 pt-1">
                <a href="{{ route('site.supplier.assets.create') }}"
                   class="inline-flex bg-brand-gold hover:brightness-95 text-brand font-bold px-5 py-2.5 rounded-xl text-sm">
                    Upload asset
                </a>
                <a href="{{ route('site.supplier.reservations') }}"
                   class="inline-flex bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                    Reservations
                </a>
                <a href="{{ route('site.supplier.settlements') }}"
                   class="inline-flex bg-white/15 hover:bg-white/25 ring-1 ring-white/30 text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                    Settlements
                </a>
            </div>
        </div>
    </section>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <a href="{{ route('site.supplier.assets') }}" class="glass-card rounded-2xl ring-1 ring-brand/15 p-5 hover:ring-brand/30 transition">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Assets</p>
            <p class="text-3xl font-extrabold text-brand tabular-nums mt-1">{{ $stats['assets'] }}</p>
        </a>
        <a href="{{ route('site.supplier.reservations') }}" class="glass-card rounded-2xl ring-1 ring-brand/15 p-5 hover:ring-brand/30 transition">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Active reservations</p>
            <p class="text-3xl font-extrabold text-brand tabular-nums mt-1">{{ $stats['reservations'] }}</p>
        </a>
        <a href="{{ route('site.supplier.requests') }}" class="glass-card rounded-2xl ring-1 ring-brand/15 p-5 hover:ring-brand/30 transition">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Assigned requests</p>
            <p class="text-3xl font-extrabold text-amber-600 tabular-nums mt-1">{{ $stats['requests'] }}</p>
        </a>
        <a href="{{ route('site.supplier.settlements') }}" class="glass-card rounded-2xl ring-1 ring-brand/15 p-5 hover:ring-brand/30 transition">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Pending payouts</p>
            <p class="text-2xl font-extrabold text-brand tabular-nums mt-1">TZS {{ format_number($stats['pending_pay']) }}</p>
        </a>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach ([
            ['Upload asset', route('site.supplier.assets.create'), 'Add stock to the marketplace'],
            ['Expected payouts', route('site.supplier.applications'), 'See amounts tied to your assets'],
            ['Asset requests', route('site.supplier.requests'), 'Admin-assigned borrower requests'],
            ['Profile', route('site.supplier.profile'), 'Update contact details'],
        ] as [$label, $url, $hint])
            <a href="{{ $url }}" class="rounded-2xl bg-white ring-1 ring-gray-200 hover:ring-brand/30 px-4 py-4 transition">
                <p class="font-semibold text-sm text-gray-900">{{ $label }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $hint }}</p>
            </a>
        @endforeach
    </div>
</x-site.supplier-layout>
