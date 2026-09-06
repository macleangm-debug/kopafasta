@php
    $values = $values ?? [];
    $ops = $values['operational'] ?? [];
    $mgmt = $values['management'] ?? [];
@endphp

<x-admin.layout title="Notifications" heading="Notifications" subheading="Configure how management digests and operational assignment alerts are delivered">
    @include('admin.settings._tabs', ['active' => 'notifications'])

    <div class="mb-4 rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 px-4 py-3 text-sm text-gray-700">
        Settings configures notification behaviour. The workspace owns the work — CTAs open the exact Screening, Recovery, Finance or partner record.
    </div>

    <form method="POST" action="{{ route('admin.settings.notifications.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <section class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
            <div>
                <h2 class="text-sm font-bold text-brand uppercase tracking-widest">Management digests</h2>
                <p class="text-xs text-gray-500 mt-1">Summarized intelligence for owners/admins — not one alert per case.</p>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="management[enabled]" value="1" @checked(! empty($mgmt['enabled'])) class="rounded border-gray-300 text-brand">
                Enable management digests
            </label>
            <div class="grid sm:grid-cols-2 gap-3 text-sm">
                @foreach ([
                    'applications' => 'Applications needing attention',
                    'collections' => 'Collections / recovery position',
                    'failed_payments' => 'Failed payments',
                    'integration_failures' => 'Integration failures',
                    'partner_exceptions' => 'Partner exceptions',
                    'sla_breaches' => 'SLA breaches',
                ] as $key => $label)
                    <label class="flex items-center gap-2 rounded-lg bg-gray-50 ring-1 ring-gray-200 px-3 py-2">
                        <input type="checkbox" name="management[events][{{ $key }}]" value="1" @checked(! empty($mgmt['events'][$key])) class="rounded border-gray-300 text-brand">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <div class="grid sm:grid-cols-3 gap-3">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="management[channels][in_app]" value="1" @checked(!isset($mgmt['channels']['in_app']) || ! empty($mgmt['channels']['in_app'])) class="rounded border-gray-300 text-brand"> In-app</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="management[channels][email]" value="1" @checked(! empty($mgmt['channels']['email'])) class="rounded border-gray-300 text-brand"> Email</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="management[channels][sms]" value="1" @checked(! empty($mgmt['channels']['sms'])) class="rounded border-gray-300 text-brand"> SMS</label>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Digest cadence</label>
                <select name="management[cadence]" class="w-full md:w-64 rounded-xl border-gray-200 text-sm">
                    <option value="immediate_summary" @selected(($mgmt['cadence'] ?? 'immediate_summary') === 'immediate_summary')>Immediate summary (batched)</option>
                    <option value="hourly" @selected(($mgmt['cadence'] ?? '') === 'hourly')>Hourly digest</option>
                    <option value="daily" @selected(($mgmt['cadence'] ?? '') === 'daily')>Daily digest</option>
                </select>
            </div>
        </section>

        <section class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
            <div>
                <h2 class="text-sm font-bold text-brand uppercase tracking-widest">Operational assignments</h2>
                <p class="text-xs text-gray-500 mt-1">Actionable case alerts for Screening, Credit, Finance, Disbursement, Recovery, valuers and partners.</p>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="operational[enabled]" value="1" @checked(!isset($ops['enabled']) || ! empty($ops['enabled'])) class="rounded border-gray-300 text-brand">
                Enable operational assignment notifications
            </label>
            <div class="grid sm:grid-cols-2 gap-3 text-sm">
                @foreach ([
                    'screening' => 'Screening assignments',
                    'credit' => 'Credit / committee actions',
                    'finance' => 'Finance exceptions',
                    'disbursement' => 'Disbursement tasks',
                    'recovery' => 'Recovery case assignments',
                    'partners' => 'Partner / valuer tasks',
                ] as $key => $label)
                    <label class="flex items-center gap-2 rounded-lg bg-gray-50 ring-1 ring-gray-200 px-3 py-2">
                        <input type="checkbox" name="operational[events][{{ $key }}]" value="1" @checked(!isset($ops['events'][$key]) || ! empty($ops['events'][$key])) class="rounded border-gray-300 text-brand">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <div class="grid sm:grid-cols-3 gap-3">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="operational[channels][in_app]" value="1" @checked(!isset($ops['channels']['in_app']) || ! empty($ops['channels']['in_app'])) class="rounded border-gray-300 text-brand"> In-app</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="operational[channels][email]" value="1" @checked(! empty($ops['channels']['email'])) class="rounded border-gray-300 text-brand"> Email</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="operational[channels][sms]" value="1" @checked(! empty($ops['channels']['sms'])) class="rounded border-gray-300 text-brand"> SMS</label>
            </div>
            <p class="text-xs text-gray-500">Every operational notification should answer: what happened → what needs doing → when due → CTA to the exact record.</p>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="rounded-xl bg-brand-gold text-brand font-bold text-sm px-5 py-2.5">Save notification rules</button>
        </div>
    </form>
</x-admin.layout>
