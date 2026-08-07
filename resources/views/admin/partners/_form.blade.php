{{-- Shared partner form. Expects $record, $statuses, $categories, $regionOptions --}}
@php
    $r = $record ?? null;
    $creating = (bool) ($creating ?? ($r === null));
    $category = old('category', $r?->category ?? ($defaultCategory ?? 'supplier'));
    $applicantCategory = old('applicant_category', $r?->applicant_category ?? 'company');
    $selectedRegions = old('regions', $r?->regions ?? []);
    $personTypes = ['affiliate', 'valuer'];
    $defaultsService = app(\App\Services\PartnerDefaultsService::class);
    $policy = app(\App\Services\RecoveryPolicyService::class);
    $recoveryType = collect($policy->partnerTypes())->search(
        fn ($meta) => ($meta['vendor_category'] ?? null) === $category
    );
    if ($recoveryType === false) {
        $recoveryType = null;
    }
    $serviceSnapshot = in_array($category, array_keys($defaultsService->categories()), true)
        ? $defaultsService->formSnapshot($category)
        : null;
    $defaultCommission = $recoveryType
        ? $policy->defaultCommissionPercent($recoveryType)
        : null;
    $defaultMarkup = $serviceSnapshot['markup_percent']
        ?? ($recoveryType ? $policy->defaultMarkupPercent($recoveryType) : null)
        ?? 0;
    $serviceRateOverride = old('service_rate_percent', data_get($r?->metadata, 'service_rate_percent'));
@endphp

