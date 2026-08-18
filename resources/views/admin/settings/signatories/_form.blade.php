@props(['signatory' => null])

@php
    $signatoryTypes = [
        'ceo'              => 'Chief Executive Officer',
        'finance_manager'  => 'Finance manager',
        'company'          => 'Company signatory (legacy)',
        'legal_advocate'   => 'Legal advocate (legacy — not used on contracts)',
    ];
    $selectedType = old('signatory_type', $signatory?->signatory_type ?? 'ceo');
@endphp

<div x-data="{ signatoryType: @js($selectedType), replaceSignature: @js(! $signatory?->signature_path), removeSignature: false, removeStamp: false }">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-admin.select name="signatory_type" label="Signatory type" :options="$signatoryTypes" :value="$selectedType" x-model="signatoryType" required />
        <x-admin.input name="name" label="Full name" :value="old('name', $signatory?->name)" required />
        <x-admin.input name="position" label="Position" :value="old('position', $signatory?->position)" placeholder="Chief Executive Officer" />
        <x-admin.input name="email" label="Email" type="email" :value="old('email', $signatory?->email)" />
        <div class="flex items-center gap-2 pt-6">
            <input type="checkbox" name="is_active" value="1" id="is_active"
                   @checked(old('is_active', $signatory?->is_active ?? true))
                   class="rounded border-gray-300 text-brand">
            <label for="is_active" class="text-sm text-gray-700">Active</label>
        </div>
    </div>

    <div class="mt-6 space-y-4">
        <h4 class="text-sm font-semibold text-gray-900">Signature</h4>
        <p class="text-xs text-gray-500">Draw a signature below or upload a PNG/JPG image. Used on loan contracts and offer letters.</p>

        @if ($signatory?->signature_path)
            <div class="space-y-3" x-show="! replaceSignature && ! removeSignature">
                <img src="{{ $signatory->signaturePublicUrl() }}" alt="Current signature" class="h-20 object-contain border rounded-lg p-2 bg-white">
                <div class="flex flex-wrap gap-3">
                    <button type="button" @click="replaceSignature = true" class="text-sm font-semibold text-brand hover:text-brand-light">Replace signature</button>
                    <button type="button" @click="removeSignature = true; replaceSignature = false" class="text-sm font-semibold text-red-700 hover:text-red-900">Remove signature</button>
                </div>
            </div>
            <input type="hidden" name="remove_signature" value="0" x-bind:value="removeSignature ? '1' : '0'">
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" x-show="@js(! $signatory?->signature_path) || replaceSignature" x-cloak>
            <div>
                <p class="text-xs font-medium text-gray-600 mb-2">Draw signature</p>
                <x-site.signature-pad
                    name="signature_data"
                    signer-name="_signer_name_unused"
                    :default-name="old('name', $signatory?->name ?? '')"
                    :readonly-name="true"
                    :include-in-form="true"
                    :initial-data-url="''"
                />
            </div>
            <div>
                <p class="text-xs font-medium text-gray-600 mb-2">Or upload image</p>
                <input type="file" name="signature_image" accept="image/png,image/jpeg,image/webp"
                       class="block w-full text-sm text-gray-600">
                <p class="text-xs text-gray-500 mt-1">Transparent PNG recommended.</p>
            </div>
        </div>
    </div>

    <div class="mt-6 space-y-4" x-show="signatoryType === 'legal_advocate'" x-cloak>
        <h4 class="text-sm font-semibold text-gray-900">Advocate stamp</h4>
        <p class="text-xs text-gray-500">Required for legal advocates. This stamp is independent from the company stamp.</p>

        @if ($signatory?->stamp_path)
            <div class="space-y-3" x-show="! removeStamp">
                <img src="{{ $signatory->stampPublicUrl() }}" alt="Current advocate stamp" class="h-24 object-contain border rounded-lg p-2 bg-white">
                <button type="button" @click="removeStamp = true" class="text-sm font-semibold text-red-700 hover:text-red-900">Remove stamp</button>
            </div>
            <input type="hidden" name="remove_stamp" value="0" x-bind:value="removeStamp ? '1' : '0'">
        @endif

        <input type="file" name="stamp_image" accept="image/png,image/jpeg,image/webp"
               class="block w-full max-w-md text-sm text-gray-600">
    </div>
</div>
