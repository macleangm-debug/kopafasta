<x-admin.layout title="PayIn integration" heading="PayIn payments" subheading="Tanzania mobile money collections &amp; payouts">
    @include('admin.settings._tabs', ['active' => 'payin'])

    <div class="mb-4">
        <a href="{{ route('admin.settings.integrations.partner', ['partner' => 'payin']) }}" class="text-sm font-semibold text-brand hover:underline">
            ← Open full partner workspace (Configuration + Usage)
        </a>
    </div>

    <div class="mb-6 grid md:grid-cols-3 gap-4">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">Supported rails</p>
            <p class="mt-1 text-sm font-bold text-gray-900">
                @forelse ($payinChannels ?? [] as $ch)
                    {{ $ch === 'mobile_money' ? 'Mobile money' : 'Bank' }}@if (! $loop->last), @endif
                @empty
                    None
                @endforelse
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

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        @include('admin.settings._payin-form', [
            'embedded' => true,
            'values' => $values,
            'gatewayMode' => $gatewayMode,
            'mobileMoneyThreshold' => $mobileMoneyThreshold,
            'defaultWebhookUrl' => $defaultWebhookUrl,
            'payinChannels' => $payinChannels,
            'channelOptions' => $channelOptions,
            'isConfigured' => $isConfigured,
            'health' => $health,
        ])
    </div>
</x-admin.layout>
