@php $loans = $loans ?? collect(); @endphp

<section>
    <h2 class="text-lg font-semibold mb-1">{{ __('borrower.loans_page.section_active') }}</h2>
    <p class="text-sm text-gray-500 mb-5">{{ __('borrower.loans_page.active_hint') }}</p>

    @if ($loans->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50/50 px-5 py-8 text-center text-sm text-gray-500">
            {{ __('borrower.loans_page.no_active_loans') }}
        </div>
    @else
        <div class="space-y-4">
            @foreach ($loans as $loan)
                @php
                    $statusBadge = match ($loan->status) {
                        'active','disbursed' => 'bg-emerald-100 text-emerald-700',
                        'arrears'            => 'bg-red-100 text-red-700',
                        default              => 'bg-amber-100 text-amber-700',
                    };
                @endphp
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $loan->product->name ?? __('borrower.apply.product_type.general') }}</p>
                            <p class="font-mono text-xs text-gray-500 mt-0.5">{{ $loan->loan_number }}</p>
                        </div>
                        <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $statusBadge }}">{{ ucfirst($loan->status) }}</span>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-4 mb-4 text-sm">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.outstanding') }}</p>
                            <p class="font-semibold">{{ format_money($loan->outstanding_balance) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.next_payment') }}</p>
                            <p class="font-semibold">{{ $loan->next_due_date ? \Carbon\Carbon::parse($loan->next_due_date)->format('d M Y') : '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_status') }}</p>
                            <p class="font-semibold">{{ ucfirst($loan->status) }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="{{ route('site.borrower.schedule', $loan->id) }}" class="text-xs font-semibold text-amber-700 hover:underline">{{ __('borrower.loans_page.view_schedule') }}</a>
                        <span class="text-gray-300">·</span>
                        <a href="{{ route('site.borrower.payments') }}" class="text-xs font-semibold text-gray-700 hover:underline">{{ __('borrower.loans_page.make_payment') }}</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
