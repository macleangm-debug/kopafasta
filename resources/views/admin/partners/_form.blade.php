{{-- Shared partner form. Expects $record, $statuses, $categories, $regionOptions --}}
@php
    $r = $record ?? null;
    $category = old('category', $r?->category ?? ($defaultCategory ?? 'supplier'));
    $applicantCategory = old('applicant_category', $r?->applicant_category ?? 'company');
    $selectedRegions = old('regions', $r?->regions ?? []);
    $personTypes = ['affiliate', 'valuer'];
    $recoveryDefaults = config('recovery.partner_types.'.$category, []);
    $defaultCommission = $recoveryDefaults['default_commission_percent'] ?? null;
    $defaultMarkup = $recoveryDefaults['default_markup_percent'] ?? config('gps_pricing.markup_percent', 10);
@endphp

<div
    x-data="{
        category: @js($category),
        applicantCategory: @js($applicantCategory),
        personTypes: @js($personTypes),
        get needsCoverage() {
            return ['valuer','gps_installer','insurance','debt_collector','towing','auctioneer','legal_partner','supplier'].includes(this.category);
        },
        get isServiceRates() {
            return ['debt_collector','towing','auctioneer','legal_partner','gps_installer','insurance'].includes(this.category);
        },
        get isValuer() { return this.category === 'valuer'; },
        get isSupplier() { return this.category === 'supplier'; },
        get isAffiliate() { return this.category === 'affiliate'; },
        get allowsPerson() { return this.personTypes.includes(this.category); },
        get isIndividual() { return this.allowsPerson && this.applicantCategory === 'individual'; },
        get isCompany() { return ! this.allowsPerson || this.applicantCategory === 'company'; },
    }"
    x-init="
        $watch('category', () => { $nextTick(() => window.dispatchEvent(new CustomEvent('admin-wizard-rebuild'))); });
        $watch('applicantCategory', () => { $nextTick(() => window.dispatchEvent(new CustomEvent('admin-wizard-rebuild'))); });
        $nextTick(() => window.dispatchEvent(new CustomEvent('admin-wizard-rebuild')));
    "
    class="space-y-0"
