<x-site.borrower-layout :title="brand_title(__('borrower.guaranteed.detail_title'))" active="loans" portalMode="guarantor" content-width="wide">

    <div>
        <a href="{{ route('site.borrower.loans', ['tab' => $listTab ?? 'guaranteed']) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-amber-700 hover:underline mb-4">
            ← {{ ($listTab ?? 'guaranteed') === 'guarantor'
                ? __('borrower.guaranteed.back_to_requests')
                : __('borrower.guaranteed.back_to_list') }}
        </a>

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        @php
            $borrowerName = $row->borrower?->legalDisplayName() ?? __('borrower.loans_page.borrower');
            $productName = $row->product?->name ?? __('borrower.guarantor.loan');
            $appStatus = $row->application_status;
            $appBadge = match ($appStatus['tone'] ?? 'gray') {
                'emerald' => 'bg-emerald-100 text-emerald-700',
                'red' => 'bg-red-100 text-red-700',
                'amber' => 'bg-amber-100 text-amber-700',
                default => 'bg-sky-100 text-sky-700',
            };
        @endphp

        <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
            <div>
                <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">{{ __('borrower.loans_page.guarantor_badge') }}</p>
                <h1 class="text-2xl font-bold text-gray-900">{{ $borrowerName }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $productName }} · <span class="font-mono">{{ $row->reference }}</span></p>
            </div>
            <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1.5 {{ $appBadge }}">{{ $row->stage_label ?? ($appStatus['label'] ?? '—') }}</span>
        </div>

        @if ($row->needs_guarantor_profile ?? false)
            <div class="mb-6 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <p class="font-semibold text-amber-900">{{ __('borrower.guaranteed.profile_block_title') }}</p>
                    <p class="text-sm text-amber-800 mt-1">{{ __('borrower.guaranteed.profile_block_body', ['percent' => $row->profile_percent ?? 0]) }}</p>
                    @if ($row->pending_hint)
                        <p class="text-xs text-amber-700 mt-2">{{ $row->pending_hint }}</p>
                    @endif
                </div>
                <a href="{{ $row->profile_url }}"
                   class="inline-flex shrink-0 justify-center rounded-xl bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2.5 text-sm">
                    {{ __('borrower.guarantor.complete_profile') }}
                </a>
            </div>
        @elseif ($row->pending_hint && ! ($row->is_disbursed ?? false))
            <div class="mb-6 rounded-2xl bg-sky-50 ring-1 ring-sky-200 px-5 py-4 text-sm text-sky-900">
                <p class="font-semibold">{{ __('borrower.guaranteed.whats_pending_title') }}</p>
                <p class="mt-1">{{ $row->pending_hint }}</p>
            </div>
        @endif

        @if ($row->in_arrears)
            <div class="mb-6 rounded-2xl bg-red-50 ring-1 ring-red-200 px-5 py-4 text-sm text-red-800">
                <p class="font-semibold">{{ __('borrower.guaranteed.arrears_alert_title') }}</p>
                <p class="mt-1">{{ __('borrower.guaranteed.arrears_alert_body', ['balance' => format_money($row->outstanding ?? 0)]) }}</p>
            </div>
        @endif

        <div class="grid sm:grid-cols-3 gap-3 mb-6">
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_amount') }}</p>
                <p class="text-lg font-bold">{{ format_money($row->amount) }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.outstanding') }}</p>
                <p class="text-lg font-bold">{{ $row->loan ? format_money($row->outstanding) : '—' }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.next_due') }}</p>
                <p class="text-lg font-bold">{{ $row->next_due_date ? \Illuminate\Support\Carbon::parse($row->next_due_date)->format('d M Y') : '—' }}</p>
                @if (($row->days_remaining ?? null) !== null)
                    <p class="text-xs mt-1 {{ $row->days_remaining < 0 ? 'text-red-600' : 'text-gray-500' }}">
                        {{ $row->days_remaining < 0
                            ? __('borrower.loans_page.days_overdue', ['days' => abs($row->days_remaining)])
                            : __('borrower.loans_page.days_left', ['days' => $row->days_remaining]) }}
                    </p>
                @endif
            </div>
        </div>

        @if ($row->loan && ($row->amount_in_arrears ?? 0) > 0)
            <p class="text-sm text-red-700 mb-6">{{ __('borrower.loans_page.arrears_amount', ['amount' => format_money($row->amount_in_arrears), 'count' => $row->servicing['overdue_installments'] ?? 1]) }}</p>
        @endif

        @if (! empty($timeline['steps']))
            <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
                <h2 class="font-semibold mb-4">{{ __('borrower.guaranteed.progress_title') }}</h2>
                <div class="mb-4 h-2 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full bg-amber-500 rounded-full" style="width: {{ min(100, max(0, (int) ($timeline['percent'] ?? 0))) }}%"></div>
                </div>
                <ol class="space-y-3">
                    @foreach ($timeline['steps'] as $step)
                        <li class="flex items-start gap-3 text-sm">
                            <span class="mt-0.5 shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold {{ $step['complete'] ? 'bg-emerald-100 text-emerald-700' : ($step['current'] ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-400') }}">
                                {{ $step['complete'] ? '✓' : '·' }}
                            </span>
                            <span class="{{ $step['current'] ? 'font-semibold text-gray-900' : 'text-gray-600' }}">{{ $step['label'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if ($row->loan)
            <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
                <h2 class="font-semibold mb-1">{{ __('borrower.guaranteed.repayment_progress') }}</h2>
                <p class="text-sm text-gray-500 mb-4">{{ __('borrower.guaranteed.repayment_progress_hint') }}</p>
                <div class="flex items-center justify-between text-xs text-gray-500 mb-2">
                    <span>{{ __('borrower.loans_page.repaid_pct', ['pct' => format_number($row->repaid_percent, 0)]) }}</span>
                    @if ($row->next_due_date)
                        <span>{{ __('borrower.guaranteed.next_due', ['date' => \Carbon\Carbon::parse($row->next_due_date)->format('d M Y')]) }}</span>
                    @endif
                </div>
                <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden mb-5">
                    <div class="h-full {{ $row->in_arrears ? 'bg-red-500' : 'bg-emerald-500' }}" style="width: {{ min(100, max(0, $row->repaid_percent)) }}%"></div>
                </div>

                @if ($row->schedule->isNotEmpty())
                    <div class="overflow-x-auto rounded-xl border border-gray-100">
                        <table class="w-full text-sm">
                            <thead class="text-xs uppercase text-gray-500 bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="text-left px-4 py-2">#</th>
                                    <th class="text-left px-4 py-2">{{ __('borrower.guaranteed.due_date') }}</th>
                                    <th class="text-right px-4 py-2">{{ __('borrower.guaranteed.installment') }}</th>
                                    <th class="text-right px-4 py-2">{{ __('borrower.guaranteed.paid') }}</th>
                                    <th class="text-center px-4 py-2">{{ __('borrower.guaranteed.status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($row->schedule as $installment)
                                    @php
                                        $isOverdue = $installment->status !== 'paid' && \Carbon\Carbon::parse($installment->due_date)->isPast();
                                        $st = $isOverdue ? 'overdue' : $installment->status;
                                        $installmentStatuses = __('borrower.guaranteed.installment_statuses');
                                        $color = match ($st) {
                                            'paid' => 'bg-emerald-100 text-emerald-700',
                                            'overdue' => 'bg-red-100 text-red-700',
                                            default => 'bg-amber-100 text-amber-700',
                                        };
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2.5 font-mono text-xs">{{ $installment->installment_no }}</td>
                                        <td class="px-4 py-2.5">{{ \Carbon\Carbon::parse($installment->due_date)->format('d M Y') }}</td>
                                        <td class="px-4 py-2.5 text-right font-semibold">{{ format_number($installment->total_due) }}</td>
                                        <td class="px-4 py-2.5 text-right text-gray-500">{{ format_number($installment->amount_paid) }}</td>
                                        <td class="px-4 py-2.5 text-center">
                                            <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ $color }}">{{ $installmentStatuses[$st] ?? ucfirst($st) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        @if ($row->restructure || $row->top_up)
            <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
                <h2 class="font-semibold mb-4">{{ __('borrower.guaranteed.modifications_title') }}</h2>
                @php
                    $modificationStatuses = __('borrower.guaranteed.modification_statuses');
                @endphp
                <dl class="space-y-4 text-sm">
                    @if ($row->restructure)
                        <div class="rounded-xl bg-gray-50 px-4 py-3">
                            <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.loan_actions.restructure') }}</dt>
                            <dd class="mt-1 capitalize">{{ str_replace('_', ' ', $row->restructure->restructure_type) }} · <span class="font-semibold">{{ $modificationStatuses[$row->restructure->status] ?? ucfirst($row->restructure->status) }}</span></dd>
                        </div>
                    @endif
                    @if ($row->top_up)
                        <div class="rounded-xl bg-gray-50 px-4 py-3">
                            <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.loan_actions.top_up') }}</dt>
                            <dd class="mt-1">{{ format_money($row->top_up->requested_amount) }} · <span class="font-semibold">{{ $modificationStatuses[$row->top_up->status] ?? ucfirst($row->top_up->status) }}</span></dd>
                        </div>
                    @endif
                </dl>
            </div>
        @endif

        <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 text-sm text-amber-900">
            <p class="font-semibold">{{ __('borrower.guaranteed.responsibility_title') }}</p>
            <p class="mt-1">{{ __('borrower.guaranteed.responsibility_body') }}</p>
        </div>
    </div>

</x-site.borrower-layout>
