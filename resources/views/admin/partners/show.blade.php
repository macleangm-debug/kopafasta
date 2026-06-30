<x-admin.show-page
    :title="$record->name"
    :heading="$record->name"
    :subheading="$record->vendor_number"
    :backUrl="route('admin.partners.all')"
    :editUrl="route('admin.partners.edit', $record)"
    :fields="array_filter([
        'Partner #'  => $record->vendor_number,
        'Name'      => $record->name,
        'Category'  => ucfirst(str_replace('_', ' ', $record->category)),
        'Status'    => ucfirst($record->status ?? ''),
        'Phone'     => $record->phone,
        'Email'     => $record->email,
        'Deposit markup %' => $record->deposit_markup_percent,
        'Affiliate code' => $record->affiliate_code,
        'Registration discount %' => $record->registration_discount_percent,
        'Application discount %' => $record->application_discount_percent,
        'Commission %' => $record->affiliate_commission_percent,
        'Recovery commission %' => $record->recovery_commission_percent,
        'Recovery markup %' => $record->recovery_markup_percent,
        'Address'   => ['value' => $record->address, 'wide' => true],
        'Created'   => $record->created_at?->format('Y-m-d H:i'),
    ])" />

@if ($affiliateStats ?? null)
    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Affiliate performance</h3>
        <div class="grid sm:grid-cols-3 gap-4 text-sm">
            <div><span class="text-gray-500">Clicks</span><p class="text-xl font-bold">{{ format_number($affiliateStats['clicks']) }}</p></div>
            <div><span class="text-gray-500">Registrations</span><p class="text-xl font-bold">{{ format_number($affiliateStats['registrations']) }}</p></div>
            <div><span class="text-gray-500">Applications</span><p class="text-xl font-bold">{{ format_number($affiliateStats['applications']) }}</p></div>
        </div>
        @if ($record->affiliate_code)
            <p class="mt-4 text-xs text-gray-500">Link: {{ app(\App\Services\AffiliateService::class)->affiliateLink($record) }}</p>
        @endif
    </div>

    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Affiliate lifecycle</h3>
        @php $lifecycle = app(\App\Services\AffiliateLifecycleService::class); @endphp
        <p class="text-sm text-gray-600 mb-2">
            Status:
            <span class="font-semibold">{{ $lifecycle->label($lifecycle->statusFor($record)) }}</span>
        </p>
        @if ($record->affiliate_leaderboard_rank)
            <p class="text-sm text-gray-600 mb-2">Leaderboard rank: <span class="font-semibold">#{{ $record->affiliate_leaderboard_rank }}</span></p>
        @endif
        @if ($record->affiliate_evaluation_snapshot)
            @php $snap = $record->affiliate_evaluation_snapshot; @endphp
            <div class="grid sm:grid-cols-3 gap-3 text-sm mb-4">
                <div><span class="text-gray-500">KPI</span><p class="font-bold">{{ number_format((float) ($snap['kpi_score'] ?? 0), 1) }}</p></div>
                <div><span class="text-gray-500">Risk</span><p class="font-bold">{{ number_format((float) ($snap['risk_score'] ?? 0), 1) }}</p></div>
                <div><span class="text-gray-500">Fraud</span><p class="font-bold">{{ number_format((float) ($snap['fraud_score'] ?? 0), 1) }}</p></div>
            </div>
            <p class="text-xs text-gray-500 mb-4">Last evaluated {{ $snap['evaluated_at'] ?? '—' }} · Recommendation: {{ ucfirst($snap['recommendation'] ?? 'none') }}</p>
        @endif
        @if ($record->affiliate_lifecycle_note)
            <p class="text-xs text-gray-500 mb-4">Note: {{ $record->affiliate_lifecycle_note }}</p>
        @endif
        <form method="POST" action="{{ route('admin.partners.affiliate-lifecycle.update', $record) }}" class="grid sm:grid-cols-3 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Set lifecycle status</label>
                <select name="status" class="w-full rounded-lg border-gray-300 text-sm">
                    @foreach ($lifecycle->statuses() as $status)
                        <option value="{{ $status }}" @selected($lifecycle->statusFor($record) === $status)>{{ $lifecycle->label($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Reason (optional)</label>
                <input type="text" name="reason" maxlength="500" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Policy breach, manual review, reinstatement…">
            </div>
            <div class="sm:col-span-3">
                <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Update lifecycle</button>
            </div>
        </form>
    </div>

    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        @php $fraudService = app(\App\Services\AffiliateFraudDetectionService::class); @endphp
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Fraud controls</h3>
        <p class="text-sm text-gray-600 mb-2">
            Risk flag:
            <span class="font-semibold capitalize">{{ $fraudService->label((string) ($record->affiliate_risk_flag ?? 'low')) }}</span>
        </p>
        @if ($record->affiliate_fraud_snapshot)
            @php $fraudSnap = $record->affiliate_fraud_snapshot; @endphp
            <p class="text-xs text-gray-500 mb-3">Last scan {{ $fraudSnap['scanned_at'] ?? '—' }} · Score {{ $fraudSnap['score'] ?? 0 }}</p>
            <ul class="text-sm text-gray-700 space-y-1 mb-4">
                @foreach (($fraudSnap['signals'] ?? []) as $signal)
                    <li class="text-xs">• {{ $signal['message'] ?? '' }}</li>
                @endforeach
            </ul>
        @endif
        <div class="flex flex-wrap gap-3 items-end">
            <form method="POST" action="{{ route('admin.partners.affiliate-fraud.scan', $record) }}">
                @csrf
                <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold px-4 py-2 rounded-lg">Run fraud scan</button>
            </form>
            <form method="POST" action="{{ route('admin.partners.affiliate-risk-flag.update', $record) }}" class="flex flex-wrap gap-2 items-end">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Override risk flag</label>
                    <select name="risk_flag" class="rounded-lg border-gray-300 text-sm">
                        @foreach ($fraudService->flags() as $flag)
                            <option value="{{ $flag }}" @selected(($record->affiliate_risk_flag ?? 'low') === $flag)>{{ $fraudService->label($flag) }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Save flag</button>
            </form>
        </div>
    </div>

    @if (($affiliateEvaluations ?? collect())->isNotEmpty())
        <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Evaluation history</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500">
                        <tr>
                            <th class="text-left py-2">Period</th>
                            <th class="text-left py-2">KPI</th>
                            <th class="text-left py-2">Risk</th>
                            <th class="text-left py-2">Fraud</th>
                            <th class="text-left py-2">Recommendation</th>
                            <th class="text-left py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($affiliateEvaluations as $evaluation)
                            <tr>
                                <td class="py-2">{{ $evaluation->period_start?->format('d M') }} – {{ $evaluation->period_end?->format('d M Y') }}</td>
                                <td class="py-2">{{ number_format((float) $evaluation->kpi_score, 1) }}</td>
                                <td class="py-2">{{ number_format((float) $evaluation->risk_score, 1) }}</td>
                                <td class="py-2">{{ number_format((float) $evaluation->fraud_score, 1) }}</td>
                                <td class="py-2 capitalize">{{ $evaluation->recommendation }}</td>
                                <td class="py-2 capitalize">{{ $evaluation->action_taken ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Affiliate KYC</h3>
        <p class="text-sm text-gray-600 mb-4">
            Status:
            <span class="font-semibold {{ in_array($record->affiliate_kyc_status, ['verified', 'approved'], true) ? 'text-emerald-700' : 'text-amber-700' }}">
                {{ ucfirst($record->affiliate_kyc_status ?? 'pending') }}
            </span>
        </p>
        <div class="grid sm:grid-cols-3 gap-4 text-sm mb-4">
            @foreach ([
                'Selfie' => $record->affiliate_selfie_path,
                'ID document' => $record->affiliate_id_path,
                'Profile photo' => $record->affiliate_photo_path,
            ] as $label => $path)
                <div class="rounded-lg bg-gray-50 p-3">
                    <p class="text-xs text-gray-500 mb-2">{{ $label }}</p>
                    @if ($path)
                        <a href="{{ asset('storage/'.$path) }}" target="_blank" class="text-amber-700 hover:underline text-xs">View file</a>
                    @else
                        <p class="text-xs text-gray-400">Not uploaded</p>
                    @endif
                </div>
            @endforeach
        </div>
        @if ($record->affiliate_code)
            <p class="text-xs text-gray-500 mb-4">Public verification: <a href="{{ route('site.affiliate.verify', $record->affiliate_code) }}" class="text-amber-700 hover:underline" target="_blank">{{ route('site.affiliate.verify', $record->affiliate_code) }}</a></p>
        @endif
        @if (in_array($record->affiliate_kyc_status, ['submitted', 'pending', 'rejected'], true) || filled($record->affiliate_selfie_path))
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('admin.partners.affiliate-kyc.approve', $record) }}">
                    @csrf
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Approve KYC</button>
                </form>
                <form method="POST" action="{{ route('admin.partners.affiliate-kyc.reject', $record) }}">
                    @csrf
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Reject KYC</button>
                </form>
            </div>
        @endif
    </div>
@endif

@if ($recoveryStats ?? null)
    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Recovery performance</h3>
        <div class="grid sm:grid-cols-3 lg:grid-cols-6 gap-4 text-sm">
            <div><span class="text-gray-500">Assignments</span><p class="text-xl font-bold">{{ format_number($recoveryStats['assignments']) }}</p></div>
            <div><span class="text-gray-500">Active</span><p class="text-xl font-bold">{{ format_number($recoveryStats['active_cases']) }}</p></div>
            <div><span class="text-gray-500">Completed</span><p class="text-xl font-bold">{{ format_number($recoveryStats['completed_cases']) }}</p></div>
            <div><span class="text-gray-500">SLA breaches</span><p class="text-xl font-bold text-red-700">{{ format_number($recoveryStats['sla_breaches']) }}</p></div>
            <div><span class="text-gray-500">Commission earned</span><p class="text-xl font-bold">{{ format_money($recoveryStats['commission_earned']) }}</p></div>
            <div><span class="text-gray-500">Commission paid</span><p class="text-xl font-bold">{{ format_money($recoveryStats['commission_paid']) }}</p></div>
        </div>
    </div>
@endif
