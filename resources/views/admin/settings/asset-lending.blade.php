<x-admin.layout title="Asset lending" heading="Asset lending" subheading="Deposit markup and marketplace policy">

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.asset-lending.save') }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-6 mb-8">
        @csrf @method('PUT')

        <div>
            <p class="text-sm font-semibold text-gray-900 mb-2">Deposit markup base</p>
            <p class="text-xs text-gray-500 mb-3">Controls how supplier markup is calculated on marketplace assets.</p>
            <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="markup_base" value="deposit" @checked(($values['markup_base'] ?? 'deposit') === 'deposit')>
                    Deposit amount (launch default)
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="markup_base" value="asset_price" @checked(($values['markup_base'] ?? '') === 'asset_price')>
                    Full asset price
                </label>
            </div>
        </div>

        <button class="rounded-lg bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 text-sm">Save settings</button>
    </form>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
        <h2 class="font-semibold text-gray-900 mb-3">Asset categories</h2>
        <p class="text-xs text-gray-500 mb-4">Category requirements (GPS, insurance, valuation) are defined in <code>config/asset_lending.php</code>.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500 bg-gray-50">
                    <tr>
                        <th class="px-3 py-2">Category</th>
                        <th class="px-3 py-2">GPS</th>
                        <th class="px-3 py-2">Insurance</th>
                        <th class="px-3 py-2">Valuation</th>
                        <th class="px-3 py-2">Ownership transfer</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($categories as $key => $row)
                        <tr>
                            <td class="px-3 py-2 font-medium">{{ $row['label'] ?? $key }}</td>
                            <td class="px-3 py-2">{{ ($row['gps_required'] ?? false) ? 'Yes' : '—' }}</td>
                            <td class="px-3 py-2">{{ ($row['insurance_required'] ?? false) ? 'Yes' : '—' }}</td>
                            <td class="px-3 py-2">{{ ($row['valuation_required'] ?? false) ? 'Yes' : '—' }}</td>
                            <td class="px-3 py-2">{{ ($row['ownership_transfer_required'] ?? false) ? 'Yes' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-admin.layout>
