@php
    $rows = $guaranteedLinks ?? collect();
    $viewMode = $viewMode ?? 'cards';
    $loanStatuses = __('borrower.loans_page.loan_statuses');
@endphp

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h2 class="text-lg font-bold text-gray-900 mb-1">{{ __('borrower.loans_page.tab_guaranteed') }}</h2>
        <p class="text-sm text-gray-500">{{ __('borrower.loans_page.guaranteed_hint') }}</p>
    </div>
    <div class="inline-flex rounded-xl ring-1 ring-gray-200/80 bg-white/80 p-0.5 text-xs">
        <a href="{{ route('site.borrower.loans', ['tab' => 'guaranteed', 'view' => 'cards']) }}"
           class="px-3 py-1.5 rounded-lg font-semibold {{ $viewMode === 'cards' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-brand-muted/50' }}">
            {{ __('borrower.applications_list.cards') }}
        </a>
        <a href="{{ route('site.borrower.loans', ['tab' => 'guaranteed', 'view' => 'table']) }}"
           class="px-3 py-1.5 rounded-lg font-semibold {{ $viewMode === 'table' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-brand-muted/50' }}">
            {{ __('borrower.applications_list.table') }}
        </a>
    </div>
</div>

@if ($rows->isEmpty())
    <x-site.empty-state
        icon="🛡"
        :title="__('borrower.loans_page.no_guaranteed')"
        :description="__('borrower.loans_page.guaranteed_empty_desc')"
    />
@elseif ($viewMode === 'table')
    <div class="glass-card overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50/80 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('borrower.loans_page.borrower') }}</th>
                    <th class="px-4 py-3">{{ __('borrower.guarantor_invite.product_label') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('borrower.loans_page.outstanding') }}</th>
                    <th class="px-4 py-3">{{ __('borrower.loans_page.loan_status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($rows as $row)
                    @php
                        $borrowerName = $row->borrower?->legalDisplayName() ?? __('borrower.loans_page.borrower');
                        $productName = $row->product?->name ?? __('borrower.guarantor.loan');
                        $loanTone = match ($row->loan_status) {
                            'active', 'disbursed' => 'bg-emerald-100 text-emerald-700',
                            'arrears' => 'bg-red-100 text-red-700',
                            'closed' => 'bg-gray-100 text-gray-700',
                            default => 'bg-gray-100 text-gray-600',
                        };
                    @endphp
                    <tr class="hover:bg-brand-muted/20 {{ ($row->is_terminal ?? false) ? 'opacity-75' : '' }}">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-gray-900">{{ $borrowerName }}</p>
                            <p class="font-mono text-xs text-gray-500">{{ $row->reference }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $productName }}</td>
                        <td class="px-4 py-3 text-right font-mono tabular-nums">{{ format_money($row->outstanding ?? 0) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex text-xs font-semibold rounded-full px-2 py-0.5 {{ $loanTone }}">{{ $loanStatuses[$row->loan_status] ?? ucfirst((string) $row->loan_status) }}</span>
                            @if ($row->in_arrears)
                                <span class="ml-1 inline-flex text-xs font-semibold rounded-full px-2 py-0.5 bg-red-100 text-red-800">{{ __('borrower.guaranteed.in_arrears') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('site.borrower.guaranteed.show', $row->link) }}" class="text-brand font-semibold hover:underline">{{ __('borrower.guaranteed.view_details') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="space-y-4">
        @foreach ($rows as $row)
            @php
                $borrowerName = $row->borrower?->legalDisplayName() ?? __('borrower.loans_page.borrower');
                $productName = $row->product?->name ?? __('borrower.guarantor.loan');
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
                            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.outstanding') }}</p>
                            <p class="font-semibold">{{ format_money($row->outstanding ?? 0) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_status') }}</p>
                            <span class="inline-flex text-xs font-semibold rounded-full px-2 py-0.5 {{ $loanTone }}">{{ $loanStatuses[$row->loan_status] ?? ucfirst((string) $row->loan_status) }}</span>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.next_due') }}</p>
                            <p class="font-semibold">{{ $row->next_due_date ? \Carbon\Carbon::parse($row->next_due_date)->format('d M Y') : '—' }}</p>
                        </div>
                    </div>

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
                </a>

                <a href="{{ route('site.borrower.guaranteed.show', $row->link) }}"
                   class="mt-3 inline-flex text-sm font-semibold text-brand">{{ __('borrower.guaranteed.view_details') }} →</a>
            </div>
        @endforeach
    </div>
@endif
