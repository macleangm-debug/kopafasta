@php
    $standing = $dossier['repayment_standing'] ?? [];
    $crb = $dossier['crb'] ?? [];
    $eligibility = $dossier['eligibility'] ?? [];
    $checklist = collect($dossier['checklist'] ?? []);
    $completeCount = $checklist->where('tone', 'emerald')->count();
    $totalCount = $checklist->count();
    $eligCats = collect($eligibility['by_category'] ?? []);
    $eligReady = $eligCats->where('complete', true)->count();
    $eligTotal = $eligCats->count();
    $currentApp = $dossier['current_application'] ?? null;
    $activeLoans = $dossier['loans']->whereIn('status', ['active', 'arrears', 'disbursed', 'restructuring']);
    $outstanding = (float) $activeLoans->sum(fn ($l) => (float) ($l->outstanding_balance ?? 0));
    $latestPayment = $dossier['latest_payment'] ?? null;
    $assetCount = (int) ($dossier['asset_count'] ?? 0);
    $guarantorService = app(\App\Services\GuarantorInvitationService::class);
@endphp

<div class="space-y-8">
    <section class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="rounded-2xl ring-1 ring-brand/10 bg-white px-4 py-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Identity</p>
            <p class="text-sm font-semibold text-gray-900 mt-1">{{ $customer->full_name }}</p>
            <p class="text-xs text-gray-500 mt-0.5">{{ $customer->customer_number }}@if($customer->member_no) · {{ $customer->member_no }}@endif</p>
        </div>
        <div class="rounded-2xl ring-1 ring-brand/10 bg-white px-4 py-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Grade · Trust</p>
            <p class="text-2xl font-bold tabular-nums text-gray-900 mt-1">{{ $crb['risk_grade'] ?? '—' }} · {{ $standing['trust_percent'] ?? 0 }}%</p>
            <p class="text-xs text-gray-500 mt-0.5">{{ $standing['label'] ?? '—' }}</p>
        </div>
        <div class="rounded-2xl ring-1 ring-brand/10 bg-white px-4 py-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Profile completion</p>
            <p class="text-2xl font-bold tabular-nums text-gray-900 mt-1">{{ $dossier['profile']['percent'] ?? 0 }}%</p>
            <p class="text-xs text-gray-500 mt-0.5">{{ $completeCount }}/{{ $totalCount }} sections ready</p>
        </div>
        <div class="rounded-2xl ring-1 ring-brand/10 bg-white px-4 py-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Eligibility to apply</p>
            <p class="text-2xl font-bold tabular-nums text-gray-900 mt-1">{{ ($eligibility['can_apply'] ?? false) ? 'Yes' : 'No' }}</p>
            <p class="text-xs text-gray-500 mt-0.5">{{ $eligReady }}/{{ max(1, $eligTotal) }} categories ready</p>
        </div>
    </section>

    <div class="grid lg:grid-cols-2 gap-6">
        <section class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-brand-muted/40 to-white">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">Profile completion</p>
                <h4 class="text-base font-bold text-gray-900 mt-0.5">What information is complete?</h4>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($checklist as $item)
                    @php $ok = ($item['tone'] ?? '') === 'emerald'; @endphp
                    <li class="px-5 py-3 flex items-center justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">{{ $item['label'] }}</p>
                            @if (! $ok && ! empty($item['detail']))
                                <p class="text-xs text-amber-800 mt-0.5">{{ $item['detail'] }}</p>
                            @endif
                        </div>
                        <span @class([
                            'shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-bold',
                            'bg-emerald-100 text-emerald-800' => $ok,
                            'bg-amber-100 text-amber-900' => ! $ok,
                        ])>{{ $ok ? '✓' : 'Needs attention' }}</span>
                    </li>
                @endforeach
            </ul>
            @if ($assetCount > 0)
                <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/80">
                    <a href="{{ route('admin.customers.show', ['customer' => $customer, 'tab' => 'assets']) }}#member-file"
                       class="text-sm font-semibold text-brand hover:underline">
                        Assets / collateral: {{ $assetCount }} →
                    </a>
                </div>
            @endif
        </section>

        <section class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-brand-muted/40 to-white">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand">Eligibility to apply</p>
                <h4 class="text-base font-bold text-gray-900 mt-0.5">Prerequisites for starting / submitting</h4>
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse ($eligCats as $cat)
                    <li class="px-5 py-3 flex items-start justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900">{{ $cat['label'] }}</p>
                            @if (! ($cat['complete'] ?? false))
                                <p class="text-xs text-amber-800 mt-0.5">→ {{ $cat['detail'] }}</p>
                            @endif
                        </div>
                        <span @class([
                            'shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-bold',
                            'bg-emerald-100 text-emerald-800' => $cat['complete'] ?? false,
                            'bg-amber-100 text-amber-900' => ! ($cat['complete'] ?? false),
                        ])>{{ ($cat['complete'] ?? false) ? '✓' : 'Needs attention' }}</span>
                    </li>
                @empty
                    <li class="px-5 py-4 text-sm text-gray-500">Eligibility checklist unavailable.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <div class="grid lg:grid-cols-3 gap-4">
        <section class="rounded-2xl ring-1 ring-brand/10 bg-white px-4 py-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Current application</p>
            @if ($currentApp)
                <p class="text-sm font-semibold text-gray-900 mt-2">{{ $currentApp->product?->name ?? 'Application' }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ $currentApp->application_number }} · {{ display_label($currentApp->status, 'application_status') }}</p>
                @php $gLinks = $currentApp->customerGuarantors ?? collect(); @endphp
                @if ($gLinks->isNotEmpty())
                    <div class="mt-3 space-y-1">
                        @foreach ($gLinks->take(2) as $link)
                            @php $st = $guarantorService->workflowStatus($link, $link->invitation); @endphp
                            <p class="text-xs text-gray-600">Guarantor: {{ $st['label'] }}</p>
                        @endforeach
                    </div>
                @endif
                <a href="{{ route('admin.loan-applications.show', $currentApp) }}" class="inline-block mt-3 text-xs font-semibold text-brand hover:underline">Open →</a>
            @else
                <p class="text-sm text-gray-500 mt-2">No open application.</p>
            @endif
        </section>

        <section class="rounded-2xl ring-1 ring-brand/10 bg-white px-4 py-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Active loan(s)</p>
            <p class="text-2xl font-bold tabular-nums text-gray-900 mt-2">{{ $activeLoans->count() }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Outstanding {{ format_money($outstanding) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $standing['label'] ?? '—' }} · streak {{ $standing['streak'] ?? 0 }}</p>
            <a href="{{ route('admin.customers.show', ['customer' => $customer, 'tab' => 'loans']) }}#member-file" class="inline-block mt-3 text-xs font-semibold text-brand hover:underline">Loans →</a>
        </section>

        <section class="rounded-2xl ring-1 ring-brand/10 bg-white px-4 py-4">
            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Latest payment</p>
            @if ($latestPayment)
                @php $ctx = $latestPayment->adminContext(); @endphp
                <p class="text-sm font-semibold text-gray-900 mt-2">{{ format_money((float) $latestPayment->amount) }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ $ctx['type'] ?? $latestPayment->payment_type }} · {{ display_label($latestPayment->status, 'payment_status') }}</p>
                @if (! empty($ctx['application_url']))
                    <a href="{{ $ctx['application_url'] }}" class="inline-block mt-2 text-xs font-semibold text-brand hover:underline">{{ $ctx['application_number'] }} →</a>
                @elseif (! empty($ctx['loan_url']))
                    <a href="{{ $ctx['loan_url'] }}" class="inline-block mt-2 text-xs font-semibold text-brand hover:underline">{{ $ctx['loan_number'] }} →</a>
                @endif
            @else
                <p class="text-sm text-gray-500 mt-2">No payments yet.</p>
            @endif
            <a href="{{ route('admin.customers.show', ['customer' => $customer, 'tab' => 'payments']) }}#member-file" class="inline-block mt-3 text-xs font-semibold text-brand hover:underline">All payments →</a>
        </section>
    </div>
</div>
