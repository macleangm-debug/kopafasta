@props(['signatory' => null])

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-admin.input name="name" label="Name" :value="old('name', $signatory?->name)" required />
    <x-admin.input name="position" label="Position" :value="old('position', $signatory?->position)" placeholder="Chief Executive Officer" />
    <x-admin.input name="email" label="Email" type="email" :value="old('email', $signatory?->email)" />
    <div class="flex items-center gap-2 pt-6">
        <input type="checkbox" name="is_active" value="1" id="is_active"
               @checked(old('is_active', $signatory?->is_active ?? true))
               class="rounded border-gray-300 text-amber-600">
        <label for="is_active" class="text-sm text-gray-700">Active</label>
    </div>
</div>

<div class="mt-6 space-y-4">
    <h4 class="text-sm font-semibold text-gray-900">Signature</h4>
    <p class="text-xs text-gray-500">Draw a signature below or upload a PNG/JPG image. Used on loan contracts and offer letters.</p>

    @if ($signatory?->signature_path)
        <img src="{{ $signatory->signaturePublicUrl() }}" alt="Current signature" class="h-20 object-contain border rounded-lg p-2 bg-white">
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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
