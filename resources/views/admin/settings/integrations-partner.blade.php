<x-admin.layout
    :title="$partner['label'].' integration'"
    :heading="$partner['label']"
    :subheading="$partner['description'] ?? 'Partner configuration and usage'"
>
    @include('admin.settings._tabs', [
    'active' => 'integrations',
    'showHelp' => false,
])

@php
    $integrationHelpPage = $tab === 'usage'
        ? 'integrations.partner.usage'
        : 'integrations.partner.configuration';
@endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.settings.integrations') }}" class="text-sm font-semibold text-brand hover:underline">← Integrations hub</a>
        <div class="flex flex-wrap items-center gap-2">
            @if (($partner['status'] ?? '') === 'available')
                <form method="POST" action="{{ route('admin.settings.integrations.health') }}">
                    @csrf
                    <input type="hidden" name="partner" value="{{ $partnerKey }}">
                    <button type="submit" class="rounded-xl bg-brand text-white text-xs font-semibold px-3 py-2 hover:bg-brand-light">Check health</button>
                </form>
                <a href="{{ route('admin.settings.integrations') }}#live-test"
                   class="rounded-xl ring-1 ring-brand/20 text-brand text-xs font-semibold px-3 py-2 hover:bg-brand-muted/40">
                    Live test
                </a>
            @endif
            @if (($partner['category'] ?? '') === 'payment' && empty($partner['is_primary']))
                <form method="POST" action="{{ route('admin.settings.integrations.primary') }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="category" value="payment">
                    <input type="hidden" name="partner" value="{{ $partnerKey }}">
                    <button type="submit" class="rounded-xl ring-1 ring-gray-200 bg-white text-xs font-semibold px-3 py-2">Make primary</button>
                </form>
            @endif
        </div>
    </div>

    <div class="mb-6 grid sm:grid-cols-3 gap-4">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">Category</p>
            <p class="mt-1 text-lg font-bold text-gray-900">{{ ucfirst($partner['category'] ?? '—') }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">Health</p>
            <p class="mt-1 text-lg font-bold {{ ! empty($health['unknown']) ? 'text-amber-700' : (! empty($health['ok']) ? 'text-emerald-700' : 'text-rose-700') }}">
                {{ ! empty($health['unknown']) ? 'Not checked' : (! empty($health['ok']) ? 'Healthy' : 'Unhealthy') }}
            </p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">
                {{ ($partner['category'] ?? '') === 'payment' ? 'Rails' : 'Workspace' }}
            </p>
            <p class="mt-1 text-sm font-semibold text-gray-900">
                @if (($partner['category'] ?? '') === 'payment')
                    @forelse (($partner['channels'] ?? []) as $ch)
                        {{ $ch === 'mobile_money' ? 'Mobile money' : 'Bank' }}@if (! $loop->last), @endif
                    @empty
                        —
                    @endforelse
                @else
                    Configuration + Usage
                @endif
            </p>
        </div>
    </div>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-gray-200">
        <div class="flex gap-2">
            <a href="{{ route('admin.settings.integrations.partner', ['partner' => $partnerKey, 'tab' => 'configuration']) }}"
               class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px {{ $tab === 'configuration' ? 'border-brand text-brand' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                Configuration
            </a>
            <a href="{{ route('admin.settings.integrations.partner', ['partner' => $partnerKey, 'tab' => 'usage']) }}"
               class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px {{ $tab === 'usage' ? 'border-brand text-brand' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                Usage &amp; billing
            </a>
        </div>
        <div class="ml-auto shrink-0 pb-2">
            <x-admin.settings-help-drawer :page="$integrationHelpPage" />
        </div>
    </div>

    @if ($tab === 'configuration')
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-6">
            @if ($partnerKey === 'payin' && is_array($payin ?? null))
                @include('admin.settings._payin-form', $payin + ['embedded' => true])
            @elseif ($partnerKey === 'crb' && is_array($crb ?? null))
                @include('admin.settings._crb-form', $crb + ['embedded' => true])
            @elseif (in_array($partnerKey, ['unitxt', 'email_smtp'], true) || ($partner['category'] ?? '') === 'messaging')
                <p class="text-sm text-gray-600">SMS / email gateway credentials are managed on the messaging gateways page. Set per-message rates under Usage &amp; billing if this provider charges you.</p>
                <a href="{{ route('admin.settings.gateways') }}" class="inline-flex rounded-xl bg-brand-gold text-brand text-sm font-bold px-4 py-2.5">Open SMS / Email</a>
            @elseif (($partner['category'] ?? '') === 'payment')
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">Supported rails</h3>
                    <p class="text-xs text-gray-500 mb-3">Select Mobile money and/or Bank transfer for this payment partner.</p>
                    <form method="POST" action="{{ route('admin.settings.integrations.channels') }}" class="flex flex-wrap items-center gap-3">
                        @csrf @method('PUT')
                        <input type="hidden" name="partner" value="{{ $partnerKey }}">
                        @foreach ($channelOptions as $key => $label)
                            <label class="inline-flex items-center gap-2 rounded-lg bg-gray-50 ring-1 ring-gray-200 px-3 py-2 text-sm">
                                <input type="checkbox" name="channels[]" value="{{ $key }}" @checked(in_array($key, $partner['channels'] ?? [], true)) class="size-4 rounded border-gray-300 text-brand">
                                {{ $label }}
                            </label>
                        @endforeach
                        <button type="submit" class="rounded-xl bg-brand text-white text-xs font-semibold px-4 py-2.5">Save rails</button>
                    </form>
                </div>
                <p class="text-sm text-gray-600">API credentials for this partner can be wired when their integration adapter is ready. Rails and Usage &amp; billing are available now.</p>
            @else
                <p class="text-sm text-gray-600">Configure this partner’s credentials when the adapter is ready. Usage &amp; billing is available for recon if they charge you.</p>
            @endif

            @if (! empty($health['guidance']) && empty($health['ok']) && empty($health['unknown']))
                <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-800">
                    <p class="font-semibold">What to check</p>
                    <ul class="mt-2 list-disc pl-4 text-xs space-y-1">
                        @foreach ($health['guidance'] as $tip)
                            <li>{{ $tip }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @else
        <div class="space-y-6">
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($usage['cards'] ?? [] as $card)
                    <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
                        <p class="text-xs uppercase tracking-widest text-gray-500">{{ $card['label'] }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ $card['value'] }}</p>
                        @if (! empty($card['hint']))
                            <p class="text-xs text-gray-500 mt-1">{{ $card['hint'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-gray-900">Pricing (optional)</h3>
                    <p class="text-xs text-gray-500 mt-1">Only fill this in if the partner charges you. Leave zeros / blank when there is no fee — usage is still tracked.</p>
                </div>
                <form method="POST" action="{{ route('admin.settings.integrations.billing', $partnerKey) }}" class="space-y-4">
                    @csrf @method('PUT')
                    @if (($partner['category'] ?? '') === 'payment')
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Collection fee type</label>
                                <select name="collection_fee_type" class="w-full rounded-xl border-gray-200 text-sm">
                                    <option value="percent" @selected(($billing['collection_fee_type'] ?? 'percent') === 'percent')>Percent of amount</option>
                                    <option value="fixed" @selected(($billing['collection_fee_type'] ?? '') === 'fixed')>Fixed (TZS)</option>
                                </select>
                            </div>
                            <x-admin.input name="collection_fee_value" label="Collection fee value" type="number" step="0.01" :value="$billing['collection_fee_value'] ?? '0'" />
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Disbursement fee type</label>
                                <select name="disbursement_fee_type" class="w-full rounded-xl border-gray-200 text-sm">
                                    <option value="percent" @selected(($billing['disbursement_fee_type'] ?? 'percent') === 'percent')>Percent of amount</option>
                                    <option value="fixed" @selected(($billing['disbursement_fee_type'] ?? '') === 'fixed')>Fixed (TZS)</option>
                                </select>
                            </div>
                            <x-admin.input name="disbursement_fee_value" label="Disbursement fee value" type="number" step="0.01" :value="$billing['disbursement_fee_value'] ?? '0'" />
                        </div>
                    @elseif (($partner['category'] ?? '') === 'messaging')
                        <div class="grid md:grid-cols-2 gap-4">
                            <x-admin.input name="sms_fee" label="SMS fee (TZS per message)" type="number" step="0.01" :value="$billing['sms_fee'] ?? '0'" />
                            <x-admin.input name="email_fee" label="Email fee (TZS per message)" type="number" step="0.01" :value="$billing['email_fee'] ?? '0'" />
                        </div>
                    @else
                        <div class="grid md:grid-cols-3 gap-4">
                            <x-admin.input name="included_units" label="Included calls in package" type="number" :value="$billing['included_units'] ?? '0'" />
                            <x-admin.input name="package_price" label="Package price (TZS)" type="number" step="0.01" :value="$billing['package_price'] ?? '0'" />
                            <x-admin.input name="overage_fee" label="Overage per call (TZS)" type="number" step="0.01" :value="$billing['overage_fee'] ?? '0'" />
                        </div>
                    @endif
                    <div class="flex justify-end">
                        <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl">Save pricing</button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 overflow-x-auto">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Monthly usage (recon)</h3>
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wider text-gray-500 border-b border-gray-100">
                        <tr>
                            <th class="pb-2 pr-4">Month</th>
                            @foreach (array_keys(($usage['history'][0]['metrics'] ?? ['—' => '']) ) as $col)
                                <th class="pb-2 pr-4">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($usage['history'] ?? [] as $row)
                            <tr>
                                <td class="py-2.5 pr-4 font-medium text-gray-900">{{ $row['month'] }}</td>
                                @foreach ($row['metrics'] as $val)
                                    <td class="py-2.5 pr-4 text-gray-700">{{ $val }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td class="py-6 text-gray-500" colspan="5">No usage history yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-admin.layout>
