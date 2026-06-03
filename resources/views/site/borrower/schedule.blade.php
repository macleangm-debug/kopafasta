<x-site.borrower-layout :title="brand_title('Repayment schedule')" active="schedule">

    <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold">Repayment schedule</h1>
            <p class="text-sm text-gray-500">{{ $loan ? $loan->loan_number : 'No loan selected.' }}</p>
        </div>
        @if ($allLoans->count() > 1)
            <form method="GET" class="text-sm">
                <select onchange="window.location='{{ url('/borrower/schedule') }}/'+this.value"
                        class="rounded-lg border-gray-300 ring-1 ring-gray-200 focus:ring-amber-500 px-3 py-2 text-sm">
                    @foreach ($allLoans as $l)
                        <option value="{{ $l->id }}" @selected($loan && $loan->id === $l->id)>{{ $l->loan_number }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if (! $loan || $schedule->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
            <p class="text-gray-500">No repayment schedule yet.</p>
        </div>
    @else
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">#</th>
                        <th class="px-4 py-3 text-left">Due date</th>
                        <th class="px-4 py-3 text-right">Principal</th>
                        <th class="px-4 py-3 text-right">Interest</th>
                        <th class="px-4 py-3 text-right">Total due</th>
                        <th class="px-4 py-3 text-right">Paid</th>
                        <th class="px-4 py-3 text-center">Status</th>
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
                                'upcoming','pending' => 'bg-amber-100 text-amber-700',
                                default    => 'bg-gray-100 text-gray-700',
                            };
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs">{{ $row->installment_no }}</td>
                            <td class="px-4 py-3">{{ \Carbon\Carbon::parse($row->due_date)->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right">{{ format_number($row->principal_due) }}</td>
                            <td class="px-4 py-3 text-right">{{ format_number($row->interest_due) }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ format_number($row->total_due) }}</td>
                            <td class="px-4 py-3 text-right text-gray-500">{{ format_number($row->amount_paid) }}</td>
                            <td class="px-4 py-3 text-center"><span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $color }}">{{ ucfirst($st) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-5 flex justify-end">
            <a href="{{ route('site.borrower.payments') }}" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">Pay next installment →</a>
        </div>
    @endif

</x-site.borrower-layout>
