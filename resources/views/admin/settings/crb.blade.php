<x-admin.layout title="CRB integration" heading="Credit Bureau (CRB)" subheading="D&amp;B Tanzania Live Request — NIDA verification and underwriting credit checks">
    @include('admin.settings._tabs', ['active' => 'crb'])
@if ($errors->has('crb_test'))<div class="mb-4 rounded-lg bg-rose-50 ring-1 ring-rose-200 px-4 py-3 text-sm text-rose-700">{{ $errors->first('crb_test') }}</div>@endif

    <div class="mb-6 grid md:grid-cols-3 gap-4">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">Configured provider</p>
            <p class="mt-1 text-sm font-bold text-gray-900">{{ $ops['provider'] ?? 'Dun & Bradstreet Tanzania' }}</p>
            <p class="mt-1 text-xs text-gray-500">Driver config: {{ strtoupper($ops['driver_config'] ?? $driver) }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">Mode</p>
            <p class="mt-1 text-lg font-bold {{ ($ops['mode_short'] ?? '') === 'STUB' ? 'text-amber-700' : 'text-emerald-700' }}">
                {{ $ops['mode'] ?? ($usesStub ? 'TEST / CRB STUB' : 'D&B LIVE') }}
            </p>
            @if (! empty($ops['production_stub_blocked']))
                <p class="mt-1 text-xs text-emerald-800">Production blocks stub underwriting evidence.</p>
            @endif
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">Configuration</p>
            <p class="mt-1 text-sm font-medium text-gray-900">Endpoint: {{ ! empty($ops['endpoint_configured']) ? 'Yes' : 'No' }}</p>
            <p class="text-sm font-medium text-gray-900">Credentials: {{ ! empty($ops['credentials_configured']) ? 'Yes' : 'No' }}</p>
            <p class="text-xs text-gray-500 mt-1">Password lives in CRB_PASSWORD env only.</p>
        </div>
    </div>

    <div class="mb-6 grid md:grid-cols-2 gap-4">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">Last successful provider connection</p>
            <p class="mt-1 text-sm font-semibold text-gray-900">{{ ! empty($ops['last_success_at']) ? format_app_datetime($ops['last_success_at'], 'd M Y g:i A') : '—' }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">Last error (sanitized)</p>
            <p class="mt-1 text-sm font-semibold {{ ! empty($ops['last_error']) ? 'text-rose-800' : 'text-gray-900' }}">{{ $ops['last_error'] ?? '—' }}</p>
            @if (! empty($ops['last_checked_at']))
                <p class="text-xs text-gray-500 mt-1">Checked {{ format_app_datetime($ops['last_checked_at'], 'd M Y g:i A') }}</p>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.crb.save') }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-6 mb-6">
        @csrf @method('PUT')

        <p class="text-sm text-gray-600">Configure how Kopafasta connects to the Tanzania Credit Bureau for NIDA identity verification and optional underwriting credit pulls. Store the SOAP password in <code class="text-[11px]">CRB_PASSWORD</code> — it is never saved in the database.</p>
        <p class="text-sm text-amber-900 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2">Stub / sandbox is for local and staging tests only. Production must use live D&amp;B; provider outage must not fall back to fixture data.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                <input type="hidden" name="crb_check_required" value="0">
                <input type="checkbox" name="crb_check_required" value="1" @checked(!empty($values['crb_check_required'])) class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                <span class="text-gray-800">Pull CRB credit report on loan submission (underwriting)</span>
            </label>
            <label class="flex items-center gap-2 text-sm bg-gray-50 ring-1 ring-gray-200 rounded-lg px-3 py-2">
                <input type="hidden" name="crb_sandbox" value="0">
                <input type="checkbox" name="crb_sandbox" value="1" @checked(!empty($values['crb_sandbox'])) class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                <span class="text-gray-800">CRB sandbox / stub mode (no live bureau calls)</span>
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.input name="crb_endpoint" label="CRB SOAP endpoint URL" :value="$values['crb_endpoint'] ?? config('crb.endpoint')" placeholder="https://..." />
            <x-admin.input name="crb_email" label="CRB user email (EmailID)" :value="$values['crb_email'] ?? config('crb.email')" />
            <x-admin.input name="crb_freshness_days" label="CRB report freshness — 90 days (configurable)" type="number" :value="$values['crb_freshness_days'] ?? '90'" required />
            <p class="md:col-span-2 -mt-2 text-xs text-gray-500">Fresh reports within this window are reused automatically — no manual refresh needed on applications.</p>
            <x-admin.input name="crb_cost_per_request" label="Cost per bureau request (TZS)" type="number" step="0.01" :value="$values['crb_cost_per_request'] ?? '0'" />
        </div>

        <div class="flex flex-wrap justify-end gap-3">
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save CRB settings</button>
        </div>
    </form>

    <div class="grid lg:grid-cols-3 gap-4 mb-6">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">Requests this month</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($billingSummary['requests'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $billingSummary['month'] ?? now()->format('F Y') }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">Estimated spend (TZS)</p>
            <p class="mt-1 text-2xl font-bold text-amber-700">{{ format_money($billingSummary['estimated_cost'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-1">At {{ format_money($values['crb_cost_per_request'] ?? 0) }} per live pull</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-xs uppercase tracking-widest text-gray-500">Fresh reports reused</p>
            <p class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format($billingSummary['fresh_reuse_count'] ?? 0) }}</p>
            <p class="text-xs text-gray-500 mt-1">Within freshness window — no new bureau charge</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 mb-6 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h3 class="text-sm font-semibold text-gray-900">Monthly usage (last 6 months)</h3>
            <a href="{{ route('admin.compliance.crb-audit') }}" class="text-sm font-semibold text-brand hover:text-brand-light">Full audit &amp; billing report →</a>
        </div>
        <table class="min-w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wider text-gray-500 border-b border-gray-100">
                <tr>
                    <th class="pb-2 pr-4">Month</th>
                    <th class="pb-2 pr-4">Requests</th>
                    <th class="pb-2">Estimated cost</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($billingHistory as $row)
                    <tr>
                        <td class="py-2.5 pr-4 font-medium text-gray-900">{{ $row['month'] }}</td>
                        <td class="py-2.5 pr-4 text-gray-600">{{ number_format($row['requests']) }}</td>
                        <td class="py-2.5 text-gray-900">{{ format_money($row['estimated_cost']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-1">Test connection</h3>
        <p class="text-xs text-gray-500 mb-4">Runs a sample NIDA lookup using the current driver and sandbox settings.</p>
        <dl class="grid sm:grid-cols-2 gap-3 text-sm mb-4">
            <div>
                <dt class="text-xs text-gray-500">Sample scenario</dt>
                <dd class="font-medium text-gray-900">{{ $sampleLabel }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Sample NIDA</dt>
                <dd class="font-mono text-gray-900">{{ $sampleNida }}</dd>
            </div>
        </dl>
        <form method="POST" action="{{ route('admin.settings.crb.test') }}">
            @csrf
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Run CRB test lookup</button>
        </form>
    </div>
</x-admin.layout>
