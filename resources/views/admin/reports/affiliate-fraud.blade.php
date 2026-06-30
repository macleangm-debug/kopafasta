<x-admin.layout title="Affiliate fraud" heading="Affiliate fraud overview" subheading="Risk flags from device fingerprinting and identity overlap detection">

    <div class="grid grid-cols-3 gap-3 mb-6">
        @foreach ([
            ['Medium', $counts['medium'], 'bg-amber-50 ring-amber-200 text-amber-800'],
            ['High', $counts['high'], 'bg-orange-50 ring-orange-200 text-orange-800'],
            ['Blocked', $counts['blocked'], 'bg-red-50 ring-red-200 text-red-800'],
        ] as [$label, $count, $class])
            <div class="rounded-xl {{ $class }} ring-1 p-4">
                <p class="text-[10px] uppercase opacity-80">{{ $label }} risk</p>
                <p class="text-2xl font-bold">{{ $count }}</p>
            </div>
        @endforeach
    </div>

    <p class="text-xs text-gray-500 mb-4">Run <span class="font-mono">php artisan affiliate:scan-fraud</span> to refresh signals. Blocked affiliates are auto-suspended.</p>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">Partner</th>
                    <th class="px-5 py-3">Code</th>
                    <th class="px-5 py-3">Risk flag</th>
                    <th class="px-5 py-3">Score</th>
                    <th class="px-5 py-3">Signals</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($flagged as $affiliate)
                    @php $snap = $affiliate->affiliate_fraud_snapshot ?? []; @endphp
                    <tr class="hover:bg-gray-50 align-top">
                        <td class="px-5 py-3 font-medium">{{ $affiliate->name }}</td>
                        <td class="px-5 py-3 font-mono text-xs">{{ $affiliate->affiliate_code }}</td>
                        <td class="px-5 py-3 capitalize font-semibold">{{ $affiliate->affiliate_risk_flag }}</td>
                        <td class="px-5 py-3">{{ $snap['score'] ?? '—' }}</td>
                        <td class="px-5 py-3 text-xs text-gray-600 max-w-md">
                            @foreach (($snap['signals'] ?? []) as $signal)
                                <p>{{ $signal['message'] ?? '' }}</p>
                            @endforeach
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('admin.partners.show', $affiliate) }}" class="text-amber-700 hover:underline text-xs">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No elevated-risk affiliates.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>
