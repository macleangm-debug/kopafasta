@php
    $person = $person ?? 'borrower';
    $assets = collect($review['assets'] ?? $review['collaterals'] ?? []);
    $canRequestDocs = auth()->user()?->hasPermission('applications.request_documents');
    $collateralPresets = \App\Services\ApplicationDocumentRequestService::COLLATERAL_PRESET_LABELS;
@endphp

<section class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
    <div class="px-5 sm:px-6 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white">
        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Collateral</p>
        <h2 class="text-sm font-semibold text-gray-900 mt-0.5">
            {{ $person === 'guarantor' ? 'Guarantor collateral' : 'Borrower collateral' }}
        </h2>
        <p class="text-xs text-gray-500 mt-0.5">
            Uploaded collateral stays visible for screening and committee. Request more when nothing is on file yet.
        </p>
    </div>

    <div class="p-5 sm:p-6 space-y-4">
        @if ($assets->isEmpty())
            <div class="rounded-xl bg-amber-50/60 ring-1 ring-amber-100 px-4 py-4">
                <p class="text-sm font-semibold text-amber-950">No collateral on file</p>
                <p class="text-xs text-amber-900/80 mt-1">
                    {{ $person === 'guarantor'
                        ? 'This guarantor has not uploaded collateral assets yet.'
                        : 'This borrower has not uploaded collateral assets yet.' }}
                </p>
            </div>

            @if ($canRequestDocs && $person === 'borrower')
                <form method="POST" action="{{ route('admin.loan-applications.document-requests.store', $record) }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="type" value="document">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">Request collateral from borrower</p>
                    <div class="grid sm:grid-cols-2 gap-2">
                        @foreach ($collateralPresets as $preset)
                            <label class="flex items-start gap-2 text-sm text-gray-700 bg-emerald-50/80 rounded-xl px-3 py-2 ring-1 ring-brand/10">
                                <input type="checkbox" name="presets[]" value="{{ $preset }}" class="mt-0.5 rounded border-gray-300 text-brand">
                                <span>{{ $preset }}</span>
                            </label>
                        @endforeach
                    </div>
                    <textarea name="instructions" rows="2" maxlength="2000"
                              placeholder="Optional note shown to the borrower"
                              class="w-full rounded-xl border-brand/15 text-sm ring-1 ring-brand/10 px-3 py-2.5"></textarea>
                    <button type="submit" class="inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl">
                        Request collateral
                    </button>
                </form>
            @endif
        @else
            <div class="grid md:grid-cols-2 gap-4">
                @foreach ($assets as $asset)
                    @php
                        $name = is_array($asset)
                            ? ($asset['name'] ?? $asset['label'] ?? 'Collateral')
                            : ($asset->name ?? $asset->label ?? 'Collateral');
                        $type = is_array($asset)
                            ? ($asset['type'] ?? $asset['asset_type'] ?? null)
                            : ($asset->type ?? $asset->asset_type ?? null);
                        $value = is_array($asset)
                            ? ($asset['estimated_value'] ?? $asset['value'] ?? null)
                            : ($asset->estimated_value ?? $asset->value ?? null);
                    @endphp
                    <div class="rounded-xl ring-1 ring-gray-200 bg-gray-50/50 p-4">
                        <p class="text-sm font-semibold text-gray-900">{{ $name }}</p>
                        @if ($type)
                            <p class="text-xs text-gray-500 mt-1">{{ $type }}</p>
                        @endif
                        @if ($value !== null && $value !== '')
                            <p class="text-sm font-bold text-gray-900 mt-2">{{ is_numeric($value) ? format_money((float) $value) : $value }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
