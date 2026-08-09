@php
    $application = $record;
    $isAl = app(\App\Services\AssetLendingService::class)->isAssetLendingApplication($application);
    $reservation = $application->assetReservation;
    $asset = $reservation?->asset;
    $reqs = $asset ? app(\App\Services\AssetLendingService::class)->categoryRequirements($asset->category) : [];
@endphp

@if ($isAl && $reservation)
    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5 mb-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-1">Asset lending — handover pipeline</h3>
        <p class="text-xs text-gray-500 mb-4">After deposit and post-approval fees: GPS → insurance (full asset value) → registration → handover (loan start).</p>

        <div class="flex flex-wrap gap-2 mb-4">
            <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-amber-100 text-amber-900 ring-1 ring-amber-200">
                Current: {{ display_label($reservation->status, 'asset_reservation_status') ?: ucfirst(str_replace('_', ' ', $reservation->status)) }}
            </span>
            @if ($disbursementReadiness->canMarkAssetHandover($application))
                <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-emerald-100 text-emerald-800">Ready for handover</span>
            @endif
        </div>

        @if ($asset)
            @php
                $supplierType = $asset->vendor ? app(\App\Services\AssetLendingService::class)->supplierType($asset->vendor) : null;
                $insuredValue = app(\App\Services\AssetLendingService::class)->insuredValueForMarketplaceAsset($asset);
            @endphp
            @if ($supplierType === 'upfront_settlement')
                <p class="text-xs text-brand bg-brand-muted rounded-lg px-3 py-2 mb-4">Upfront settlement supplier — company pays net asset value (after customer deposit) on loan approval.</p>
            @endif
            <p class="text-xs text-gray-600 mb-3">Insurance cover value (full asset): <span class="font-semibold tabular-nums">{{ format_money($insuredValue) }}</span></p>
            <form method="POST" action="{{ route('admin.loan-applications.asset-identifiers', $application) }}" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4 border-t border-gray-100 pt-4">
                @csrf
                @method('PUT')
                <x-admin.input name="serial_number" label="Serial / reg. no." :value="old('serial_number', $asset->serial_number)" />
                <x-admin.input name="chassis_number" label="Chassis no." :value="old('chassis_number', $asset->chassis_number)" />
                <x-admin.input name="engine_number" label="Engine no." :value="old('engine_number', $asset->engine_number)" />
                <x-admin.input name="insurance_policy_number" label="Insurance policy" :value="old('insurance_policy_number', $asset->insurance_policy_number)" />
                <div class="sm:col-span-2 lg:col-span-4">
                    <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2 rounded-lg text-sm">Save asset identifiers</button>
                </div>
            </form>
        @endif

        <div class="flex flex-wrap gap-2 border-t border-gray-100 pt-4">
            @if (($reqs['gps_required'] ?? false) && ! in_array($reservation->status, ['gps_installation', 'insurance_active', 'registration_complete', 'released'], true))
                <div class="w-full rounded-lg bg-sky-50 ring-1 ring-sky-200 p-4 mb-2 space-y-3">
                    <p class="text-xs font-semibold text-sky-900">Assign GPS installer</p>
                    <form method="POST" action="{{ route('admin.loan-applications.assign-gps', $application) }}" class="flex flex-wrap gap-3 items-end">
                        @csrf
                        <div class="min-w-[200px]">
                            <label class="block text-[10px] uppercase text-gray-500 mb-1">Installer</label>
                            <select name="vendor_id" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="">— Select installer —</option>
                                @foreach (($gpsInstallers ?? collect()) as $installer)
                                    <option value="{{ $installer->id }}" @selected(($suggestedGpsInstaller?->id ?? null) === $installer->id)>
                                        {{ $installer->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="bg-sky-700 hover:bg-sky-600 text-white font-semibold px-4 py-2 rounded-lg text-sm">Assign selected</button>
                    </form>
                    <form method="POST" action="{{ route('admin.loan-applications.assign-gps', $application) }}">
                        @csrf
                        <input type="hidden" name="auto" value="1">
                        <button type="submit" class="text-xs font-semibold text-sky-800 underline">Auto-match by region</button>
                    </form>
                </div>
                <form method="POST" action="{{ route('admin.loan-applications.reservation-advance', $application) }}">
                    @csrf
                    <input type="hidden" name="action" value="gps_installation">
                    <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white font-semibold px-4 py-2 rounded-lg text-sm">Mark GPS installation</button>
                </form>
            @endif
            @if (($reqs['insurance_required'] ?? false) && ! in_array($reservation->status, ['insurance_active', 'registration_complete', 'released'], true))
                <form method="POST" action="{{ route('admin.loan-applications.reservation-advance', $application) }}">
                    @csrf
                    <input type="hidden" name="action" value="insurance_active">
                    <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2 rounded-lg text-sm">Mark insurance active</button>
                </form>
            @endif
            @if (($reqs['ownership_transfer_required'] ?? false) && $reservation->status === 'insurance_active')
                <form method="POST" action="{{ route('admin.loan-applications.reservation-advance', $application) }}">
                    @csrf
                    <input type="hidden" name="action" value="registration_complete">
                    <button type="submit" class="bg-indigo-700 hover:bg-indigo-600 text-white font-semibold px-4 py-2 rounded-lg text-sm">Mark registration complete</button>
                </form>
            @endif
            @if ($disbursementReadiness->canMarkAssetHandover($application) && $reservation->status !== 'released')
                <form method="POST" action="{{ route('admin.loan-applications.reservation-advance', $application) }}">
                    @csrf
                    <input type="hidden" name="action" value="release">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-4 py-2 rounded-lg text-sm">Mark asset handed over</button>
                </form>
            @endif
        </div>

        @if ($application->manualPostApprovalFees->isNotEmpty())
            <div class="mt-4 border-t border-gray-100 pt-4">
                <p class="text-xs font-semibold text-gray-600 mb-2">Manual post-approval fees</p>
                <ul class="text-sm space-y-1">
                    @foreach ($application->manualPostApprovalFees as $fee)
                        <li class="flex justify-between gap-3">
                            <span>{{ $fee->description }}</span>
                            <span class="font-semibold">{{ format_money($fee->borrower_amount) }}
                                <span class="text-xs text-gray-500">({{ ucfirst($fee->status) }})</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.loan-applications.manual-fee', $application) }}" class="mt-6 pt-4 border-t border-gray-100 grid sm:grid-cols-4 gap-3 items-end">
            @csrf
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Add post-approval fee</label>
                <input type="text" name="description" required placeholder="Ownership transfer, insurance…" class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <x-admin.input name="partner_cost" label="Partner cost" type="number" step="1" min="0" required />
            <x-admin.input name="markup_percent" label="Markup %" type="number" step="0.1" min="0" value="10" required />
            <div class="sm:col-span-4">
                <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2 rounded-lg text-sm">Add fee request</button>
            </div>
        </form>
    </div>
@endif
