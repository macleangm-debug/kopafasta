<x-admin.layout title="Company Profile" heading="Company Profile" subheading="Legal entity & branding details">
    @include('admin.settings._tabs', ['active' => 'company'])
<form method="POST" action="{{ route('admin.settings.company.save') }}" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-6">
        @csrf @method('PUT')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.input name="name"            label="Company name"      :value="$values['name'] ?? ''" required />
            <x-admin.input name="legal_name"      label="Legal name"        :value="$values['legal_name'] ?? ''" />
            <x-admin.input name="registration_no" label="Registration no."  :value="$values['registration_no'] ?? ''" />
            <x-admin.input name="tin"             label="TIN"               :value="$values['tin'] ?? ''" />
            <x-admin.input name="bot_licence"     label="BOT licence"       :value="$values['bot_licence'] ?? ''" />
            <x-admin.input name="tier"            label="Tier (1/2/3)"      :value="$values['tier'] ?? ''" />
            <x-admin.input name="email"           label="Primary contact email" type="email" :value="$values['email'] ?? ''" />
            <x-admin.input name="support_email"   label="Support email (optional 2nd)" type="email" :value="$values['support_email'] ?? ''" />
            <x-admin.input name="phone"           label="Hotline / phone 1"     :value="$values['phone'] ?? ''" />
            <x-admin.input name="phone_2"         label="Phone 2 (optional)"    :value="$values['phone_2'] ?? ''" />
            <x-admin.input name="phone_3"         label="Phone 3 (optional)"    :value="$values['phone_3'] ?? ''" />
            <x-admin.input name="whatsapp"        label="WhatsApp (digits, e.g. 2557…)" :value="$values['whatsapp'] ?? ''" />
            <x-admin.input name="hotline_label"   label="Hotline label (e.g. Customer care)" :value="$values['hotline_label'] ?? ''" />
            <x-admin.input name="website"         label="Website"           :value="$values['website'] ?? ''" />
            <x-admin.input name="app_base_url"    label="App base URL"      :value="$values['app_base_url'] ?? ''" placeholder="https://app.kopafasta.com" />
            <x-admin.input name="address"         label="Address"           :value="$values['address'] ?? ''" />
            <x-admin.input name="currency"        label="Default currency (ISO 3)" :value="$values['currency'] ?? 'TZS'" required />
            <x-admin.input name="timezone"        label="Timezone"          :value="$values['timezone'] ?? 'Africa/Dar_es_Salaam'" required />
            <x-admin.input name="fiscal_year_start" label="Fiscal year start (MM-DD)" :value="$values['fiscal_year_start'] ?? '01-01'" />
        </div>

        <p class="text-xs text-gray-500 rounded-lg bg-gray-50 ring-1 ring-gray-200 px-4 py-3">
            Phone 1–3 and emails appear on the public support page and help widget. Leave extra phones blank if unused.
        </p>

        <div class="rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 px-5 py-4 space-y-3">
            <div>
                <p class="text-xs uppercase tracking-widest text-brand font-semibold">Number formatting</p>
                <p class="text-sm text-gray-600 mt-1">All amounts and large numbers display with thousand separators (e.g. 1,500,000). Money inputs accept and show commas automatically.</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Thousands separator</label>
                    <select name="thousands_separator" class="w-full text-sm bg-white border border-brand/15 rounded-xl px-3.5 py-2.5">
                        <option value="," @selected(($values['thousands_separator'] ?? ',') === ',')>Comma (1,500,000)</option>
                        <option value="." @selected(($values['thousands_separator'] ?? ',') === '.')>Dot (1.500.000)</option>
                        <option value=" " @selected(($values['thousands_separator'] ?? ',') === ' ')>Space (1 500 000)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Decimal separator</label>
                    <select name="decimal_separator" class="w-full text-sm bg-white border border-brand/15 rounded-xl px-3.5 py-2.5">
                        <option value="." @selected(($values['decimal_separator'] ?? '.') === '.')>Dot (1,500.50)</option>
                        <option value="," @selected(($values['decimal_separator'] ?? '.') === ',')>Comma (1.500,50)</option>
                    </select>
                </div>
            </div>
            <p class="text-xs text-gray-500">Example: {{ format_money(1500000.5, true, 2) }}</p>
        </div>

        <p class="text-xs text-gray-500 rounded-lg bg-gray-50 ring-1 ring-gray-200 px-4 py-3">
            Contract signatory, company stamp, and legal clauses are managed under
            <a href="{{ route('admin.settings.legal') }}" class="font-semibold text-brand hover:underline">Legal settings</a>.
        </p>
        <div class="flex justify-end">
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save company profile</button>
        </div>
    </form>
</x-admin.layout>
