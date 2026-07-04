<x-site.borrower-layout :title="brand_title(__('borrower.schedule_page.title'))" active="loans" content-width="wide">

    <div class="mb-4">
        @if ($loan)
            <a href="{{ route('site.borrower.loans.show', $loan) }}" class="text-sm font-semibold text-brand hover:underline">{{ __('borrower.loans_page.view_loan') }}</a>
        @else
            <a href="{{ route('site.borrower.loans', ['tab' => 'active']) }}" class="text-sm font-semibold text-brand hover:underline">{{ __('borrower.loans_page.back') }}</a>
        @endif
    </div>

    <x-site.borrower-page-header
        :eyebrow="__('borrower.loans_page.view_schedule')"
        :title="__('borrower.schedule_page.title')"
        :subtitle="$loan ? $loan->loan_number : __('borrower.schedule_page.no_loan')"
    >
        <x-slot:actions>
            @if ($allLoans->count() > 1)
                <form method="GET">
                    <select onchange="window.location='{{ url('/borrower/schedule') }}/'+this.value"
                            class="rounded-xl border-gray-300 ring-1 ring-gray-200/80 focus:ring-brand px-3 py-2 text-sm bg-white/80">
                        @foreach ($allLoans as $l)
                            <option value="{{ $l->id }}" @selected($loan && $loan->id === $l->id)>{{ $l->loan_number }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </x-slot:actions>
    </x-site.borrower-page-header>

    @if (! $loan || $schedule->isEmpty())
        <x-site.empty-state
            icon="📅"
            :title="__('borrower.schedule_page.empty')"
        />
    @else
        <div class="glass-card overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/80 text-gray-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">{{ __('borrower.schedule_page.col_number') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('borrower.schedule_page.col_due_date') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('borrower.schedule_page.col_principal') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('borrower.schedule_page.col_interest') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('borrower.schedule_page.col_total_due') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('borrower.schedule_page.col_paid') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('borrower.schedule_page.col_status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($schedule as $row)
                        @php
                            $isOverdue = $row->status !== 'paid' && \Carbon\Carbon::parse($row->due_date)->isPast();
                            $st = $isOverdue ? 'overdue' : $row->status;
                            $color = match ($st) {
                                'paid'     => 'bg-emerald-100 text-emerald-700',
                                'overdue'  => 'bg-red-100 text-red-700',
                                'upcoming','pending' => 'bg-brand-muted text-brand',
                                default    => 'bg-gray-100 text-gray-700',
                            };
                        @endphp
                        <tr class="hover:bg-brand-muted/20 transition">
                            <td class="px-4 py-3 font-mono text-xs">{{ $row->installment_no }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($row->due_date)->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ format_number($row->principal_due) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ format_number($row->interest_due) }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ format_number($row->total_due) }}</td>
                            <td class="px-4 py-3 text-right text-gray-500 tabular-nums">{{ format_number($row->amount_paid) }}</td>
                            <td class="px-4 py-3 text-center"><span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $color }}">{{ ucfirst($st) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-5 flex justify-end">
            <a href="{{ route('site.borrower.payments.create', ['loan' => $loan->id]) }}" class="bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-2.5 rounded-xl text-sm shadow-sm">{{ __('borrower.schedule_page.pay_next') }}</a>
        </div>
    @endif

</x-site.borrower-layout>
