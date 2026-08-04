<div class="mb-6">
    <h2 class="text-lg font-semibold mb-1">{{ __('borrower.loans_page.tab_guaranteed') }}</h2>
    <p class="text-sm text-gray-500">{{ __('borrower.loans_page.guaranteed_hint') }}</p>
</div>

@if (($guaranteedLinks ?? collect())->isEmpty())
    <div class="glass-card p-10 text-center">
        <p class="text-gray-500">{{ __('borrower.loans_page.no_guaranteed') }}</p>
    </div>
@else
    <div class="space-y-4">
        @foreach ($guaranteedLinks as $row)
            @php
                $loanStatuses = __('borrower.loans_page.loan_statuses');
                $borrowerName = $row->borrower?->legalDisplayName() ?? __('borrower.loans_page.borrower');
                $productName = $row->product?->name ?? __('borrower.guarantor.loan');
                $appTone = match ($row->application_status['tone'] ?? 'gray') {
                    'emerald' => 'bg-emerald-100 text-emerald-800',
                    'red' => 'bg-red-100 text-red-800',
                    'amber' => 'bg-brand-muted text-brand',
                    'orange' => 'bg-orange-100 text-orange-800',
                    default => 'bg-sky-100 text-sky-800',
                };
                $loanTone = match ($row->loan_status) {
                    'active', 'disbursed' => 'bg-emerald-100 text-emerald-700',
                    'arrears' => 'bg-red-100 text-red-700',
                    'closed' => 'bg-gray-100 text-gray-700',
                    default => 'bg-gray-100 text-gray-600',
                };
                $cardClass = ($row->is_terminal ?? false)
                    ? 'opacity-75 ring-1 ring-gray-200'
                    : 'hover:ring-2 hover:ring-brand/20';
            @endphp
            <div class="glass-card p-6 transition-all {{ $cardClass }}">
                <a href="{{ route('site.borrower.guaranteed.show', $row->link) }}" class="block group">
                    <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
                        <div>
                            <p class="text-xs text-gray-500">{{ $productName }}</p>
                            <p class="font-semibold text-gray-900 group-hover:text-brand">{{ $borrowerName }}</p>
                            <p class="text-xs text-gray-500 mt-0.5 font-mono">{{ $row->reference }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-sky-100 text-sky-800">{{ __('borrower.loans_page.guarantor_badge') }}</span>
                            @if ($row->in_arrears)
                                <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-red-100 text-red-800">{{ __('borrower.guaranteed.in_arrears') }}</span>
                            @endif
                            @if ($row->is_terminal ?? false)
                                <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-gray-100 text-gray-700">{{ __('borrower.guaranteed.outcome_closed') }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-4 gap-4 text-sm mb-4">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_amount') }}</p>
                            <p class="font-semibold">{{ format_money($row->amount) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.guaranteed.current_step') }}</p>
                            <span class="inline-flex text-xs font-semibold rounded-full px-2 py-0.5 {{ $appTone }}">{{ $row->stage_label ?? ($row->application_status['label'] ?? '—') }}</span>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_status') }}</p>
                            @if ($row->loan)
                                <span class="inline-flex text-xs font-semibold rounded-full px-2 py-0.5 {{ $loanTone }}">{{ $loanStatuses[$row->loan_status] ?? ucfirst($row->loan_status) }}</span>
                            @else
                                <p class="text-gray-600">{{ __('borrower.loans_page.not_disbursed') }}</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.outstanding') }}</p>
                            <p class="font-semibold">{{ $row->loan ? format_money($row->outstanding) : '—' }}</p>
                        </div>
                    </div>

                    @if ($row->loan)
                        <div class="mb-2">
                            <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                                <span>{{ __('borrower.loans_page.repaid_pct', ['pct' => format_number($row->repaid_percent, 0)]) }}</span>
                                @if ($row->next_due_date)
                                    <span>{{ __('borrower.guaranteed.next_due', ['date' => \Carbon\Carbon::parse($row->next_due_date)->format('d M Y')]) }}</span>
                                @endif
                            </div>
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full {{ $row->in_arrears ? 'bg-red-500' : 'bg-emerald-500' }}" style="width: {{ min(100, max(0, $row->repaid_percent)) }}%"></div>
                            </div>
                        </div>
                    @endif
                </a>

                @if ($row->needs_guarantor_profile ?? false)
                    <div class="mt-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-amber-900">{{ __('borrower.guaranteed.profile_block_title') }}</p>
                            <p class="text-xs text-amber-800 mt-0.5">{{ __('borrower.guaranteed.profile_block_body', ['percent' => $row->profile_percent ?? 0]) }}</p>
                        </div>
                        <a href="{{ $row->profile_url }}"
                           class="inline-flex shrink-0 justify-center rounded-xl bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 text-sm">
                            {{ __('borrower.guarantor.complete_profile') }}
                        </a>
                    </div>
                @else
                    <a href="{{ route('site.borrower.guaranteed.show', $row->link) }}"
                       class="mt-3 inline-flex text-sm font-semibold text-brand">{{ __('borrower.guaranteed.view_details') }} →</a>
                @endif
            </div>
        @endforeach
    </div>
@endif