<div
    x-data="{
        category: @js($category),
        applicantCategory: @js($applicantCategory),
        personTypes: @js($personTypes),
        snapshots: @js(collect($defaultsService->categories())->mapWithKeys(fn ($_, $key) => [$key => $defaultsService->formSnapshot($key)])->all()),
        recoveryDefaults: @js(collect($policy->partnerTypes())->mapWithKeys(function ($meta, $type) use ($policy) {
            return [$meta['vendor_category'] ?? $type => [
                'commission' => $policy->defaultCommissionPercent($type),
                'markup' => $policy->defaultMarkupPercent($type),
                'label' => $meta['label'] ?? $type,
            ]];
        })->all()),
        get needsCoverage() {
            return ['valuer','gps_installer','insurance','debt_collector','towing','auctioneer','legal_partner','supplier'].includes(this.category);
        },
        get isServiceRates() {
            return ['debt_collector','towing','auctioneer','legal_partner','gps_installer','insurance','valuer','call_center'].includes(this.category);
        },
        get isValuer() { return this.category === 'valuer'; },
        get isInsurance() { return this.category === 'insurance'; },
        get isGps() { return this.category === 'gps_installer'; },
        get isSupplier() { return this.category === 'supplier'; },
        get isAffiliate() { return this.category === 'affiliate'; },
        get isDebtCollector() { return this.category === 'debt_collector' || this.category === 'auctioneer'; },
        get allowsPerson() { return this.personTypes.includes(this.category); },
        roles: @js(old('roles', $r ? ($r->roles ?: array_values(array_filter([$r->category]))) : (filled($category) ? [$category] : []))),
        toggleRole(role) {
            if (this.roles.includes(role)) {
                this.roles = this.roles.filter((r) => r !== role);
            } else {
                this.roles = [...this.roles, role];
            }
            if (this.roles.length === 0 && this.category) {
                this.roles = [this.category];
            }
        },
        hasRole(role) { return this.roles.includes(role); },
        get isIndividual() { return this.allowsPerson && this.applicantCategory === 'individual'; },
        get isCompany() { return ! this.allowsPerson || this.applicantCategory === 'company'; },
        get snapshot() { return this.snapshots[this.category] || null; },
        get recovery() { return this.recoveryDefaults[this.category] || null; },
    }"
    x-init="
        $watch('category', (value) => {
            if (value === 'debt_collector' && ! this.roles.includes('debt_collector')) {
                this.roles = ['debt_collector', ...this.roles.filter((r) => r === 'auctioneer')];
            }
            if (value === 'auctioneer' && ! this.roles.includes('auctioneer')) {
                this.roles = ['auctioneer', ...this.roles.filter((r) => r === 'debt_collector')];
            }
            if (! ['debt_collector', 'auctioneer'].includes(value)) {
                this.roles = value ? [value] : [];
            }
            $nextTick(() => window.dispatchEvent(new CustomEvent('admin-wizard-rebuild')));
        });
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

    <div data-step-gate x-show="isDebtCollector" x-cloak>
        <x-admin.step title="Service capabilities">
            <div class="md:col-span-2 space-y-3">
                <p class="text-xs text-gray-500">
                    Tick what this partner can do. When both are selected, a repossessed asset can stay with the same partner for auctioning.
                </p>
                <template x-for="role in roles" :key="'hidden-'+role">
                    <input type="hidden" name="roles[]" :value="role">
                </template>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="flex items-start gap-3 rounded-xl border border-brand/15 bg-white px-4 py-3 cursor-pointer">
                        <input type="checkbox" class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand"
                               :checked="hasRole('debt_collector')"
                               @change="toggleRole('debt_collector')">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">Repossession / field collection</span>
                            <span class="block text-xs text-gray-500 mt-0.5">Visits, collateral checks, and completing repossession.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border border-brand/15 bg-white px-4 py-3 cursor-pointer">
                        <input type="checkbox" class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand"
                               :checked="hasRole('auctioneer')"
                               @change="toggleRole('auctioneer')">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">Auctioning</span>
                            <span class="block text-xs text-gray-500 mt-0.5">List and sell repossessed assets after the hold period.</span>
                        </span>
                    </label>
                </div>
            </div>
        </x-admin.step>
    </div>

    <x-admin.step title="Basic info">
        @if ($r)
            <div>
                <p class="text-xs font-semibold text-gray-700 mb-1">Partner code</p>
                <p class="text-sm font-mono text-gray-900">{{ $r->vendor_number }}</p>
            </div>
        @endif
        <x-admin.input name="name" label="Trading / company name" :value="$r?->name" required
                       help="Company or trading name shown on the portal shell." />

        <div x-show="isCompany" x-cloak class="contents">
            <x-admin.input name="legal_name" label="Legal business name" :value="$r?->legal_name" />
            <x-admin.input name="registration_number" label="BRELA / registration no." :value="$r?->registration_number" />
            <x-admin.input name="tin" label="TIN" :value="$r?->tin" />
        </div>

        @if ($creating)
            <input type="hidden" name="status" value="{{ old('status', 'inactive') }}">
            <div class="md:col-span-2 rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 px-4 py-3 text-xs text-brand">
                Portal status is chosen on the final confirmation step (invite, activate now, or save as draft).
            </div>
        @else
            <x-admin.select name="status" label="Status" :options="$statuses" :value="$r?->status ?? 'active'" required />
        @endif
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

    <x-admin.step title="Contact person">
        @php
            $residenceMeta = is_array($r?->metadata['residence'] ?? null) ? $r->metadata['residence'] : [];
            $contactPerson = old('contact_person_name', data_get($r?->metadata, 'contact_person.name'));
            $nationalId = old('national_id', data_get($r?->metadata, 'identity.national_id'));
        @endphp
        <div class="md:col-span-2" x-show="isCompany" x-cloak>
            <x-admin.input name="contact_person_name" label="Contact person full name" :value="$contactPerson"
                           help="Person who signs in / handles jobs — not the company trading name." />
        </div>
        <x-admin.phone-input name="phone" label="Phone" :value="$r?->phone" :required="$creating" />
        <x-admin.input name="email" label="Email" :value="$r?->email" type="email" />
        <x-admin.input name="national_id" label="NIDA number" :value="$nationalId" help="20-digit National ID for the contact person / individual." />
        @if ($creating)
            <p class="md:col-span-2 text-xs text-gray-500 -mt-2">Phone is required so the partner can activate and sign in to the portal.</p>
        @endif
        <div class="md:col-span-2 space-y-2">
            <p class="text-xs font-semibold text-gray-700">Address (region / district)</p>
            <p class="text-xs text-gray-500">Same structured address used on the partner portal Address tab.</p>
            <x-site.address-fields
                prefix="address"
                :region="old('address_region', $residenceMeta['region'] ?? '')"
                :district="old('address_district', $residenceMeta['district'] ?? '')"
                :ward="old('address_ward', $residenceMeta['ward'] ?? '')"
                :street="old('address_street', $residenceMeta['street'] ?? '')"
                :required="false"
            />
        </div>
    </x-admin.step>

    <x-admin.step title="Payout account">
        @php $payout = is_array($r?->metadata['payout_account'] ?? null) ? $r->metadata['payout_account'] : []; @endphp
        <div class="md:col-span-2" x-data="{ payoutType: @js(old('payout_type', $payout['type'] ?? 'mobile_money')) }">
            <p class="text-xs text-gray-500 mb-3">Where approved partner payouts are sent. Partners can also update this under Profile → Payment.</p>
            <div class="flex flex-wrap gap-4 mb-4 text-sm">
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="payout_type" value="mobile_money" x-model="payoutType" class="text-brand focus:ring-brand">
                    Mobile money
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="payout_type" value="bank" x-model="payoutType" class="text-brand focus:ring-brand">
                    Bank
                </label>
            </div>
            <div class="grid md:grid-cols-2 gap-4">
                <x-admin.input name="payout_account_name" label="Account name" :value="old('payout_account_name', $payout['account_name'] ?? '')" />
                <div x-show="payoutType === 'mobile_money'" class="contents">
                    <x-admin.input name="payout_mobile_provider" label="Provider (M-Pesa / Tigo / Airtel)" :value="old('payout_mobile_provider', $payout['mobile_provider'] ?? '')" />
                    <x-admin.input name="payout_mobile_number" label="Mobile money number" :value="old('payout_mobile_number', $payout['mobile_number'] ?? '')" />
                </div>
                <div x-show="payoutType === 'bank'" class="contents">
                    <x-admin.input name="payout_bank_name" label="Bank name" :value="old('payout_bank_name', $payout['bank_name'] ?? '')" />
                    <x-admin.input name="payout_account_number" label="Account number" :value="old('payout_account_number', $payout['account_number'] ?? '')" />
                </div>
            </div>
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

    <div data-step-gate x-show="isServiceRates" x-cloak>
        <x-admin.step title="Rates">
            <div class="md:col-span-2 rounded-xl bg-brand-muted/60 ring-1 ring-brand/15 px-4 py-3 text-sm text-brand space-y-2">
                <p class="font-semibold">Default rates from Settings → Recovery policy</p>
                <p class="text-xs text-brand/80">
                    Platform defaults are in
                    <a href="{{ route('admin.settings.recovery') }}" class="font-semibold underline">Service partner default rates</a>.
                    Leave overrides blank to use those defaults.
                </p>
                <ul class="text-xs space-y-1 pt-1" x-show="snapshot">
                    <template x-for="line in (snapshot?.lines || [])" :key="line">
                        <li x-text="line"></li>
                    </template>
                </ul>
                <ul class="text-xs space-y-1 pt-1" x-show="!snapshot && recovery">
                    <li>Default recovery commission: <strong x-text="(recovery?.commission ?? 0) + '%'"></strong></li>
                    <li>Default company markup: <strong x-text="(recovery?.markup ?? 0) + '%'"></strong></li>
                </ul>
            </div>
            <details class="md:col-span-2 rounded-xl border border-brand/15 bg-white p-4" @if(filled($serviceRateOverride) || filled(old('partner_cost', $r?->partner_cost)) || filled(old('markup_percent', $r?->markup_percent)) || filled(old('recovery_commission_percent', $r?->recovery_commission_percent)) || filled(old('recovery_markup_percent', $r?->recovery_markup_percent))) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-gray-800">Optional partner override</summary>
                <p class="mt-2 mb-4 text-xs text-gray-500">Only when this partner needs a negotiated rate different from Settings.</p>
                <div class="grid md:grid-cols-2 gap-4">
                    <div x-show="isInsurance" x-cloak>
                        <x-admin.input name="service_rate_percent" label="Override cover rate (% of insured value)" type="number" step="0.01" :value="$serviceRateOverride" help="Leave blank to use the platform insurance rate." />
                    </div>
                    <div x-show="!isInsurance" x-cloak>
                        <x-admin.input name="partner_cost" label="Override base price (TZS)" type="number" step="0.01" :value="$r?->partner_cost" help="Valuation fee, GPS device cost, or negotiated base." />
                    </div>
                    <x-admin.input name="markup_percent" label="Override markup (%)" type="number" step="0.01" :value="$r?->markup_percent" help="Leave blank to use the platform default (or none)." />
                    <div x-show="recovery || isGps" class="contents">
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
