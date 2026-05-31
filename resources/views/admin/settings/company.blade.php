<x-admin.layout title="Company Profile" heading="Company Profile" subheading="Legal entity & branding details">
    @include('admin.settings._tabs', ['active' => 'company'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('admin.settings.company.save') }}" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-6">
        @csrf @method('PUT')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.input name="name"            label="Company name"      :value="$values['name'] ?? ''" required />
            <x-admin.input name="legal_name"      label="Legal name"        :value="$values['legal_name'] ?? ''" />
            <x-admin.input name="registration_no" label="Registration no."  :value="$values['registration_no'] ?? ''" />
            <x-admin.input name="tin"             label="TIN"               :value="$values['tin'] ?? ''" />
            <x-admin.input name="bot_licence"     label="BOT licence"       :value="$values['bot_licence'] ?? ''" />
            <x-admin.input name="tier"            label="Tier (1/2/3)"      :value="$values['tier'] ?? ''" />
            <x-admin.input name="email"           label="Contact email"     type="email" :value="$values['email'] ?? ''" />
            <x-admin.input name="phone"           label="Contact phone"     :value="$values['phone'] ?? ''" />
            <x-admin.input name="website"         label="Website"           :value="$values['website'] ?? ''" />
            <x-admin.input name="address"         label="Address"           :value="$values['address'] ?? ''" />
            <x-admin.input name="currency"        label="Default currency (ISO 3)" :value="$values['currency'] ?? 'TZS'" required />
            <x-admin.input name="timezone"        label="Timezone"          :value="$values['timezone'] ?? 'Africa/Dar_es_Salaam'" required />
            <x-admin.input name="fiscal_year_start" label="Fiscal year start (MM-DD)" :value="$values['fiscal_year_start'] ?? '01-01'" />
        </div>

        <div class="border-t border-gray-100 pt-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-1">Contract signature</h3>
            <p class="text-xs text-gray-500 mb-4">Used automatically on loan contracts and offer letters.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="signatory_name" label="Signatory name" :value="$values['signatory_name'] ?? ''" />
                <x-admin.input name="signatory_title" label="Designation / title" :value="$values['signatory_title'] ?? ''" />
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Signature image</label>
                    @if (! empty($values['signature_path']))
                        <img src="{{ asset('storage/'.$values['signature_path']) }}" alt="Company signature" class="h-16 mb-3 object-contain">
                    @endif
                    <input type="file" name="signature_image" accept="image/*" class="block w-full text-sm text-gray-600">
                </div>
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save company profile</button>
        </div>
    </form>
</x-admin.layout>
