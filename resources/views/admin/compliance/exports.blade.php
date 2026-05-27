<x-admin.layout title="Regulatory Exports" heading="Regulatory Exports" subheading="Download monthly snapshots for BOT, FIU & TRA">
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 divide-y divide-gray-100">
        @foreach ($months as $m)
            <div class="flex items-center justify-between px-6 py-4">
                <div>
                    <div class="font-medium text-gray-800">{{ \Carbon\Carbon::parse($m.'-01')->format('F Y') }}</div>
                    <div class="text-xs text-gray-500">Portfolio, AML, KYC & ledger snapshot</div>
                </div>
                <div class="flex gap-2">
                    <button class="text-xs font-semibold text-gray-500 ring-1 ring-gray-300 hover:bg-gray-50 px-3 py-1.5 rounded-md" disabled>BOT (CSV)</button>
                    <button class="text-xs font-semibold text-gray-500 ring-1 ring-gray-300 hover:bg-gray-50 px-3 py-1.5 rounded-md" disabled>AML (XML)</button>
                    <button class="text-xs font-semibold text-gray-500 ring-1 ring-gray-300 hover:bg-gray-50 px-3 py-1.5 rounded-md" disabled>Ledger (XLSX)</button>
                </div>
            </div>
        @endforeach
    </div>
    <p class="mt-4 text-xs text-gray-500">Export generation is queued by the Compliance Reports job — buttons go live after the export worker is configured.</p>
</x-admin.layout>
