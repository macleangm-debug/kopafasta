@php
    $lockedStart = ! empty($isConfigured);
    $values = $values ?? [];
    $gatewayMode = $gatewayMode ?? 'dummy';
    $payinChannels = $payinChannels ?? ['mobile_money'];
    $channelOptions = $channelOptions ?? ['mobile_money' => 'Mobile money', 'bank' => 'Bank transfer'];
    $health = $health ?? [];
    $mobileMoneyThreshold = $mobileMoneyThreshold ?? payment_mobile_money_threshold();
    $defaultWebhookUrl = $defaultWebhookUrl ?? route('webhooks.payin');
    $embedded = $embedded ?? false;
@endphp

<div
    x-data="{ editing: {{ $lockedStart ? 'false' : 'true' }} }"
    class="{{ $embedded ? '' : 'bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6' }} space-y-6"
>
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            @unless ($embedded)
                <p class="text-sm text-gray-600">
                    Docs: <a href="https://docs.payin.co.tz/" target="_blank" rel="noopener" class="font-semibold text-brand hover:underline">docs.payin.co.tz</a>.
                    Set <strong>Gateway mode = Live</strong> for real USSD, then Save &amp; test.
                </p>
            @endunless
            @if ($lockedStart)
                <p class="text-xs text-amber-700 mt-1" x-show="!editing" x-cloak>Settings are locked. Click Edit to change credentials or rails.</p>
            @endif
        </div>
        <div class="flex gap-2">
            <button type="button" x-show="!editing" x-cloak @click="editing = true"
                    class="shrink-0 rounded-xl bg-brand text-white text-xs font-semibold px-4 py-2.5 hover:bg-brand-light">
                Edit settings
            </button>
            <button type="button" x-show="editing && {{ $lockedStart ? 'true' : 'false' }}" x-cloak @click="editing = false"
                    class="shrink-0 rounded-xl ring-1 ring-gray-200 bg-white text-gray-700 text-xs font-semibold px-4 py-2.5 hover:bg-gray-50">
                Cancel
            </button>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.payin.save') }}" class="space-y-6">
        @csrf @method('PUT')
        <fieldset :disabled="!editing" class="space-y-6 disabled:opacity-70">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Payment gateway mode</label>
                <select name="gateway_mode" class="w-full md:w-80 rounded-xl border-gray-200 text-sm">
                    <option value="dummy" @selected($gatewayMode === 'dummy')>Dummy (instant test, no USSD)</option>
                    <option value="live" @selected($gatewayMode === 'live')>Live (PayIn USSD / real rails)</option>
                </select>
            </div>

            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4 space-y-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Supported rails</h3>
                    <p class="text-xs text-gray-500 mt-1">Choose Mobile money and/or Bank transfer for this PSP. Mobile money on = PayIn collections enabled.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    @foreach ($channelOptions as $channelKey => $channelLabel)
                        <label class="inline-flex items-center gap-2 rounded-lg bg-white ring-1 ring-gray-200 px-3 py-2 text-sm">
                            <input type="checkbox" name="channels[]" value="{{ $channelKey }}"
                                   @checked(in_array($channelKey, $payinChannels, true))
                                   class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                            <span>{{ $channelLabel }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4 space-y-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Channel rules (all payment gates)</h3>
                    <p class="text-xs text-gray-500 mt-1">Membership, fees, deposits, repayments — same threshold everywhere.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-admin.money-input
                        name="mobile_money_threshold"
                        label="Mobile money max (TZS)"
                        :value="$mobileMoneyThreshold"
                        :decimals="0"
                        help="0 up to this amount: mobile money. Above: bank only."
                    />
                    <div class="rounded-lg bg-white ring-1 ring-gray-200 px-3 py-2 text-xs text-gray-600">
                        <p><span class="font-semibold text-gray-800">Mobile money:</span> 0 – {{ format_money($mobileMoneyThreshold) }}</p>
                        <p class="mt-1"><span class="font-semibold text-gray-800">Bank transfer:</span> above {{ format_money($mobileMoneyThreshold) }}</p>
                    </div>
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
                <x-admin.input name="api_key" label="API key (X-API-Key)" :value="$values['api_key'] ?? ''" autocomplete="off" />
                <x-admin.input name="api_secret" label="API secret (X-API-Secret)" :value="$values['api_secret'] ?? ''" autocomplete="off" />
                <x-admin.input name="webhook_secret" label="Webhook secret (HMAC)" :value="$values['webhook_secret'] ?? ''" autocomplete="off" />
                <div class="md:col-span-2">
                    <x-admin.input name="default_callback_url" label="Callback URL (optional override)" :value="$values['default_callback_url'] ?? ''" :placeholder="$defaultWebhookUrl" />
                    <p class="mt-1 text-xs text-gray-500">Default: <code class="text-[11px]">{{ $defaultWebhookUrl }}</code></p>
                </div>
            </div>
        </fieldset>

        <div class="flex flex-wrap justify-end gap-3" x-show="editing" x-cloak>
            <button type="submit" name="intent" value="save"
                    class="rounded-xl ring-1 ring-gray-200 bg-white text-gray-800 font-semibold text-sm px-5 py-2.5 hover:bg-gray-50">
                Save settings
            </button>
            <button type="submit" name="intent" value="save_and_test"
                    class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl shadow-sm">
                Save &amp; test connection
            </button>
        </div>
    </form>
</div>
