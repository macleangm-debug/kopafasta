@php
    $assets = $dossier['assets'] ?? collect();
    $collateralDocs = $dossier['documents_by_context']['collateral'] ?? collect();
    $typeOptions = \App\Models\CustomerAsset::typeOptions();
@endphp

<div class="space-y-6">
    <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">Assets / collateral</p>
        <h4 class="text-base font-bold text-gray-900 mt-0.5">Declared assets</h4>
        <p class="text-xs text-gray-500 mt-0.5">Separate from Profile completion percentage when collateral is optional.</p>
    </div>

    @if ($assets->isEmpty())
        <p class="text-sm text-gray-500">No collateral/assets on file.</p>
    @else
        <div class="space-y-3">
            @foreach ($assets as $asset)
                <article class="rounded-2xl ring-1 ring-brand/10 bg-white px-4 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $asset->label ?: ($typeOptions[$asset->asset_type] ?? $asset->asset_type) }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $typeOptions[$asset->asset_type] ?? $asset->asset_type }}</p>
                        </div>
                        <p class="text-sm font-bold tabular-nums text-gray-900">
                            {{ $asset->estimated_value ? format_money((float) $asset->estimated_value) : '—' }}
                        </p>
                    </div>
                    @if ($asset->description)
                        <p class="text-sm text-gray-600 mt-2">{{ $asset->description }}</p>
                    @endif
                    @if ($asset->registration_number)
                        <p class="text-xs text-gray-500 mt-2">Reg / ref: <span class="font-mono">{{ $asset->registration_number }}</span></p>
                    @endif
                    @php
                        $detailFields = \App\Models\CustomerAsset::detailFieldsFor((string) $asset->asset_type);
                        $details = $asset->details();
                    @endphp
                    @if ($detailFields !== [] && $details !== [])
                        <dl class="mt-3 grid sm:grid-cols-2 gap-2 text-xs">
                            @foreach ($detailFields as $field)
                                @php
                                    $fkey = is_array($field) ? ($field['key'] ?? null) : $field;
                                    $flabel = is_array($field) ? ($field['label'] ?? $fkey) : $fkey;
                                    $fval = $fkey ? ($details[$fkey] ?? null) : null;
                                @endphp
                                @if ($fkey && filled($fval))
                                    <div>
                                        <dt class="text-gray-500 uppercase tracking-wider text-[10px]">{{ $flabel }}</dt>
                                        <dd class="font-medium text-gray-800 mt-0.5">{{ is_array($fval) ? implode(', ', $fval) : $fval }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    @if ($collateralDocs->isNotEmpty())
        <section class="border-t border-gray-100 pt-5">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand mb-3">Ownership / valuation / insurance</p>
            <ul class="space-y-2">
                @foreach ($collateralDocs as $doc)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl ring-1 ring-gray-100 px-3 py-2.5 text-sm">
                        <span class="font-medium">{{ $doc->documentType?->name ?? 'Document' }}</span>
                        <span class="text-xs text-gray-500">{{ display_label($doc->status, 'document_status') }}</span>
                        @if ($doc->file_path)
                            @php
                                $ext = strtolower(pathinfo($doc->file_path, PATHINFO_EXTENSION));
                                $previewType = $ext === 'pdf' ? 'pdf' : 'image';
                            @endphp
                            <button type="button"
                                    onclick="window.kfOpenDocumentPreview(@js(asset('storage/'.$doc->file_path)), @js($doc->documentType?->name ?? 'Document'), @js($previewType))"
                                    class="text-xs font-semibold text-brand hover:underline">View</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