>
    <x-admin.step title="Partner type">
        <div class="md:col-span-2">
            <x-admin.select
                name="category"
                label="Partner type"
                :options="$categories"
                :value="$category"
                required
                x-model="category"
                help="Only the sections relevant to this type are shown below."
            />
        </div>

        <div class="md:col-span-2" x-show="allowsPerson" x-cloak>
            <p class="text-xs font-semibold text-gray-700 mb-2">Entity type</p>
            <p class="text-xs text-gray-500 mb-3">Affiliates and valuers may be an individual or a company. Other partner types are companies.</p>
            <div class="flex flex-wrap gap-4">
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="radio" name="applicant_category" value="company" x-model="applicantCategory" class="text-brand focus:ring-brand">
                    Company
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="radio" name="applicant_category" value="individual" x-model="applicantCategory" class="text-brand focus:ring-brand">
                    Individual
                </label>
            </div>
        </div>
        <template x-if="! allowsPerson">
            <input type="hidden" name="applicant_category" value="company">
        </template>
    </x-admin.step>

    <x-admin.step title="Basic info">
        @if ($r)
            <div>
                <p class="text-xs font-semibold text-gray-700 mb-1">Partner code</p>
                <p class="text-sm font-mono text-gray-900">{{ $r->vendor_number }}</p>
            </div>
        @endif
        <x-admin.input name="name" label="Trading / display name" :value="$r?->name" required />

        <div x-show="isCompany" x-cloak class="contents">
            <x-admin.input name="legal_name" label="Legal business name" :value="$r?->legal_name" />
            <x-admin.input name="registration_number" label="BRELA / registration no." :value="$r?->registration_number" />
            <x-admin.input name="tin" label="TIN" :value="$r?->tin" />
        </div>

        <x-admin.select name="status" label="Status" :options="$statuses" :value="$r?->status ?? 'active'" required />
    </x-admin.step>

    <div data-step-gate x-show="needsCoverage" x-cloak>
        <x-admin.step title="Coverage regions">
            <div class="md:col-span-2 space-y-3">
                <p class="text-xs text-gray-500">Select regions, or mark the partner as nationwide for all regions.</p>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="radio" name="coverage_type" value="regions" @checked(old('coverage_type', $r?->coverage_type ?? 'regions') !== 'nationwide') class="text-brand focus:ring-brand">
                    Specific regions
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                    <input type="radio" name="coverage_type" value="nationwide" @checked(old('coverage_type', $r?->coverage_type ?? 'regions') === 'nationwide') class="text-brand focus:ring-brand">
                    Nationwide
                </label>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-64 overflow-y-auto rounded-xl border border-brand/15 p-3">
                    @foreach ($regionOptions ?? [] as $region)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="regions[]" value="{{ $region }}"
                                   @checked(in_array($region, $selectedRegions, true))
                                   class="rounded border-gray-300 text-brand focus:ring-brand">
                            <span>{{ $region }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </x-admin.step>
    </div>

    <x-admin.step title="Contact">
        <x-admin.phone-input name="phone" label="Phone" :value="$r?->phone" />
        <x-admin.input name="email" label="Email" :value="$r?->email" type="email" />
        <div class="md:col-span-2" x-show="! isValuer" x-cloak>
            <x-admin.textarea name="address" label="Address / coverage area" :value="$r?->address" rows="2"
                              placeholder="Office address or service coverage notes" />
        </div>
    </x-admin.step>

    <div data-step-gate x-show="isCompany" x-cloak>
        <x-admin.step title="Business documents">
            <p class="md:col-span-2 text-xs text-gray-500">BRELA, TIN certificate, business licence. PDF or image, max 5MB each.</p>
            @foreach ([
                'doc_brela' => 'BRELA / company registration',
                'doc_tin_certificate' => 'TIN certificate',
                'doc_business_licence' => 'Business licence',
                'doc_other' => 'Other supporting document',
            ] as $input => $label)
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">{{ $label }}</label>
                    <input type="file" name="{{ $input }}" accept=".jpg,.jpeg,.png,.pdf"
                           class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-xl file:border-0 file:bg-brand-gold file:px-3 file:py-2 file:text-xs file:font-semibold file:text-brand">
                </div>
            @endforeach
        </x-admin.step>
    </div>

    <div data-step-gate x-show="isIndividual || isCompany" x-cloak>
        <x-admin.step title="Identity documents">
            <p class="md:col-span-2 text-xs text-gray-500" x-show="isIndividual">National ID for the individual partner.</p>
            <p class="md:col-span-2 text-xs text-gray-500" x-show="isCompany">Optional registrant National ID.</p>
            @foreach ([
                'doc_national_id_front' => 'National ID (front)',
                'doc_national_id_back' => 'National ID (back)',
            ] as $input => $label)
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">{{ $label }}</label>
                    <input type="file" name="{{ $input }}" accept=".jpg,.jpeg,.png,.pdf"
                           class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-xl file:border-0 file:bg-brand-gold file:px-3 file:py-2 file:text-xs file:font-semibold file:text-brand">
                </div>
            @endforeach
            @if ($r)
                @php $docs = $r->documents()->whereNotNull('doc_type')->latest()->get(); @endphp
                @if ($docs->isNotEmpty())
                    <div class="md:col-span-2 space-y-2">
                        <p class="text-xs font-semibold text-gray-700">Uploaded documents</p>
                        @foreach ($docs as $doc)
                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($doc->file_path) }}" target="_blank"
                               class="block text-sm text-brand hover:underline">{{ $doc->label ?: ($doc->doc_type.' · '.$doc->file_path) }}</a>
                        @endforeach
                    </div>
                @endif
            @endif
        </x-admin.step>
    </div>

    <div data-step-gate x-show="isValuer || isServiceRates" x-cloak>
        <x-admin.step title="Rates">
            <div class="md:col-span-2 rounded-xl bg-brand-muted/60 ring-1 ring-brand/15 px-4 py-3 text-sm text-brand space-y-2">
                <p class="font-semibold">Default rates come from Settings</p>
                <p class="text-xs text-brand/80">
                    Platform defaults are in
                    <a href="{{ route('admin.settings.recovery') }}" class="font-semibold underline">Recovery policy</a>.
                    Leave overrides blank to use defaults.
                </p>
                <ul class="text-xs space-y-1 pt-1">
                    @if ($defaultCommission !== null)
                        <li>Default recovery commission: <strong>{{ rtrim(rtrim(number_format((float) $defaultCommission, 2), '0'), '.') }}%</strong></li>
                    @endif
                    <li>Default company markup: <strong>{{ rtrim(rtrim(number_format((float) $defaultMarkup, 2), '0'), '.') }}%</strong></li>
                </ul>
            </div>
            <details class="md:col-span-2 rounded-xl border border-brand/15 bg-white p-4" @if(filled(old('partner_cost', $r?->partner_cost)) || filled(old('markup_percent', $r?->markup_percent)) || filled(old('recovery_commission_percent', $r?->recovery_commission_percent)) || filled(old('recovery_markup_percent', $r?->recovery_markup_percent))) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-gray-800">Optional partner override</summary>
                <p class="mt-2 mb-4 text-xs text-gray-500">Only when this partner needs a negotiated rate different from Settings.</p>
                <div class="grid md:grid-cols-2 gap-4">
                    <x-admin.input name="partner_cost" label="Override base cost (TZS)" type="number" step="0.01" :value="$r?->partner_cost" />
                    <x-admin.input name="markup_percent" label="Override markup (%)" type="number" step="0.01" :value="$r?->markup_percent" />
                    <div x-show="isServiceRates" class="contents">
                        <x-admin.input name="recovery_commission_percent" label="Override recovery commission (%)" type="number" step="0.01" :value="$r?->recovery_commission_percent" />
                        <x-admin.input name="recovery_markup_percent" label="Override company markup (%)" type="number" step="0.01" :value="$r?->recovery_markup_percent" />
                    </div>
                </div>
            </details>
        </x-admin.step>
    </div>

    <div data-step-gate x-show="isSupplier" x-cloak>
        <x-admin.step title="Supplier settings">
            <p class="md:col-span-2 text-xs text-gray-500 mb-2">Deposit markup is controlled under Settings → Asset lending.</p>
            <x-admin.select name="supplier_type" label="Supplier payment mode" :options="config('asset_lending.supplier_types')" :value="$r?->supplier_type ?? config('asset_lending.default_supplier_type')" />
            <p class="md:col-span-2 text-xs text-gray-500">
                <strong>Direct repayment</strong> — supplier receives principal from customer repayments.
                <strong>Full upfront payment</strong> — entire asset value is paid on approval.
            </p>
        </x-admin.step>
    </div>

    <div data-step-gate x-show="isAffiliate" x-cloak>
        <x-admin.step title="Affiliate program">
            <div class="md:col-span-2 rounded-xl bg-brand-muted/60 ring-1 ring-brand/15 px-4 py-3 text-sm text-brand mb-2">
                Defaults from Settings → Affiliates. Overrides below are optional.
            </div>
            <x-admin.input name="affiliate_code" label="Promo / affiliate code" :value="$r?->affiliate_code" placeholder="Auto-generated for affiliates" />
            <x-admin.input name="registration_discount_percent" label="Registration discount (%)" type="number" step="0.01" :value="$r?->registration_discount_percent ?? config('affiliates.default_registration_discount_percent')" />
            <x-admin.input name="application_discount_percent" label="Application discount (%)" type="number" step="0.01" :value="$r?->application_discount_percent ?? config('affiliates.default_application_discount_percent')" />
            <x-admin.input name="affiliate_commission_percent" label="Commission (%)" type="number" step="0.01" :value="$r?->affiliate_commission_percent ?? config('affiliates.default_commission_percent')" />
        </x-admin.step>
    </div>
</div>
