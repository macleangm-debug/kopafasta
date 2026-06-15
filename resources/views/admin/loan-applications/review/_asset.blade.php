<x-admin.review-section id="review-asset" title="Asset collateral" subtitle="Marketplace asset linked to this application">
    @if (empty($review['asset']))
        <p class="text-sm text-gray-500">No marketplace asset is linked to this application.</p>
    @else
        @php $asset = $review['asset']; @endphp
        <div class="rounded-xl ring-1 ring-gray-200 p-4 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-semibold text-gray-900">{{ $asset['title'] }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $asset['category'] }} · {{ $asset['supplier'] }}</p>
                </div>
                <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-amber-100 text-amber-800">
                    {{ display_label($asset['reservation_status'], 'asset_reservation_status') ?: ucfirst(str_replace('_', ' ', $asset['reservation_status'])) }}
                </span>
            </div>
            <dl class="grid sm:grid-cols-3 gap-3 text-sm">
                <div>
                    <dt class="text-xs text-gray-500">Asset value</dt>
                    <dd class="font-semibold mt-0.5">{{ format_money($asset['asset_value']) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Customer deposit</dt>
                    <dd class="font-semibold mt-0.5">{{ format_money($asset['customer_deposit']) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Availability</dt>
                    <dd class="font-semibold mt-0.5">{{ ucfirst($asset['availability_status']) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Deposit status</dt>
                    <dd class="font-semibold mt-0.5">{{ ucfirst($asset['deposit_status'] ?? 'pending') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Application fee</dt>
                    <dd class="font-semibold mt-0.5">{{ ucfirst($asset['reservation_fee_status'] ?? 'pending') }}</dd>
                </div>
                @if ($asset['viewing_date'])
                    <div>
                        <dt class="text-xs text-gray-500">Viewing date</dt>
                        <dd class="font-semibold mt-0.5">{{ $asset['viewing_date'] }}</dd>
                    </div>
                @endif
                @if ($asset['serial_number'] ?? null)
                    <div><dt class="text-xs text-gray-500">Serial / reg.</dt><dd class="font-semibold mt-0.5">{{ $asset['serial_number'] }}</dd></div>
                @endif
                @if ($asset['chassis_number'] ?? null)
                    <div><dt class="text-xs text-gray-500">Chassis</dt><dd class="font-semibold mt-0.5">{{ $asset['chassis_number'] }}</dd></div>
                @endif
                @if ($asset['engine_number'] ?? null)
                    <div><dt class="text-xs text-gray-500">Engine</dt><dd class="font-semibold mt-0.5">{{ $asset['engine_number'] }}</dd></div>
                @endif
                @if ($asset['insurance_policy_number'] ?? null)
                    <div><dt class="text-xs text-gray-500">Insurance policy</dt><dd class="font-semibold mt-0.5">{{ $asset['insurance_policy_number'] }}</dd></div>
                @endif
            </dl>
        </div>
    @endif
</x-admin.review-section>
