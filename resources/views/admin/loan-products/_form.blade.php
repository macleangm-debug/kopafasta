{{-- Shared Loan Product form. Expects $record --}}
@php
    $r = $record ?? null;
    $cloneOptions = ['' => '— Start blank —'];
    foreach (($cloneSources ?? collect()) as $p) {
        $cloneOptions[(string) $p->id] = $p->name.' ('.$p->code.')';
    }
@endphp

<div x-data="{ cloneFrom: @js(old('clone_from_id', '')) }" class="space-y-6">
    <x-admin.step title="Basics">
        @unless ($r)
            <div class="md:col-span-2 rounded-xl bg-amber-50 ring-1 ring-amber-100 p-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Same as (copy an existing product)</label>
                <select name="clone_from_id" x-model="cloneFrom"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
                    @foreach ($cloneOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-gray-600">
                    Pick a template. Then only enter the <strong>English name</strong>, <strong>Swahili name</strong>, and optional <strong>image</strong>. Everything else (pricing, tiers, documents, flags) is copied. Edit details after create if needed.
                </p>
            </div>
        @endunless

        <div class="md:col-span-2" x-show="!cloneFrom" x-cloak>
            <x-admin.input  name="code"                label="Product code"        :value="$r?->code"     :required="! $r" placeholder="e.g. BIZ-30" />
        </div>
        <x-admin.input  name="name"                label="Name (English)"    :value="$r?->name"     required />
        <x-admin.input  name="name_sw"             label="Name (Swahili)"    :value="$r?->name_sw"  placeholder="Optional — shown when locale is Swahili" />

        <div class="md:col-span-2" x-show="!cloneFrom" x-cloak>
            <x-admin.select name="category"            label="Category"
                            :options="[
                                'business_loan' => 'Business loan',
                                'salary_loan'   => 'Salary loan',
                                'agriculture'   => 'Agriculture',
                                'asset_finance' => 'Asset finance',
                                'emergency'     => 'Emergency',
                            ]"
                            :value="$r?->category" />
            <x-admin.select name="status"              label="Visibility"          :options="['active' => 'Active', 'coming_soon' => 'Coming soon', 'inactive' => 'Inactive']" :value="$r?->status ?? 'active'" required />
            <x-admin.textarea name="description" label="Long description (English)" :value="$r?->description" rows="3" />
            <div class="grid sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <x-admin.textarea name="short_description" label="Short description (English)" :value="$r?->short_description" rows="2" maxlength="90" />
                    <p class="mt-1 text-[11px] text-gray-500">Max 90 characters — fits about 2 lines on product cards.</p>
                </div>
                <div>
                    <x-admin.textarea name="short_description_sw" label="Short description (Swahili)" :value="$r?->short_description_sw" rows="2" maxlength="90" />
                    <p class="mt-1 text-[11px] text-gray-500">Max 90 characters — shown when locale is Kiswahili.</p>
                </div>
            </div>
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Product image</label>
            <p class="text-xs text-gray-500 mb-2">
                @if ($r)
                    Optional upload. If empty, the themed illustration for this product code is used (same artwork borrowers already see).
                @else
                    Optional. Leave blank to reuse the template product’s image / themed illustration.
                @endif
            </p>
            @if ($r?->image_path)
                <img src="{{ asset('storage/'.$r->image_path) }}" alt="" class="mb-3 h-28 w-44 object-cover rounded-xl ring-1 ring-gray-200">
            @endif
            <input type="file" name="image" accept="image/*"
                   class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-amber-800 hover:file:bg-amber-100">
        </div>
    </x-admin.step>

    <div x-show="!cloneFrom" x-cloak>
        @include('admin.loan-products._pricing-fields')
        @include('admin.loan-products._rate-tiers-fields', ['record' => $r, 'rateTiers' => $rateTiers ?? null])
        @include('admin.loan-products._post-approval-fees-fields')
        <x-admin.number-format-script />
        @include('admin.loan-products._document-templates-fields')

        <x-admin.step title="Requirements">
            <x-admin.select name="requires_collateral" label="Requires collateral" :options="['1' => 'Yes', '0' => 'No']" :value="(string) ($r?->requires_collateral ?? '0')" />
            <x-admin.select name="requires_guarantor"  label="Requires guarantor"  :options="['1' => 'Yes', '0' => 'No']" :value="(string) ($r?->requires_guarantor ?? '0')" />
            @if ($r?->requires_guarantor)
                <label class="flex items-start gap-3 text-sm text-gray-800 md:col-span-2">
                    <input type="hidden" name="guarantor_gate_1_required" value="0">
                    <input type="checkbox" name="guarantor_gate_1_required" value="1"
                           @checked((bool) old('guarantor_gate_1_required', $r?->guarantor_gate_1_required))
                           class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand/30">
                    <span>
                        <span class="font-semibold">Require Gate 1 affordability for the guarantor</span>
                        <span class="block text-xs text-gray-500 mt-0.5">Declared-income check on the guarantor. Off by default — only enable when this product genuinely needs guarantor financial assessment.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 text-sm text-gray-800 md:col-span-2">
                    <input type="hidden" name="guarantor_gate_2_required" value="0">
                    <input type="checkbox" name="guarantor_gate_2_required" value="1"
                           @checked((bool) old('guarantor_gate_2_required', $r?->guarantor_gate_2_required))
                           class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand/30">
                    <span>
                        <span class="font-semibold">Require Gate 2 verified capacity for the guarantor</span>
                        <span class="block text-xs text-gray-500 mt-0.5">Statement-backed guarantor capacity. Off by default.</span>
                    </span>
                </label>
            @endif
            <div class="md:col-span-2">
                <p class="text-sm font-medium text-gray-700 mb-2">Eligible grades</p>
                <p class="text-xs text-gray-500 mb-2">Leave all unchecked to keep the product open to every grade.</p>
                <div class="flex flex-wrap gap-4 text-sm">
                    @foreach (['bronze','silver','gold','platinum'] as $grade)
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="eligible_grades[]" value="{{ $grade }}"
                                   @checked(in_array($grade, old('eligible_grades', $r?->eligible_grades ?? []), true))
                                   class="rounded border-gray-300 text-brand focus:ring-brand/30">
                            <span class="capitalize">{{ $grade }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            @php
                $isGroupProduct = $r && (
                    str_starts_with(strtoupper((string) $r->code), 'GL')
                    || ($r->category ?? '') === 'group'
                );
                $constitutionReq = collect($requirements ?? ($r?->requirements ?? collect()))
                    ->first(fn ($row) => (is_array($row) ? ($row['name'] ?? '') : ($row->name ?? '')) === 'Group constitution');
                $rosterReq = collect($requirements ?? ($r?->requirements ?? collect()))
                    ->first(fn ($row) => (is_array($row) ? ($row['name'] ?? '') : ($row->name ?? '')) === 'Group member roster');
                $constitutionRequiredDefault = is_array($constitutionReq)
                    ? (bool) ($constitutionReq['is_required'] ?? false)
                    : (bool) ($constitutionReq?->is_required ?? false);
                $rosterRequiredDefault = is_array($rosterReq)
                    ? (bool) ($rosterReq['is_required'] ?? false)
                    : (bool) ($rosterReq?->is_required ?? false);
            @endphp
            @if ($isGroupProduct)
                <div class="md:col-span-2 rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-4 space-y-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Group loan evidence (optional)</h3>
                        <p class="text-xs text-gray-600 mt-1">
                            Group members are already the roster. Paper constitution and a printed member list are not compulsory and do not block screening.
                        </p>
                    </div>
                    <label class="flex items-start gap-3 text-sm text-gray-800 cursor-pointer">
                        <input type="hidden" name="require_group_constitution" value="0">
                        <input type="checkbox" name="require_group_constitution" value="1"
                               @checked((string) old('require_group_constitution', $constitutionRequiredDefault ? '1' : '0') === '1')
                               class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand/30">
                        <span>
                            <span class="font-semibold">Require group constitution</span>
                            <span class="block text-xs text-gray-500 mt-0.5">Group bylaws / constitution document upload on the application.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 text-sm text-gray-800 cursor-pointer">
                        <input type="hidden" name="require_group_member_roster" value="0">
                        <input type="checkbox" name="require_group_member_roster" value="1"
                               @checked((string) old('require_group_member_roster', $rosterRequiredDefault ? '1' : '0') === '1')
                               class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand/30">
                        <span>
                            <span class="font-semibold">Require group member roster document</span>
                            <span class="block text-xs text-gray-500 mt-0.5">Printed/signed list with IDs — in addition to digital group members.</span>
                        </span>
                    </label>
                </div>
            @endif
        </x-admin.step>

        <x-admin.step title="Documents" id="documents">
            @include('admin.loan-products._requirements-fields')
        </x-admin.step>

        <x-admin.step title="SEO (optional overrides)">
            <p class="md:col-span-2 text-sm text-gray-600">
                Leave blank to generate a title from the product name, category, and brand, and a description from the public product copy.
                These fields never change how the loan engine decides eligibility.
            </p>
            <x-admin.input name="seo_title" label="SEO title (English)" :value="$r?->seo_title" />
            <x-admin.input name="seo_title_sw" label="SEO title (Kiswahili)" :value="$r?->seo_title_sw" />
            <div class="md:col-span-2 grid sm:grid-cols-2 gap-4">
                <x-admin.textarea name="seo_description" label="Meta description (English)" :value="$r?->seo_description" rows="2" maxlength="320" />
                <x-admin.textarea name="seo_description_sw" label="Meta description (Kiswahili)" :value="$r?->seo_description_sw" rows="2" maxlength="320" />
            </div>
            <label class="md:col-span-2 inline-flex items-center gap-2 text-sm">
                <input type="hidden" name="seo_indexable" value="0">
                <input type="checkbox" name="seo_indexable" value="1"
                       @checked((bool) old('seo_indexable', $r?->seo_indexable ?? true))
                       class="rounded border-gray-300 text-brand focus:ring-brand/30">
                Indexable in search engines when the product is public
            </label>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Social image</label>
                @if ($r?->seo_image_path)
                    <img src="{{ asset('storage/'.$r->seo_image_path) }}" alt="" class="mb-3 h-24 w-40 object-cover rounded-xl ring-1 ring-gray-200">
                @endif
                <input type="file" name="seo_image" accept="image/*"
                       class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-amber-800">
            </div>
        </x-admin.step>
    </div>
</div>
