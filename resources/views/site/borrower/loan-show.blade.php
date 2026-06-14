<x-site.borrower-layout
    :title="brand_title($loan->loan_number)"
    active="loans">

    <div class="mb-4">
        <a href="{{ route('site.borrower.loans', ['tab' => 'active']) }}" class="text-xs text-gray-500 hover:text-gray-700">
            {{ __('borrower.loans_page.back') }}
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
        <div>
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">{{ $servicing['product_name'] ?? '—' }}</p>
            <h1 class="text-2xl font-bold font-mono">{{ $servicing['loan_reference'] }}</h1>
            <p class="text-xs text-gray-500 mt-1">{{ __('borrower.loans_page.active_loan') }}</p>
        </div>
        @php
            $statusBadge = match ($loan->status) {
                'active', 'disbursed' => 'bg-emerald-100 text-emerald-700',
                'arrears' => 'bg-red-100 text-red-700',
                'closed' => 'bg-gray-100 text-gray-700',
                default => 'bg-amber-100 text-amber-700',
            };
        @endphp
        <span class="text-xs font-semibold rounded-full px-3 py-1.5 {{ $statusBadge }}">{{ $servicing['status_label'] ?? ucfirst($loan->status) }}</span>
    </div>

    @if ($servicing['in_arrears'])
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">{{ __('borrower.loans_page.arrears_alert') }}</p>
            @if (($servicing['amount_in_arrears'] ?? 0) > 0)
                <p class="mt-1">{{ __('borrower.loans_page.arrears_amount', ['amount' => format_money($servicing['amount_in_arrears']), 'count' => $servicing['overdue_installments']]) }}</p>
            @endif
        </div>
    @endif

    {{-- Loan summary --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
        <h2 class="font-semibold text-gray-900 mb-4">{{ __('borrower.loan_servicing.summary_title') }}</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loan_servicing.reference') }}</p>
                <p class="font-mono font-semibold text-gray-900 mt-1">{{ $servicing['loan_reference'] }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_amount') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ format_money($servicing['principal']) }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.outstanding') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ format_money($servicing['outstanding_balance']) }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loan_servicing.disbursement_date') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ optional($servicing['disbursement_date'])->format('d M Y') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loan_servicing.status') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $servicing['status_label'] }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loan_servicing.arrears_status') }}</p>
                <p class="font-semibold mt-1 {{ ($servicing['arrears_status'] ?? '') === 'in_arrears' ? 'text-red-700' : 'text-emerald-700' }}">
                    {{ ($servicing['arrears_status'] ?? '') === 'in_arrears'
                        ? __('borrower.loan_servicing.in_arrears')
                        : __('borrower.loan_servicing.current') }}
                </p>
            </div>
        </div>
    </div>

    {{-- Repayment --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
        <h2 class="font-semibold text-gray-900 mb-4">{{ __('borrower.loan_servicing.repayment_title') }}</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm mb-4">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loan_servicing.installments_paid') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $servicing['installments_paid'] ?? 0 }} / {{ $servicing['installments_total'] ?? 0 }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loan_servicing.installments_remaining') }}</p>
                <p class="font-semibold text-gray-900 mt-1">{{ $servicing['installments_remaining'] ?? 0 }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.next_due') }}</p>
                <p class="font-semibold text-gray-900 mt-1">
                    @if ($servicing['next_due_date'])
                        {{ $servicing['next_due_date']->format('d M Y') }}
                    @else
                        —
                    @endif
                </p>
                @if ($servicing['next_due_amount'])
                    <p class="text-xs text-gray-500 mt-0.5">{{ format_money($servicing['next_due_amount']) }}</p>
                @endif
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.repaid_pct', ['pct' => format_number($servicing['progress_pct'], 0)]) }}</p>
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden mt-2">
                    <div class="h-full {{ $servicing['in_arrears'] ? 'bg-red-500' : 'bg-emerald-500' }}" style="width: {{ $servicing['progress_pct'] }}%"></div>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('site.borrower.schedule', $loan->id) }}" class="bg-gray-900 hover:bg-gray-800 text-white text-xs font-semibold px-4 py-2 rounded-full">{{ __('borrower.loans_page.view_schedule') }}</a>
            <a href="{{ route('site.borrower.payments.create', ['loan' => $loan->id]) }}" class="bg-amber-500 hover:bg-amber-400 text-gray-900 text-xs font-semibold px-4 py-2 rounded-full">{{ __('borrower.loans_page.make_payment') }}</a>
        </div>
    </div>

    {{-- Documents --}}
    @if ($finalContract || $scheduleAnnex)
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
            <h2 class="font-semibold text-gray-900 mb-4">{{ __('borrower.loan_servicing.documents_title') }}</h2>
            <div class="flex flex-wrap gap-3">
                @if ($finalContract?->file_path)
                    <a href="{{ route('site.borrower.loans.final-contract', $loan) }}" target="_blank"
                       class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 px-4 py-2 rounded-lg">
                        {{ __('borrower.loan_servicing.view_final_contract') }}
                    </a>
                @endif
                @if ($scheduleAnnex?->file_path)
                    <a href="{{ route('site.borrower.agreement.download', $scheduleAnnex) }}" target="_blank"
                       class="inline-flex items-center gap-2 text-sm font-semibold text-amber-800 bg-amber-100 hover:bg-amber-200 px-4 py-2 rounded-lg">
                        {{ __('borrower.loan_servicing.view_schedule_pdf') }}
                    </a>
                @endif
            </div>
        </div>
    @endif

    {{-- Actions --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
        <h2 class="font-semibold text-gray-900 mb-4">{{ __('borrower.loan_servicing.actions_title') }}</h2>
        <div class="flex flex-wrap gap-2">
            @if ($canRestructure)
                <a href="{{ route('site.borrower.loans.restructure', $loan) }}"
                   class="inline-flex items-center gap-2 text-sm font-semibold text-amber-900 bg-amber-100 hover:bg-amber-200 px-4 py-2 rounded-lg">
                    {{ __('borrower.loan_actions.restructure_title') }}
                </a>
            @endif
            @if ($canTopUp)
                <a href="{{ route('site.borrower.loans.top-up', $loan) }}"
                   class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-800 bg-emerald-100 hover:bg-emerald-200 px-4 py-2 rounded-lg">
                    {{ __('borrower.loan_actions.top_up_title') }}
                </a>
            @endif
        </div>
    </div>

    @if ($recentRepayments->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
            <h2 class="font-semibold mb-4">{{ __('borrower.loans_page.recent_payments') }}</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($recentRepayments as $payment)
                    <li class="py-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="font-medium">{{ $payment->reference ?? 'Payment' }}</p>
                            <p class="text-xs text-gray-500">{{ optional($payment->paid_at)->format('d M Y, H:i') ?? '—' }}</p>
                        </div>
                        <span class="font-semibold">{{ format_money($payment->amount) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($timeline))
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-900 mb-4">{{ __('borrower.loan_servicing.timeline_title') }}</h2>
            <ul class="space-y-4">
                @foreach ($timeline as $event)
                    @php
                        $toneRing = match ($event['tone']) {
                            'emerald' => 'bg-emerald-500 ring-emerald-100',
                            'sky' => 'bg-sky-500 ring-sky-100',
                            'amber' => 'bg-amber-500 ring-amber-100',
                            'red' => 'bg-red-500 ring-red-100',
                            default => 'bg-gray-400 ring-gray-100',
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
