<x-admin.review-section id="review-asset" title="Asset collateral" subtitle="Marketplace asset linked to this application">
    @if (empty($review['asset']))
        <p class="text-sm text-gray-500">No marketplace asset is linked to this application.</p>
    @else
        @php
            $asset = $review['asset'];
            $insurance = $asset['insurance_status'] ?? null;
            $insuranceTone = match ($insurance['tone'] ?? '') {
                'red'     => 'bg-red-50 ring-red-200 text-red-900',
                'amber'   => 'bg-amber-50 ring-amber-200 text-amber-900',
                'emerald' => 'bg-emerald-50 ring-emerald-200 text-emerald-900',
                default   => 'bg-gray-50 ring-gray-200 text-gray-800',
            };
        @endphp

        @if ($insurance && ($insurance['status'] ?? '') !== 'valid')
            <div class="mb-4 rounded-lg ring-1 px-4 py-3 text-sm {{ $insuranceTone }}">
                <p class="font-semibold">{{ $insurance['label'] }}</p>
                <p class="mt-1 text-xs opacity-90">{{ $insurance['detail'] }}</p>
                @perm('applications.request_documents')
                    @if (in_array($insurance['status'], ['expired', 'expiring', 'missing'], true))
                        <div class="mt-3 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.loan-applications.document-requests.store', $record) }}">
                                @csrf
                                <input type="hidden" name="type" value="document">
                                <input type="hidden" name="presets[]" value="{{ $insurance['status'] === 'expired' ? 'New Insurance Certificate' : 'Insurance About To Expire' }}">
                                <button type="submit" class="text-xs font-semibold rounded-lg px-3 py-1.5 bg-white/80 hover:bg-white ring-1 ring-current">
                                    Request updated insurance
                                </button>
                            </form>
                        </div>
                    @endif
                @endperm
            </div>
        @endif

        @perm('applications.request_documents')
            <div class="mb-4 flex flex-wrap gap-2">
                @foreach ([
                    'New Asset Photo' => 'Request asset photo',
                    'New Ownership Document' => 'Request ownership doc',
                    'Updated National ID' => 'Request updated ID',
                ] as $preset => $buttonLabel)
                    <form method="POST" action="{{ route('admin.loan-applications.document-requests.store', $record) }}">
                        @csrf
                        <input type="hidden" name="type" value="document">
                        <input type="hidden" name="presets[]" value="{{ $preset }}">
                        <button type="submit" class="text-xs font-semibold rounded-lg px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-800 ring-1 ring-gray-200">
                            {{ $buttonLabel }}
                        </button>
                    </form>
                @endforeach
            </div>
        @endperm

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
                @if (! empty($asset['waiting_period_days']))
                    <div>
                        <dt class="text-xs text-gray-500">Waiting period</dt>
                        <dd class="font-semibold mt-0.5">{{ $asset['waiting_period_days'] }} days</dd>
                    </div>
                @endif
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
                @if ($asset['insurance_expires_at'] ?? null)
                    <div>
                        <dt class="text-xs text-gray-500">Insurance expiry</dt>
                        <dd class="font-semibold mt-0.5">{{ optional($asset['insurance_expires_at'])->format('d M Y') }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    @endif
</x-admin.review-section>
