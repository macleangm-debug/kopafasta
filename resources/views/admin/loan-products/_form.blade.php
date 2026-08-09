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
        </x-admin.step>

        <x-admin.step title="Documents" id="documents">
            @include('admin.loan-products._requirements-fields')
        </x-admin.step>
    </div>
</div>
