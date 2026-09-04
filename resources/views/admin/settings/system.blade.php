<x-admin.layout title="System" heading="System" subheading="Which environment and build you are looking at">
    <div class="max-w-xl space-y-4">
        <dl class="rounded-2xl bg-white ring-1 ring-brand/10 divide-y divide-gray-100">
            <div class="px-5 py-4 flex justify-between gap-4">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Environment</dt>
                <dd class="text-sm font-bold {{ $release['environment'] === 'staging' ? 'text-amber-700' : 'text-gray-900' }}">{{ $release['label'] }}</dd>
            </div>
            <div class="px-5 py-4 flex justify-between gap-4">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Application version</dt>
                <dd class="text-sm font-semibold text-gray-900 font-mono">{{ $release['version'] }}</dd>
            </div>
            <div class="px-5 py-4 flex justify-between gap-4">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Commit</dt>
                <dd class="text-sm font-semibold text-gray-900 font-mono">{{ $release['short_commit'] ?: 'unknown' }}</dd>
            </div>
            <div class="px-5 py-4 flex justify-between gap-4">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Deployed</dt>
                <dd class="text-sm font-semibold text-gray-900">{{ $release['deployed_at_display'] ?: '—' }}</dd>
            </div>
            <div class="px-5 py-4 flex justify-between gap-4">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">APP_URL</dt>
                <dd class="text-sm font-semibold text-gray-900 break-all">{{ $release['app_url'] }}</dd>
            </div>
            <div class="px-5 py-4 flex justify-between gap-4">
                <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">APP_DEBUG</dt>
                <dd class="text-sm font-semibold {{ $release['debug'] ? 'text-rose-700' : 'text-emerald-700' }}">{{ $release['debug'] ? 'true' : 'false' }}</dd>
            </div>
        </dl>
        <p class="text-xs text-gray-500">Git commit is the technical authority. Production must match the Staging commit you approved. Never copy selected PHP files between environments.</p>
    </div>

    @if (!empty($stagingEnabled) && is_array($stagingPayments ?? null))
        <div class="max-w-3xl mt-10 space-y-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Staging Payments</h2>
                <p class="mt-1 text-sm text-gray-600">Test money only. Commercial Settings Hub prices stay the source of truth. This section never appears in production.</p>
            </div>
            @if (session('status'))
                <p class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</p>
            @endif
            <form method="POST" action="{{ route('admin.settings.staging-payments.save') }}" class="space-y-6 rounded-2xl bg-white ring-1 ring-brand/10 p-5">
                @csrf
                @method('PUT')
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block text-sm font-semibold text-gray-800">Payment mode
                        <select name="mode" class="mt-1 w-full rounded-xl ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            <option value="simulator" @selected(($stagingPayments['mode'] ?? '') === 'simulator')>Simulator (default UAT)</option>
                            <option value="psp_sandbox" @selected(($stagingPayments['mode'] ?? '') === 'psp_sandbox')>PayIn sandbox</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-gray-800">Default test fee (TZS)
                        <input type="number" min="0" name="default_test_fee" value="{{ (int) $stagingPayments['default_test_fee'] }}" class="mt-1 w-full rounded-xl ring-1 ring-gray-200 px-3 py-2.5 text-sm tabular-nums">
                    </label>
                </div>
                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="allow_success" value="1" @checked($stagingPayments['allow_success'])> Allow success simulation</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="allow_pending" value="1" @checked($stagingPayments['allow_pending'])> Allow pending</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="allow_failure" value="1" @checked($stagingPayments['allow_failure'])> Allow pending/failure/cancelled</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="allow_reversal" value="1" @checked($stagingPayments['allow_reversal'])> Allow reversal</label>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest font-semibold text-gray-500">Override specific fees</p>
                    <div class="mt-2 grid sm:grid-cols-2 gap-3">
                        @foreach (['application_fee' => 'Individual application fee', 'group_application_fee' => 'Group application fee (per member)', 'asset_backed_application_fee' => 'Asset-backed application fee', 'valuation_fee' => 'Valuation fee', 'plus' => 'Kopafasta Plus', 'membership' => 'Membership', 'partner_membership' => 'Partner/affiliate membership', 'other' => 'Other payable'] as $key => $label)
                            <label class="block text-xs font-semibold text-gray-700">{{ $label }}
                                <input type="number" min="0" name="overrides[{{ $key }}]" value="{{ (int) ($stagingPayments['overrides'][$key] ?? 0) }}" class="mt-1 w-full rounded-xl ring-1 ring-gray-200 px-3 py-2 text-sm tabular-nums">
                            </label>
                        @endforeach
                    </div>
                </div>
                <button type="submit" class="rounded-xl bg-brand text-white text-sm font-bold px-5 py-2.5">Save staging payments</button>
            </form>

            <div class="rounded-2xl bg-white ring-1 ring-gray-200 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-[10px] uppercase tracking-widest text-gray-500">
                        <tr>
                            <th class="text-left px-4 py-3">Setting / product</th>
                            <th class="text-right px-4 py-3">Canonical</th>
                            <th class="text-right px-4 py-3">Staging effective</th>
                            <th class="text-left px-4 py-3">Changed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($stagingPayments['audit'] as $row)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-900">{{ $row['label'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $row['source'] }}</p>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ format_money($row['canonical']) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ format_money($row['staging']) }}</td>
                                <td class="px-4 py-3">{{ $row['changed'] ? 'YES' : 'NO' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-admin.layout>
