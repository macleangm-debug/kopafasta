@php
    $accounts = $dossier['payment_accounts'] ?? collect();
    $snapshot = $dossier['payment_snapshot'] ?? [];
    $complete = (bool) ($dossier['payment_complete'] ?? false);
@endphp

<div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">Payment account</p>
            <h4 class="text-base font-bold text-gray-900 mt-0.5">Disbursement / repayment account</h4>
            <p class="text-xs text-gray-500 mt-0.5">Canonical payment details used for loan disbursement.</p>
        </div>
        <span @class([
            'rounded-full px-2.5 py-1 text-[11px] font-bold',
            'bg-emerald-100 text-emerald-800' => $complete,
            'bg-amber-100 text-amber-900' => ! $complete,
        ])>{{ $complete ? 'Ready' : 'Incomplete' }}</span>
    </div>

    @if ($accounts->isNotEmpty())
        <div class="space-y-3">
            @foreach ($accounts as $account)
                @php
                    $ok = app(\App\Services\CustomerDisbursementDetailsService::class)->accountIsComplete($account);
                    $snap = app(\App\Services\CustomerDisbursementDetailsService::class)->snapshotFromAccount($account);
                @endphp
                <div class="rounded-2xl ring-1 ring-brand/10 bg-white px-4 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <p class="text-sm font-semibold text-gray-900">{{ $snap['method_label'] ?? ($account->isMobile() ? 'Mobile money' : 'Bank') }}</p>
                        <div class="flex items-center gap-2">
                            @if ($account->is_default)
                                <span class="text-[10px] font-bold uppercase tracking-wider text-brand">Default</span>
                            @endif
                            <span @class([
                                'rounded-full px-2 py-0.5 text-[11px] font-bold',
                                'bg-emerald-100 text-emerald-800' => $ok,
                                'bg-amber-100 text-amber-900' => ! $ok,
                            ])>{{ $ok ? 'Complete' : 'Incomplete' }}</span>
                        </div>
                    </div>
                    <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        @if ($account->isMobile())
                            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Provider</dt><dd class="font-medium mt-0.5">{{ $snap['mobile_provider_label'] ?? '—' }}</dd></div>
                            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Number</dt><dd class="font-medium mt-0.5 tabular-nums">{{ $snap['mobile_number'] ?? '—' }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs uppercase tracking-wider text-gray-500">Account name</dt><dd class="font-medium mt-0.5">{{ $snap['mobile_account_name'] ?? '—' }}</dd></div>
                        @else
                            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Bank</dt><dd class="font-medium mt-0.5">{{ $snap['bank_name'] ?? '—' }}</dd></div>
                            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Branch</dt><dd class="font-medium mt-0.5">{{ $snap['bank_branch'] ?? '—' }}</dd></div>
                            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Account name</dt><dd class="font-medium mt-0.5">{{ $snap['bank_account_name'] ?? '—' }}</dd></div>
                            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Account number</dt><dd class="font-medium mt-0.5 font-mono">{{ $snap['bank_account_number'] ?? '—' }}</dd></div>
                        @endif
                    </dl>
                </div>
            @endforeach
        </div>
    @elseif (filled($snapshot['method'] ?? null))
        <dl class="grid md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Method</dt><dd class="font-medium mt-0.5">{{ $snapshot['method_label'] ?? $snapshot['method'] }}</dd></div>
            @if (($snapshot['method'] ?? '') === 'mobile_money')
                <div><dt class="text-xs uppercase tracking-wider text-gray-500">Provider</dt><dd class="font-medium mt-0.5">{{ $snapshot['mobile_provider_label'] ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-gray-500">Number</dt><dd class="font-medium mt-0.5">{{ $snapshot['mobile_number'] ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-gray-500">Account name</dt><dd class="font-medium mt-0.5">{{ $snapshot['mobile_account_name'] ?? '—' }}</dd></div>
            @else
                <div><dt class="text-xs uppercase tracking-wider text-gray-500">Bank</dt><dd class="font-medium mt-0.5">{{ $snapshot['bank_name'] ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-gray-500">Account name</dt><dd class="font-medium mt-0.5">{{ $snapshot['bank_account_name'] ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase tracking-wider text-gray-500">Account number</dt><dd class="font-medium mt-0.5 font-mono">{{ $snapshot['bank_account_number'] ?? '—' }}</dd></div>
            @endif
        </dl>
    @else
        <p class="text-sm text-gray-500">No payment account on file yet.</p>
    @endif
</div>
