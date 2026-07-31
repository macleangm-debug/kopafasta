{{-- Shared partner form. Expects $record, $statuses, $categories, $regionOptions --}}
@php
    $r = $record ?? null;
    $category = old('category', $r?->category ?? ($defaultCategory ?? 'supplier'));
    $selectedRegions = old('regions', $r?->regions ?? []);
    $needsCoverage = in_array($category, ['valuer', 'gps_installer', 'insurance', 'debt_collector', 'towing', 'auctioneer', 'legal_partner', 'supplier'], true);
    $isServiceRates = in_array($category, ['debt_collector', 'towing', 'auctioneer', 'legal_partner', 'gps_installer', 'insurance'], true);
    $isValuer = $category === 'valuer';
    $isSupplier = $category === 'supplier';
    $isAffiliate = $category === 'affiliate';
    $recoveryDefaults = config('recovery.partner_types.'.$category, []);
    $defaultCommission = $recoveryDefaults['default_commission_percent'] ?? null;
    $defaultMarkup = $recoveryDefaults['default_markup_percent'] ?? config('gps_pricing.markup_percent', 10);
@endphp

<div>
    <x-admin.step title="Partner type">
        <p class="md:col-span-2 text-sm text-gray-600">
            Selected type: <span class="font-semibold text-gray-900 capitalize">{{ str_replace('_', ' ', $category) }}</span>
            @if (! $r)
                · <a href="{{ route('admin.partners.create') }}" class="text-amber-700 hover:underline">Change type</a>
            @endif
        </p>
        <input type="hidden" name="category" value="{{ $category }}">
    </x-admin.step>

    <x-admin.step title="Basic info">
        @if ($r)
            <div>
                <p class="text-xs font-semibold text-gray-700 mb-1">Partner code</p>
                <p class="text-sm font-mono text-gray-900">{{ $r->vendor_number }}</p>
            </div>
        @endif
        <x-admin.input  name="name"          label="Trading / display name" :value="$r?->name" required />
        <x-admin.input  name="legal_name"    label="Legal business name" :value="$r?->legal_name" />
        <x-admin.input  name="registration_number" label="BRELA / registration no." :value="$r?->registration_number" />
        <x-admin.input  name="tin"           label="TIN" :value="$r?->tin" />
        <x-admin.select name="status"        label="Status"        :options="$statuses"        :value="$r?->status ?? 'active'" required />
    </x-admin.step>

    @if ($needsCoverage)
        <x-admin.step title="Coverage regions">
            <div class="md:col-span-2 space-y-3">
                <p class="text-xs text-gray-500">Select regions, or mark the partner as nationwide for all regions.</p>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="radio" name="coverage_type" value="regions" @checked(old('coverage_type', $r?->coverage_type ?? 'regions') !== 'nationwide') class="text-amber-600 focus:ring-amber-500">
                    Specific regions
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="radio" name="coverage_type" value="nationwide" @checked(old('coverage_type', $r?->coverage_type ?? 'regions') === 'nationwide') class="text-amber-600 focus:ring-amber-500">
                    Nationwide
                </label>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-64 overflow-y-auto rounded-lg border border-gray-200 p-3">
                    @foreach ($regionOptions ?? [] as $region)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="regions[]" value="{{ $region }}"
                                   @checked(in_array($region, $selectedRegions, true))
                                   class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                            <span>{{ $region }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </x-admin.step>
    @endif

    <x-admin.step title="Contact">
        <x-admin.phone-input name="phone" label="Phone" :value="$r?->phone" />
        <x-admin.input  name="email"         label="Email"         :value="$r?->email"         type="email" />
        @if (! $isValuer)
            <div class="md:col-span-2">
                <x-admin.textarea name="address" label="Address / coverage area" :value="$r?->address" rows="2"
                                  placeholder="Office address or service coverage notes" />
            </div>
        @endif
    </x-admin.step>

    <x-admin.step title="Business documents">
        <p class="md:col-span-2 text-xs text-gray-500">Same document set as public partner enrollment (BRELA, TIN certificate, business licence). PDF or image, max 5MB each.</p>
        @foreach ([
            'doc_brela' => 'BRELA / company registration',
            'doc_tin_certificate' => 'TIN certificate',
            'doc_business_licence' => 'Business licence',
            'doc_national_id_front' => 'Registrant National ID (front)',
            'doc_national_id_back' => 'Registrant National ID (back)',
            'doc_other' => 'Other supporting document',
        ] as $input => $label)
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-700 mb-1">{{ $label }}</label>
                <input type="file" name="{{ $input }}" accept=".jpg,.jpeg,.png,.pdf"
                       class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-500 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-gray-900">
            </div>
        @endforeach
        @if ($r)
            @php $docs = $r->documents()->whereNotNull('doc_type')->latest()->get(); @endphp
            @if ($docs->isNotEmpty())
                <div class="md:col-span-2 space-y-2">
                    <p class="text-xs font-semibold text-gray-700">Uploaded documents</p>
                    @foreach ($docs as $doc)
                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($doc->file_path) }}" target="_blank"
                           class="block text-sm text-amber-700 hover:underline">{{ $doc->label ?: ($doc->doc_type.' · '.$doc->file_path) }}</a>
                    @endforeach
                </div>
            @endif
        @endif
    </x-admin.step>

    @if ($isValuer || $isServiceRates)
        <x-admin.step title="Rates">
            <div class="md:col-span-2 rounded-xl bg-amber-50 ring-1 ring-amber-200/80 px-4 py-3 text-sm text-amber-950 space-y-2">
                <p class="font-semibold">Default rates come from Settings</p>
                <p class="text-xs text-amber-900/80">
                    Platform defaults (commission / markup) are managed in
                    <a href="{{ route('admin.settings.hub') }}" class="font-semibold underline">Settings hub</a>
                    → Recovery / partner pricing. Leave override fields blank to use defaults.
                </p>
                <ul class="text-xs space-y-1 pt-1">
                    @if ($defaultCommission !== null)
                        <li>Default recovery commission: <strong>{{ rtrim(rtrim(number_format((float) $defaultCommission, 2), '0'), '.') }}%</strong></li>
                    @endif
                    <li>Default company markup: <strong>{{ rtrim(rtrim(number_format((float) $defaultMarkup, 2), '0'), '.') }}%</strong></li>
                </ul>
            </div>
            <details class="md:col-span-2 rounded-xl border border-gray-200 bg-white p-4" @if(filled(old('partner_cost', $r?->partner_cost)) || filled(old('markup_percent', $r?->markup_percent)) || filled(old('recovery_commission_percent', $r?->recovery_commission_percent)) || filled(old('recovery_markup_percent', $r?->recovery_markup_percent))) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-gray-800">Optional partner override</summary>
                <p class="mt-2 mb-4 text-xs text-gray-500">Only set these when this partner needs a negotiated rate different from Settings. Prefer documenting approval offline.</p>
                <div class="grid md:grid-cols-2 gap-4">
                    <x-admin.input name="partner_cost" label="Override base cost (TZS)" type="number" step="0.01" :value="$r?->partner_cost" />
                    <x-admin.input name="markup_percent" label="Override markup (%)" type="number" step="0.01" :value="$r?->markup_percent" />
                    @if ($isServiceRates)
                        <x-admin.input name="recovery_commission_percent" label="Override recovery commission (%)" type="number" step="0.01" :value="$r?->recovery_commission_percent" />
                        <x-admin.input name="recovery_markup_percent" label="Override company markup (%)" type="number" step="0.01" :value="$r?->recovery_markup_percent" />
                    @endif
                </div>
            </details>
        </x-admin.step>
    @endif

    @if ($isSupplier)
        <x-admin.step title="Supplier settings">
            <p class="md:col-span-2 text-xs text-gray-500 mb-2">Deposit markup is controlled under Settings → Asset lending (not per supplier).</p>
            <x-admin.select name="supplier_type" label="Supplier payment mode" :options="config('asset_lending.supplier_types')" :value="$r?->supplier_type ?? config('asset_lending.default_supplier_type')" />
            <p class="md:col-span-2 text-xs text-gray-500">
                <strong>Direct repayment</strong> — supplier receives principal from customer repayments.
                <strong>Full upfront payment</strong> — entire asset value is paid to supplier on loan approval; future repayments are managed under capital financing.
            </p>
        </x-admin.step>
    @endif

    @if ($isAffiliate)
        <x-admin.step title="Affiliate program">
            <div class="md:col-span-2 rounded-xl bg-amber-50 ring-1 ring-amber-200/80 px-4 py-3 text-sm text-amber-950 mb-2">
                Defaults from <code class="text-xs">config/affiliates.php</code> / Settings. Overrides below are optional.
            </div>
            <x-admin.input name="affiliate_code" label="Promo / affiliate code" :value="$r?->affiliate_code" placeholder="Auto-generated for affiliates" />
            <x-admin.input name="registration_discount_percent" label="Registration discount (%)" type="number" step="0.01" :value="$r?->registration_discount_percent ?? config('affiliates.default_registration_discount_percent')" />
            <x-admin.input name="application_discount_percent" label="Application discount (%)" type="number" step="0.01" :value="$r?->application_discount_percent ?? config('affiliates.default_application_discount_percent')" />
            <x-admin.input name="affiliate_commission_percent" label="Commission (%)" type="number" step="0.01" :value="$r?->affiliate_commission_percent ?? config('affiliates.default_commission_percent')" />
        </x-admin.step>
    @endif
</div>
