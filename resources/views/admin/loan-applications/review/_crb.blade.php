@php
    $crb = $review['crb'] ?? [];
    $crbExplain = app(\App\Services\CrbCreditCheckService::class)->recommendationExplanation($crb);
    $crbFreshnessDays = app(\App\Services\CrbFreshnessService::class)->freshnessDays();
@endphp

<x-admin.review-section id="review-crb" title="CRB & credit review" subtitle="Internal credit bureau data for underwriting — not shown to borrowers">
    @if (($record->current_stage ?? 'submitted') === 'screening')
        <p class="mb-4 text-sm text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-4 py-3">
            Complete screening to begin formal credit review. CRB data below is available for reference.
        </p>
    @elseif (! in_array($record->current_stage ?? 'submitted', ['credit_appraisal', 'pre_approval', 'approval', 'disbursement'], true))
        <p class="mb-4 text-sm text-gray-600 bg-gray-50 ring-1 ring-gray-100 rounded-lg px-4 py-3">
            Credit appraisal becomes the active underwriting stage after screening is completed.
        </p>
    @endif
    <div class="grid sm:grid-cols-2 gap-4 mb-5">
        <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500">Identity (from NIDA / CRB)</p>
            <dl class="mt-2 space-y-1 text-sm">
                <div><dt class="text-xs text-gray-500 inline">Name:</dt> <dd class="inline font-medium">{{ $crb['identity']['full_name'] ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500 inline">NIDA:</dt> <dd class="inline font-mono text-xs">{{ $crb['identity']['national_id'] ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500 inline">DOB:</dt> <dd class="inline">{{ $crb['identity']['date_of_birth'] ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500 inline">Gender:</dt> <dd class="inline capitalize">{{ $crb['identity']['gender'] ?? '—' }}</dd></div>
            </dl>
        </div>
        <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">CRB freshness — {{ $crbFreshnessDays }} days (configurable)</p>
                    <p @class([
                        'text-sm font-bold uppercase mt-1',
                        'text-emerald-700' => ($crb['freshness_tone'] ?? '') === 'emerald',
                        'text-amber-700'   => ($crb['freshness_tone'] ?? '') === 'amber',
                        'text-gray-700'    => ($crb['freshness_tone'] ?? '') === 'gray',
                    ])>{{ $crb['freshness_label'] ?? '—' }}</p>
                    @if ($crb['checked_at'])
                        <p class="text-xs text-gray-500 mt-1">Retrieved {{ $crb['checked_at']->diffForHumans() }}</p>
                    @endif
                    @if ($crb['days_since_check'] !== null)
                        <p class="text-xs text-gray-500">{{ $crb['days_since_check'] }} days ago</p>
                    @endif
                    <p class="text-xs text-gray-500 mt-2">Reports within the {{ $crbFreshnessDays }}-day window are reused automatically (no manual refresh).</p>
                </div>
            </div>
            @if (! empty($crb['submission_meta']['reused']))
                <p class="text-xs text-emerald-700 mt-3">This application reused an existing CRB record (no new bureau charge).</p>
            @elseif (! empty($crb['submission_meta']['refreshed']))
                <p class="text-xs text-sky-700 mt-3">CRB was refreshed when this application was submitted.</p>
            @endif
        </div>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-5">
        <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500">CRB source</p>
            <p class="text-sm font-semibold text-gray-900 mt-1">{{ $crb['status'] }}</p>
        </div>
        <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500">Credit score</p>
            <p class="text-sm font-semibold text-gray-900 mt-1">{{ $crb['score'] ?? '—' }}</p>
            @if ($crb['risk_grade'])
                <p class="text-xs text-gray-500 mt-1">Grade: {{ strtoupper($crb['risk_grade']) }}</p>
            @endif
        </div>
        <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500">CRB recommendation</p>
            <p @class([
                'text-sm font-bold uppercase mt-1',
                'text-emerald-700' => $crb['recommendation'] === 'approve',
                'text-amber-700'   => $crb['recommendation'] === 'refer',
                'text-red-700'     => $crb['recommendation'] === 'reject',
            ])>{{ ucfirst($crb['recommendation']) }}</p>
            <p class="text-xs text-gray-600 mt-2">{{ $crbExplain['summary'] }}</p>
            @if (! empty($crbExplain['reasons']))
                <ul class="mt-2 space-y-1 text-xs text-gray-600">
                    @foreach ($crbExplain['reasons'] as $reason)
                        <li>• {{ $reason }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm mb-5">
        <div><dt class="text-xs text-gray-500">Active / existing loans</dt><dd class="font-semibold mt-0.5">{{ $crb['existing_loans'] }}</dd></div>
        <div><dt class="text-xs text-gray-500">Outstanding balances</dt><dd class="font-semibold mt-0.5">{{ $crb['outstanding_balance'] ? format_money($crb['outstanding_balance']) : '—' }}</dd></div>
        <div><dt class="text-xs text-gray-500">Delinquencies</dt><dd class="font-semibold mt-0.5">{{ $crb['delinquencies'] }}</dd></div>
        <div><dt class="text-xs text-gray-500">CRB RUID</dt><dd class="font-mono text-xs mt-0.5">{{ $crb['crb_ruid'] ?? '—' }}</dd></div>
    </dl>

    @if (! empty($crb['loan_history']))
        <div class="rounded-lg ring-1 ring-gray-100 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-2 text-left">Lender</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($crb['loan_history'] as $row)
                        <tr>
                            <td class="px-4 py-2">{{ $row['lender'] ?? '—' }}</td>
                            <td class="px-4 py-2 capitalize">{{ $row['status'] ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">{{ isset($row['balance']) ? format_money($row['balance']) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <p class="mt-4 text-xs text-gray-500">Affordability (internal): <span class="font-semibold uppercase">{{ $review['affordability']['verdict'] ?? '—' }}</span></p>
    @if (! empty($review['affordability']['reason']))
        <p class="mt-2 text-sm text-gray-600 bg-gray-50 rounded-lg px-4 py-3 ring-1 ring-gray-100">{{ $review['affordability']['reason'] }}</p>
    @endif
</x-admin.review-section>

<x-admin.review-section id="review-appraisal" title="Affordability appraisal" subtitle="Debt-service ratio and repayment capacity">
    @php $aff = is_array($review['affordability'] ?? null) ? $review['affordability'] : []; @endphp
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
        <span @class([
            'inline-flex items-center px-2.5 py-1 rounded text-xs font-semibold uppercase',
            'bg-emerald-100 text-emerald-800' => ($aff['verdict'] ?? '') === 'pass',
            'bg-amber-100 text-amber-800'     => ($aff['verdict'] ?? '') === 'warn',
            'bg-red-100 text-red-800'           => ($aff['verdict'] ?? '') === 'fail',
        ])>{{ $aff['verdict'] ?? '—' }}</span>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div><div class="text-xs uppercase text-gray-500">Monthly income</div><div class="font-semibold">{{ format_money($aff['net_income'] ?? 0) }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">Existing obligations</div><div class="font-semibold">{{ format_money($aff['existing_obligations'] ?? 0) }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">New EMI</div><div class="font-semibold">{{ format_money($aff['new_emi'] ?? 0) }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">DSR / Limit</div><div class="font-semibold">{{ format_number(($aff['dsr'] ?? 0) * 100, 1) }}% / {{ format_number(($aff['threshold'] ?? 0) * 100, 1) }}%</div></div>
    </div>
</x-admin.review-section>
