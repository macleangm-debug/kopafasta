@php $crb = $review['crb']; @endphp

<x-admin.review-section id="review-crb" title="CRB & credit review" subtitle="Bureau status, exposure and system recommendation">
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-5">
        <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500">CRB status</p>
            <p class="text-sm font-semibold text-gray-900 mt-1">{{ $crb['status'] }}</p>
            @if ($crb['checked_at'])
                <p class="text-xs text-gray-500 mt-1">Checked {{ $crb['checked_at']->diffForHumans() }}</p>
            @endif
        </div>
        <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500">Credit score</p>
            <p class="text-sm font-semibold text-gray-900 mt-1">{{ $crb['score'] ?? '—' }}</p>
            @if ($crb['risk_grade'])
                <p class="text-xs text-gray-500 mt-1">Grade: {{ strtoupper($crb['risk_grade']) }}</p>
            @endif
        </div>
        <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500">System recommendation</p>
            <p @class([
                'text-sm font-bold uppercase mt-1',
                'text-emerald-700' => $crb['recommendation'] === 'approve',
                'text-amber-700'   => $crb['recommendation'] === 'refer',
                'text-red-700'     => $crb['recommendation'] === 'reject',
            ])>{{ $crb['recommendation'] }}</p>
        </div>
    </div>

    <dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
        <div><dt class="text-xs text-gray-500">Existing loans</dt><dd class="font-semibold mt-0.5">{{ $crb['existing_loans'] }}</dd></div>
        <div><dt class="text-xs text-gray-500">Delinquencies</dt><dd class="font-semibold mt-0.5">{{ $crb['delinquencies'] }}</dd></div>
        <div><dt class="text-xs text-gray-500">CRB RUID</dt><dd class="font-mono text-xs mt-0.5">{{ $crb['crb_ruid'] ?? '—' }}</dd></div>
        <div><dt class="text-xs text-gray-500">Affordability</dt>
            <dd class="font-semibold mt-0.5 uppercase">{{ $review['affordability']['verdict'] ?? '—' }}</dd>
        </div>
    </dl>

    @if (! empty($review['affordability']['reason']))
        <p class="mt-4 text-sm text-gray-600 bg-gray-50 rounded-lg px-4 py-3 ring-1 ring-gray-100">
            {{ $review['affordability']['reason'] }}
        </p>
    @endif
</x-admin.review-section>

<x-admin.review-section id="review-appraisal" title="Affordability appraisal" subtitle="Debt-service ratio and repayment capacity">
    @php $aff = $review['affordability']; @endphp
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
        <span @class([
            'inline-flex items-center px-2.5 py-1 rounded text-xs font-semibold uppercase',
            'bg-emerald-100 text-emerald-800' => ($aff['verdict'] ?? '') === 'pass',
            'bg-amber-100 text-amber-800'     => ($aff['verdict'] ?? '') === 'warn',
            'bg-red-100 text-red-800'           => ($aff['verdict'] ?? '') === 'fail',
        ])>{{ $aff['verdict'] ?? '—' }}</span>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div><div class="text-xs uppercase text-gray-500">Monthly income</div><div class="font-semibold">TZS {{ number_format($aff['net_income'] ?? 0) }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">Existing obligations</div><div class="font-semibold">TZS {{ number_format($aff['existing_obligations'] ?? 0) }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">New EMI</div><div class="font-semibold">TZS {{ number_format($aff['new_emi'] ?? 0) }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">DSR / Limit</div><div class="font-semibold">{{ number_format(($aff['dsr'] ?? 0) * 100, 1) }}% / {{ number_format(($aff['threshold'] ?? 0) * 100, 1) }}%</div></div>
    </div>
</x-admin.review-section>
