<x-site.supplier-layout title="Supplier dashboard" active="dashboard">
    <h1 class="text-2xl font-bold mb-2">Welcome, {{ $vendor->name }}</h1>
    <p class="text-sm text-gray-500 mb-6">Manage marketplace assets, reservations, and settlement status.</p>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ([['Assets', $stats['assets']], ['Active reservations', $stats['reservations']], ['Open requests', $stats['requests']], ['Pending payouts', 'TZS '.number_format($stats['pending_pay'])]] as [$label, $value])
            <div class="rounded-xl bg-white ring-1 ring-gray-200 p-5">
                <p class="text-xs uppercase text-gray-500">{{ $label }}</p>
                <p class="text-2xl font-bold mt-1">{{ $value }}</p>
            </div>
        @endforeach
    </div>
</x-site.supplier-layout>
