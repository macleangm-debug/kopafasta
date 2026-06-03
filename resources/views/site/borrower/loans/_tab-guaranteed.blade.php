<div class="mb-6">
    <h2 class="text-lg font-semibold mb-1">{{ __('borrower.loans_page.tab_guaranteed') }}</h2>
    <p class="text-sm text-gray-500">{{ __('borrower.loans_page.guaranteed_hint') }}</p>
</div>

@if (($guaranteedLinks ?? collect())->isEmpty())
    <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center">
        <p class="text-gray-500">{{ __('borrower.loans_page.no_guaranteed') }}</p>
    </div>
@else
    <div class="space-y-4">
        @foreach ($guaranteedLinks as $link)
            @php
                $app = $link->application;
                $borrower = $app?->customer;
                $loan = $app?->loan;
            @endphp
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex items-start justify-between gap-3 mb-3 flex-wrap">
                    <div>
                        <p class="text-xs text-gray-500">{{ $app?->product?->name ?? __('borrower.loans_page.loan_application') }}</p>
                        <p class="font-semibold">{{ $borrower?->full_name ?? __('borrower.loans_page.borrower') }}</p>
                        <p class="text-xs text-gray-500 mt-0.5 font-mono">{{ $app?->application_number }}</p>
                    </div>
                    <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-sky-100 text-sky-800">{{ __('borrower.loans_page.guarantor_badge') }}</span>
                </div>
                <div class="grid sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.requested') }}</p>
                        <p class="font-semibold">{{ format_money($app?->requested_amount ?? 0) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.application_status') }}</p>
                        <p class="font-semibold capitalize">{{ str_replace('_', ' ', $app?->status ?? '—') }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_status') }}</p>
                        <p class="font-semibold capitalize">{{ $loan ? ucfirst($loan->status) : __('borrower.loans_page.not_disbursed') }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
