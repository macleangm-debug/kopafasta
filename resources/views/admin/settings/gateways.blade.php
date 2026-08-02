<x-admin.layout title="SMS / Email Gateways" heading="SMS / Email communication" subheading="API credentials, Sender ID, and connection health">
    @include('admin.settings._tabs', ['active' => 'gateways'])
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('sms_health'))
        @php $health = session('sms_health'); @endphp
        <div class="mb-4 rounded-lg px-4 py-3 text-sm ring-1 {{ ($health['ok'] ?? false) ? 'bg-emerald-50 ring-emerald-200 text-emerald-800' : 'bg-rose-50 ring-rose-200 text-rose-800' }}">
            <p class="font-semibold">{{ ($health['ok'] ?? false) ? 'Connection healthy' : 'Connection failed' }}</p>
            <p class="mt-1">{{ $health['message'] ?? '' }}</p>
            @if (! empty($health['provider']))
                <p class="mt-1 text-xs opacity-80">Provider: {{ $health['provider'] }}</p>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.gateways.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">SMS gateway</h3>
                    <p class="text-xs text-gray-500 mt-1">Enter your provider API key, secret, and approved Sender ID. Then test the connection.</p>
                </div>
                <a href="{{ route('admin.settings.gateways.health') }}"
                   class="inline-flex items-center justify-center rounded-lg bg-brand text-white text-xs font-semibold px-3 py-2 hover:bg-brand-light shrink-0">
                    Check SMS connection
                </a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Provider</label>
                    <select name="sms_provider" class="w-full rounded-lg border-gray-300 text-sm">
                        @foreach (['' => 'Log only (dev)', 'beem' => 'Beem Africa', 'africastalking' => "Africa's Talking"] as $val => $label)
                            <option value="{{ $val }}" @selected(($values['sms_provider'] ?? '') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <x-admin.input name="sms_sender_id" label="Sender ID" :value="$values['sms_sender_id'] ?? ''" placeholder="KOPAFASTA" />
                <x-admin.input name="sms_api_key" label="API key / username" :value="$values['sms_api_key'] ?? ''" placeholder="Your API key" />
                <x-admin.input name="sms_api_secret" label="API secret / apiKey" :value="$values['sms_api_secret'] ?? ''" placeholder="Your API secret" />
                <div class="md:col-span-2">
                    <x-admin.input name="sms_endpoint" label="Endpoint URL (optional override)" :value="$values['sms_endpoint'] ?? ''" placeholder="Leave blank for provider default" />
                </div>
                <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2 md:col-span-2">
                    <input type="hidden" name="staff_sms_alerts" value="0">
                    <input type="checkbox" name="staff_sms_alerts" value="1" @checked(! isset($values['staff_sms_alerts']) || ! empty($values['staff_sms_alerts'])) class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                    <span class="text-gray-800">Send SMS alerts to staff on new restructure and top-up requests</span>
                </label>
            </div>
            <p class="mt-4 text-[11px] text-gray-500">
                Tip: keep <strong>Transactional messaging → Force log mode</strong> on until this health check passes, then turn it off for live OTP and repayment SMS.
            </p>
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
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save gateways</button>
        </div>
    </form>
</x-admin.layout>
