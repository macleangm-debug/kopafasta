<x-admin.layout title="Asset lending" heading="Asset lending" subheading="Markup rules and marketplace policy">
    @include('admin.settings._tabs', ['active' => 'asset-lending'])

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.asset-lending.save') }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-6 mb-8">
        @csrf @method('PUT')

        <div>
            <p class="text-sm font-semibold text-gray-900 mb-2">Markup rules</p>
            <p class="text-xs text-gray-500 mb-3">Suppliers enter asset cost and deposit; the platform applies this markup to calculate customer price. Suppliers cannot override markup.</p>
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

        <div class="grid md:grid-cols-2 gap-4">
            <x-admin.input name="default_deposit_markup_percent" label="Default deposit markup (%)" type="number" step="0.01"
                           :value="$values['default_deposit_markup_percent'] ?? 10" required />
            <x-admin.input name="default_monthly_rate_percent" label="Default monthly rate for weekly installment calc (%)" type="number" step="0.01"
                           :value="$values['default_monthly_rate_percent'] ?? 12" required />
            <x-admin.input name="default_waiting_period_days" label="Default asset waiting period (days)" type="number"
                           :value="$values['default_waiting_period_days'] ?? 7" required />
            <x-admin.input name="insurance_expiry_warning_days" label="Insurance expiry warning (days before)" type="number"
                           :value="$values['insurance_expiry_warning_days'] ?? 30" required />
            <x-admin.input name="max_asset_photos" label="Max asset photos per listing" type="number"
                           :value="$values['max_asset_photos'] ?? 4" required />
            <x-admin.input name="vehicle_max_age_years" label="Vehicle max age (years from manufacture)" type="number"
                           :value="$values['vehicle_max_age_years'] ?? config('asset_lending.vehicle_max_age_years', 10)" required />
        </div>
        <p class="text-xs text-gray-500">Waiting period is shown to borrowers between deposit and loan application. The monthly rate drives auto-calculated weekly installments during loan processing. Insurance warning appears in underwriting when policy expiry is near or past due. Vehicle max age limits the year-of-manufacture dropdown on borrower collateral (e.g. 10 → from {{ now()->year - 10 }} to {{ now()->year }}).</p>

        <div class="border-t border-gray-100 pt-6">
            <p class="text-sm font-semibold text-gray-900 mb-2">Partner code format</p>
            <p class="text-xs text-gray-500 mb-3">Auto-generated codes follow <span class="font-mono">{prefix}-{type}-{country}-{id}</span> (e.g. PT-SP-TZ-1AV3).</p>
            <div class="grid md:grid-cols-2 gap-4">
                <x-admin.input name="code_prefix" label="Code prefix" :value="$values['code_prefix'] ?? 'PT'" required />
                <x-admin.input name="default_country_code" label="Default country code" :value="$values['default_country_code'] ?? 'TZ'" required maxlength="2" />
            </div>
        </div>

        <button class="rounded-lg bg-brand-gold hover:brightness-95 text-brand font-semibold px-5 py-2.5 text-sm">Save settings</button>
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
