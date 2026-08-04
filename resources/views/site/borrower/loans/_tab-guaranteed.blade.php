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
    <div class="grid gap-4 sm:grid-cols-2">
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
                $detailUrl = route('site.borrower.guaranteed.show', $row->link);
            @endphp
            <div class="glass-card p-5 ring-1 ring-brand/10 {{ ($row->is_terminal ?? false) ? 'opacity-80' : '' }}">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ $productName }}</p>
                        <p class="text-lg font-bold text-gray-900 tracking-tight mt-0.5 leading-snug">{{ $borrowerName }}</p>
                        <p class="font-mono text-xs text-gray-500 mt-1">{{ $row->reference }}</p>
                    </div>
                    <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $loanTone }}">
                        {{ $loanStatuses[$row->loan_status] ?? ucfirst((string) $row->loan_status) }}
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="rounded-xl bg-gray-50 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.outstanding') }}</p>
                        <p class="font-semibold text-sm mt-0.5">{{ format_money($row->outstanding ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.next_due') }}</p>
                        <p class="font-semibold text-sm mt-0.5">{{ $row->next_due_date ? \Carbon\Carbon::parse($row->next_due_date)->format('d M Y') : '—' }}</p>
                    </div>
                </div>
                <a href="{{ $detailUrl }}"
                   class="inline-flex items-center justify-center w-full sm:w-auto font-bold px-5 py-2.5 rounded-xl text-sm bg-brand-gold hover:bg-yellow-400 text-brand shadow-sm">
                    {{ __('borrower.guaranteed.view_request') }}
                </a>
            </div>
        @endforeach
    </div>
@endif
