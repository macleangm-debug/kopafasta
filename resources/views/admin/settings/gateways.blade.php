<x-admin.layout title="SMS / Email Gateways" heading="SMS / Email Gateways" subheading="Outbound messaging providers">
    @include('admin.settings._tabs', ['active' => 'gateways'])
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('admin.settings.gateways.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">SMS gateway</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="sms_provider"  label="Provider (e.g. africastalking)" :value="$values['sms_provider'] ?? ''" />
                <x-admin.input name="sms_sender_id" label="Sender ID"  :value="$values['sms_sender_id'] ?? ''" />
                <x-admin.input name="sms_api_key"    label="API key"    :value="$values['sms_api_key'] ?? ''" />
                <x-admin.input name="sms_api_secret" label="API secret" :value="$values['sms_api_secret'] ?? ''" />
                <div class="md:col-span-2"><x-admin.input name="sms_endpoint" label="Endpoint URL" :value="$values['sms_endpoint'] ?? ''" /></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Email gateway</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-admin.input name="email_provider"      label="Provider (smtp/ses/mailgun)"  :value="$values['email_provider'] ?? 'smtp'" />
                <x-admin.input name="email_from_address"  label="From address" type="email" :value="$values['email_from_address'] ?? ''" />
                <x-admin.input name="email_from_name"     label="From name"   :value="$values['email_from_name'] ?? ''" />
                <x-admin.input name="email_encryption"    label="Encryption (tls/ssl)" :value="$values['email_encryption'] ?? 'tls'" />
                <x-admin.input name="email_smtp_host"     label="SMTP host"   :value="$values['email_smtp_host'] ?? ''" />
                <x-admin.input name="email_smtp_port"     label="SMTP port" type="number" :value="$values['email_smtp_port'] ?? '587'" />
                <x-admin.input name="email_smtp_user"     label="SMTP username" :value="$values['email_smtp_user'] ?? ''" />
                <x-admin.input name="email_smtp_pass"     label="SMTP password" :value="$values['email_smtp_pass'] ?? ''" />
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save gateways</button>
        </div>
    </form>
</x-admin.layout>
