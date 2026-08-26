<x-site.borrower-layout
    :title="brand_title($loan->loan_number)"
    active="loans"
    content-width="wide">

    <div class="mb-4">
        <a href="{{ route('site.borrower.loans', ['tab' => 'active']) }}" data-kf-motion="pop" class="text-sm font-semibold text-brand hover:underline">
            {{ __('borrower.loans_page.back') }}
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @php
        $statusBadge = match ($loan->status) {
            'active', 'disbursed' => 'bg-emerald-100 text-emerald-700',
            'arrears' => 'bg-red-100 text-red-700',
            'closed' => 'bg-gray-100 text-gray-700',
            default => 'bg-brand-muted text-brand',
        };
    @endphp

    <x-site.borrower-page-header
        :eyebrow="$servicing['product_name'] ?? '—'"
        :title="$servicing['loan_reference']"
        :subtitle="__('borrower.loans_page.active_loan')"
        :share="'kf-loan-'.$loan->id">
        <x-slot:actions>
            <span class="text-xs font-semibold rounded-full px-3 py-1.5 {{ $statusBadge }}">{{ $servicing['status_label'] ?? ucfirst($loan->status) }}</span>
        </x-slot:actions>
    </x-site.borrower-page-header>

    @if ($servicing['in_arrears'])
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">{{ __('borrower.loans_page.arrears_alert') }}</p>
            @if (($servicing['amount_in_arrears'] ?? 0) > 0)
                <p class="mt-1">{{ __('borrower.loans_page.arrears_amount', ['amount' => format_money($servicing['amount_in_arrears']), 'count' => $servicing['overdue_installments']]) }}</p>
            @endif
        </div>
    @endif

    @if (! empty($auctionHold))
        <div class="mb-4">
            <x-site.auction-hold-banner :status="$auctionHold" />
        </div>
    @endif

    @if ($loan->status === 'closed')
        @php $completion = app(\App\Services\LendingJourneyService::class)->completionSummary($loan); @endphp
        <div class="mb-6 rounded-3xl bg-emerald-50 ring-1 ring-emerald-200 p-6">
            <p class="text-lg font-bold text-emerald-900">{{ __('borrower.loan_servicing.completed_title') }} ✓</p>
            <p class="text-sm text-emerald-800 mt-1">{{ __('borrower.loan_servicing.completed_body') }}</p>
            <dl class="mt-4 grid sm:grid-cols-3 gap-3 text-sm">
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-emerald-800/70">{{ __('borrower.loan_servicing.amount_borrowed') }}</dt>
                    <dd class="font-bold text-emerald-950 mt-1 tabular-nums">{{ format_money((float) $completion['amount_borrowed']) }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-emerald-800/70">{{ __('borrower.loan_servicing.amount_repaid') }}</dt>
                    <dd class="font-bold text-emerald-950 mt-1 tabular-nums">{{ format_money((float) $completion['amount_repaid']) }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-emerald-800/70">{{ __('borrower.loan_servicing.completed_on') }}</dt>
                    <dd class="font-bold text-emerald-950 mt-1">{{ optional($completion['completed_at'])->format('d M Y') ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    @endif

    {{-- Loan summary --}}
    <div class="glass-card p-5 mb-6">
        <h2 class="font-semibold text-gray-900 mb-4">{{ __('borrower.loan_servicing.summary_title') }}</h2>
        @if ($loan->status !== 'closed')
            <div class="grid sm:grid-cols-2 gap-4 mb-5">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_servicing.balance_remaining') }}</p>
                    <p class="text-3xl font-extrabold text-gray-900 mt-1 tabular-nums">{{ format_money((float) $servicing['outstanding_balance']) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_servicing.next_payment') }}</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">
                        @if ($servicing['next_due_amount'])
                            {{ format_money((float) $servicing['next_due_amount']) }}
                            @if ($servicing['next_due_date'])
                                <span class="text-gray-500 font-semibold">· {{ $servicing['next_due_date']->format('d M') }}</span>
                            @endif
                        @else
                            —
                        @endif
                    </p>
                    <p class="text-xs text-gray-500 mt-1">{{ __('borrower.loan_servicing.payments_progress', [
                        'paid' => $servicing['installments_paid'] ?? 0,
                        'total' => $servicing['installments_total'] ?? 0,
                    ]) }}</p>
                </div>
            </div>
        @endif
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_servicing.reference') }}</p>
                <p class="font-mono font-semibold text-brand mt-1">{{ $servicing['loan_reference'] }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.loan_amount') }}</p>
                <p class="font-semibold text-gray-900 mt-1 tabular-nums">{{ format_money($servicing['principal']) }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.outstanding') }}</p>
                <x-loan-balance-breakdown
                    :breakdown="$servicing['balance_breakdown'] ?? []"
                    :recovery-charges="$servicing['recovery_charges'] ?? null"
                />
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_servicing.disbursement_date') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ optional($servicing['disbursement_date'])->format('d M Y') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_servicing.status') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $servicing['status_label'] }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_servicing.arrears_status') }}</p>
                <p class="font-semibold mt-1 {{ ($servicing['arrears_status'] ?? '') === 'in_arrears' ? 'text-red-700' : 'text-emerald-700' }}">
                    {{ ($servicing['arrears_status'] ?? '') === 'in_arrears'
                        ? __('borrower.loan_servicing.in_arrears')
                        : __('borrower.loan_servicing.current') }}
                </p>
            </div>
        </div>
    </div>

    {{-- Repayment --}}
    <div class="glass-card p-5 mb-6">
        <h2 class="font-semibold text-gray-900 mb-4">{{ __('borrower.loan_servicing.repayment_title') }}</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm mb-4">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_servicing.installments_paid') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $servicing['installments_paid'] ?? 0 }} / {{ $servicing['installments_total'] ?? 0 }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_servicing.installments_remaining') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $servicing['installments_remaining'] ?? 0 }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.next_due') }}</p>
                <p class="font-semibold text-gray-900 mt-1">
                    @if ($servicing['next_due_date'])
                        {{ $servicing['next_due_date']->format('d M Y') }}
                    @else
                        —
                    @endif
                </p>
                @if ($servicing['next_due_amount'])
                    <p class="text-xs text-gray-500 mt-0.5 tabular-nums">{{ format_money($servicing['next_due_amount']) }}</p>
                @endif
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.repaid_pct', ['pct' => format_number($servicing['progress_pct'], 0)]) }}</p>
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden mt-2">
                    <div class="h-full {{ $servicing['in_arrears'] ? 'bg-red-500' : 'bg-brand' }}" style="width: {{ $servicing['progress_pct'] }}%"></div>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($loan->status !== 'closed')
                <a href="{{ route('site.borrower.schedule', $loan->id) }}" class="bg-brand hover:bg-brand-light text-white text-xs font-semibold px-4 py-2 rounded-xl">{{ __('borrower.loans_page.view_schedule') }}</a>
                @if (! empty($openPayment))
                    <a href="{{ route('site.borrower.payments.show', $openPayment) }}" class="bg-brand-gold hover:bg-yellow-400 text-brand text-xs font-bold px-4 py-2 rounded-xl">
                        {{ __('borrower.loans_page.make_payment') }} · {{ format_money((float) $openPayment->amount) }}
                    </a>
                @else
                    <a href="{{ route('site.borrower.payments.create', ['loan' => $loan->id]) }}" class="bg-brand-gold hover:bg-yellow-400 text-brand text-xs font-bold px-4 py-2 rounded-xl">{{ __('borrower.loans_page.make_payment') }}</a>
                @endif
            @endif
        </div>
        @if (! empty($openPayment))
            <p class="text-xs text-gray-500 mt-2">Kopafasta asked you to pay {{ format_money((float) $openPayment->amount) }} ({{ $openPayment->reference }}). Paying this amount is applied to penalty, then interest, then principal — including overdue instalments.</p>
        @endif
    </div>

    {{-- Documents --}}
    @if ($finalContract || $scheduleAnnex)
        <div class="glass-card p-5 mb-6">
            <h2 class="font-semibold text-gray-900 mb-4">{{ __('borrower.loan_servicing.documents_title') }}</h2>
            <div class="flex flex-wrap gap-3">
                @if ($finalContract?->file_path)
                    <a href="{{ route('site.borrower.loans.final-contract', $loan) }}" target="_blank"
                       class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-brand hover:bg-brand-light px-4 py-2 rounded-xl">
                        {{ __('borrower.loan_servicing.view_final_contract') }}
                    </a>
                @endif
                @if ($scheduleAnnex?->file_path)
                    <a href="{{ route('site.borrower.agreement.download', $scheduleAnnex) }}" target="_blank"
                       class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-muted hover:bg-brand-muted/80 px-4 py-2 rounded-xl">
                        {{ __('borrower.loan_servicing.view_schedule_pdf') }}
                    </a>
                @endif
            </div>
        </div>
    @endif

    {{-- Actions --}}
    <div class="glass-card p-5 mb-6">
        <h2 class="font-semibold text-gray-900 mb-4">{{ __('borrower.loan_servicing.actions_title') }}</h2>
        <div class="flex flex-wrap gap-2">
            @if ($canRestructure)
                <a href="{{ route('site.borrower.loans.restructure', $loan) }}"
                   class="inline-flex items-center gap-2 text-sm font-semibold text-brand bg-brand-muted hover:bg-brand-muted/80 px-4 py-2 rounded-xl">
                    {{ __('borrower.loan_actions.restructure_title') }}
                </a>
            @endif
            @if ($canTopUp)
                <a href="{{ route('site.borrower.loans.top-up', $loan) }}"
                   class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-800 bg-emerald-100 hover:bg-emerald-200 px-4 py-2 rounded-xl">
                    {{ __('borrower.loan_actions.top_up_title') }}
                </a>
            @endif
        </div>
    </div>

    @if ($recentRepayments->isNotEmpty())
        <div class="glass-card p-5 mb-6">
            <h2 class="font-semibold mb-4">{{ __('borrower.loans_page.recent_payments') }}</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($recentRepayments as $payment)
                    <li class="py-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="font-medium font-mono text-brand">{{ $payment->reference ?? __('borrower.loan_servicing.recent_payment_fallback') }}</p>
                            <p class="text-xs text-gray-500">{{ optional($payment->paid_at)->format('d M Y, H:i') ?? '—' }}</p>
                        </div>
                        <span class="font-semibold tabular-nums">{{ format_money($payment->amount) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($timeline))
        <div class="glass-card p-5">
            <h2 class="font-semibold text-gray-900 mb-4">{{ __('borrower.loan_servicing.timeline_title') }}</h2>
            <ul class="space-y-4">
                @foreach ($timeline as $event)
                    @php
                        $toneRing = match ($event['tone']) {
                            'emerald' => 'bg-emerald-500 ring-emerald-100',
                            'sky' => 'bg-sky-500 ring-sky-100',
                            'amber' => 'bg-brand-gold ring-amber-100',
                            'red' => 'bg-red-500 ring-red-100',
                            default => 'bg-brand ring-brand-muted',
                        };
                    @endphp
                    <li class="flex gap-3">
                        <span class="mt-1.5 size-2.5 shrink-0 rounded-full ring-4 {{ $toneRing }}"></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-gray-900">{{ $event['label'] }}</p>
                                <p class="text-xs text-gray-500">{{ $event['at']->format('d M Y') }}</p>
                            </div>
                            @if (! empty($event['detail']))
                                <p class="text-xs text-gray-600 mt-0.5">{{ $event['detail'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-site.borrower-layout>
