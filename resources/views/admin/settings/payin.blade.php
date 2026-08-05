<x-admin.layout title="PayIn integration" heading="PayIn payments" subheading="Tanzania mobile money collections &amp; payouts (M-Pesa, Airtel, Tigo, Halo)">
    @include('admin.settings._tabs', ['active' => 'payin'])

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('payin_health'))
        @php $health = session('payin_health'); @endphp
        <div class="mb-4 rounded-lg px-4 py-3 text-sm ring-1 {{ ($health['ok'] ?? false) ? 'bg-emerald-50 ring-emerald-200 text-emerald-800' : 'bg-rose-50 ring-rose-200 text-rose-800' }}">
            <p class="font-semibold">{{ ($health['ok'] ?? false) ? 'Connection healthy' : 'Connection failed' }}</p>
            <p class="mt-1">{{ $health['message'] ?? '' }}</p>
            @if (! empty($health['balance']))
                <p class="mt-2 text-xs font-mono opacity-90">
                    Collection: {{ format_money($health['balance']['collection_balance'] ?? 0) }}
                    · Disbursement: {{ format_money($health['balance']['disbursement_balance'] ?? 0) }}
                </p>
            @endif
        </div>
    @endif

    <div class="mb-6 grid md:grid-cols-3 gap-4">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">PayIn</p>
            <p class="mt-1 text-lg font-bold {{ ! empty($values['enabled']) ? 'text-emerald-700' : 'text-amber-700' }}">
                {{ ! empty($values['enabled']) ? 'Enabled' : 'Disabled' }}
            </p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">Environment</p>
            <p class="mt-1 text-lg font-bold text-gray-900">{{ strtoupper($values['environment'] ?? 'sandbox') }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">Gateway mode</p>
            <p class="mt-1 text-lg font-bold {{ ($gatewayMode ?? 'dummy') === 'live' ? 'text-emerald-700' : 'text-amber-700' }}">
                {{ strtoupper($gatewayMode ?? 'dummy') }}
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.payin.save') }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-6 mb-6">
        @csrf @method('PUT')

        <p class="text-sm text-gray-600">
            Docs: <a href="https://docs.payin.co.tz/" target="_blank" rel="noopener" class="font-semibold text-brand hover:underline">docs.payin.co.tz</a>.
            Use sandbox keys first, set gateway mode to <strong>live</strong>, then test a fee payment — the borrower gets a USSD prompt.
            Manage primary payment partner from the
            <a href="{{ route('admin.settings.integrations') }}" class="font-semibold text-brand hover:underline">Integrations hub</a>.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked(! empty($values['enabled'])) class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                <span class="text-gray-800">Enable PayIn for mobile money</span>
            </label>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Payment gateway mode</label>
                <select name="gateway_mode" class="w-full rounded-xl border-gray-200 text-sm">
                    <option value="dummy" @selected(($gatewayMode ?? 'dummy') === 'dummy')>Dummy (instant test, no USSD)</option>
                    <option value="live" @selected(($gatewayMode ?? 'dummy') === 'live')>Live (PayIn USSD / real rails)</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Environment</label>
                <select name="environment" class="w-full rounded-xl border-gray-200 text-sm">
                    <option value="sandbox" @selected(($values['environment'] ?? 'sandbox') === 'sandbox')>Sandbox</option>
                    <option value="production" @selected(($values['environment'] ?? '') === 'production')>Production</option>
                </select>
            </div>
            <x-admin.input name="api_key" label="API key (X-API-Key)" :value="$values['api_key'] ?? ''" placeholder="pk_live_… or pk_test_…" />
            <x-admin.input name="api_secret" label="API secret (X-API-Secret)" :value="$values['api_secret'] ?? ''" placeholder="sk_live_… or sk_test_…" />
            <x-admin.input name="webhook_secret" label="Webhook secret (HMAC)" :value="$values['webhook_secret'] ?? ''" placeholder="From PayIn dashboard" />
            <div class="md:col-span-2">
                <x-admin.input name="default_callback_url" label="Callback URL (optional override)" :value="$values['default_callback_url'] ?? ''" :placeholder="$defaultWebhookUrl" />
                <p class="mt-1 text-xs text-gray-500">Default webhook endpoint: <code class="text-[11px]">{{ $defaultWebhookUrl }}</code> — register this in the PayIn dashboard.</p>
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-3">
            <a href="{{ route('admin.settings.payin.health') }}"
               class="inline-flex items-center justify-center rounded-xl bg-brand text-white text-xs font-semibold px-4 py-2.5 hover:bg-brand-light">
                Test connection
            </a>
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl shadow-sm">
                Save PayIn settings
            </button>
        </div>
    </form>
</x-admin.layout>
