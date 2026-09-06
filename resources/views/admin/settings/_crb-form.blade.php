@php
    $values = $values ?? [];
    $driver = $driver ?? config('crb.driver');
    $usesStub = $usesStub ?? false;
    $sampleNida = $sampleNida ?? '19810713-00001-23456-78';
    $sampleLabel = $sampleLabel ?? 'Single hit (verified)';
    $embedded = $embedded ?? false;
    $isConfigured = filled($values['crb_endpoint'] ?? null) || filled($values['crb_email'] ?? null) || filled(env('CRB_PASSWORD'));
    $lockedStart = $isConfigured;
@endphp

<div
    x-data="{ editing: {{ $lockedStart ? 'false' : 'true' }},
        openEdit() { this.$refs.form?.reset(); this.editing = true; },
        cancelEdit() { this.$refs.form?.reset(); this.editing = false; },
    }"
    class="{{ $embedded ? '' : 'bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6' }} space-y-6"
    data-integration-settings="crb"
>
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <p class="text-sm text-gray-600">NIDA verification and underwriting credit checks. SOAP password stays in <code class="text-[11px]">CRB_PASSWORD</code> env — never stored in the database.</p>
            @if ($lockedStart)
                <p class="text-xs text-gray-500 mt-1" x-show="!editing" x-cloak>Configuration is locked. Click Edit settings to make changes.</p>
            @endif
        </div>
        <div class="flex gap-2">
            <button type="button" x-show="!editing" x-cloak @click="openEdit()"
                    class="shrink-0 rounded-xl bg-brand text-white text-xs font-semibold px-4 py-2.5 hover:bg-brand-light">
                Edit settings
            </button>
            <button type="button" x-show="editing && {{ $lockedStart ? 'true' : 'false' }}" x-cloak @click="cancelEdit()"
                    class="shrink-0 rounded-xl ring-1 ring-gray-200 bg-white text-gray-700 text-xs font-semibold px-4 py-2.5 hover:bg-gray-50">
                Cancel
            </button>
        </div>
    </div>

    <div x-show="!editing" x-cloak class="space-y-6">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-5">
            <div>
                <dt class="text-[10px] uppercase tracking-[0.18em] font-semibold text-gray-500">Configured driver</dt>
                <dd class="mt-1.5 text-lg font-bold text-gray-900">{{ strtoupper($driver) }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-[0.18em] font-semibold text-gray-500">Active mode</dt>
                <dd class="mt-1.5 text-lg font-bold {{ $usesStub || ! empty($values['crb_sandbox']) ? 'text-amber-700' : 'text-emerald-700' }}">
                    {{ ($usesStub || ! empty($values['crb_sandbox'])) ? 'Sandbox / stub' : 'Live bureau' }}
                </dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-[0.18em] font-semibold text-gray-500">Pull on loan submission</dt>
                <dd class="mt-1.5 text-base font-semibold text-gray-900">{{ ! empty($values['crb_check_required']) ? 'Yes' : 'No' }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-[0.18em] font-semibold text-gray-500">Report freshness</dt>
                <dd class="mt-1.5 text-base font-semibold text-gray-900">{{ (int) ($values['crb_freshness_days'] ?? 90) }} days</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-[10px] uppercase tracking-[0.18em] font-semibold text-gray-500">SOAP endpoint</dt>
                <dd class="mt-1.5 text-sm font-medium text-gray-900 break-all">{{ $values['crb_endpoint'] ?? config('crb.endpoint') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-[0.18em] font-semibold text-gray-500">User email</dt>
                <dd class="mt-1.5 text-sm font-semibold text-gray-900">{{ $values['crb_email'] ?? config('crb.email') ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-[0.18em] font-semibold text-gray-500">Password</dt>
                <dd class="mt-1.5 text-sm font-semibold text-gray-900">{{ env('CRB_PASSWORD') ? 'Set in CRB_PASSWORD env' : 'Not configured' }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-[0.18em] font-semibold text-gray-500">Legacy cost / request</dt>
                <dd class="mt-1.5 text-base font-semibold tabular-nums text-gray-900">{{ format_money($values['crb_cost_per_request'] ?? 0) }}</dd>
                <p class="mt-1 text-xs text-gray-500">Prefer package pricing under Usage &amp; billing</p>
            </div>
        </dl>

        <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-gray-900">Test connection</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ $sampleLabel }} · NIDA {{ $sampleNida }}</p>
            </div>
            <form method="POST" action="{{ route('admin.settings.crb.test') }}">
                @csrf
                <button type="submit" class="rounded-xl bg-brand text-white text-xs font-semibold px-4 py-2.5 hover:bg-brand-light">Run CRB test lookup</button>
            </form>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.crb.save') }}" class="space-y-6" x-ref="form" x-show="editing" x-cloak
          data-no-draft data-integration-settings-form="crb" autocomplete="off">
        @csrf @method('PUT')
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
            <x-admin.input name="crb_freshness_days" label="CRB report freshness (days)" type="number" :value="$values['crb_freshness_days'] ?? '90'" required />
            <x-admin.input name="crb_cost_per_request" label="Legacy cost per bureau request (TZS)" type="number" step="0.01" :value="$values['crb_cost_per_request'] ?? '0'" />
        </div>

        <div class="flex flex-wrap justify-end gap-3">
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl shadow-sm">Save CRB settings</button>
        </div>
    </form>
</div>
