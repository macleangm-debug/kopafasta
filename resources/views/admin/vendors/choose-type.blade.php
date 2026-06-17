<x-admin.layout title="Add partner" heading="Add partner" subheading="Choose the partner type to load the relevant form">
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 max-w-4xl">
        @php
            $types = [
                'valuer'        => ['Valuer', 'Coverage area, vehicle types, licence'],
                'affiliate'     => ['Affiliate partner', 'Commission settings, promo code'],
                'debt_collector'=> ['Recovery partner', 'Recovery rates and SLA'],
                'gps_installer' => ['GPS partner', 'Installation coverage'],
                'insurance'     => ['Insurance partner', 'Policy types and coverage'],
                'supplier'      => ['Supplier', 'Managed loan or upfront settlement'],
                'legal_partner' => ['Legal partner', 'Advocate details and stamp'],
                'auctioneer'    => ['Auction partner', 'Auction coverage'],
            ];
        @endphp
        @foreach ($types as $key => [$label, $hint])
            <a href="{{ route('admin.partners.create', ['category' => $key]) }}"
               class="block rounded-xl bg-white ring-1 ring-gray-200 p-5 hover:ring-amber-300 hover:shadow-sm transition">
                <h3 class="font-semibold text-gray-900">{{ $label }}</h3>
                <p class="text-sm text-gray-500 mt-1">{{ $hint }}</p>
            </a>
        @endforeach
    </div>
</x-admin.layout>
