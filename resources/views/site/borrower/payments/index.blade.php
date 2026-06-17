<x-site.borrower-layout :title="brand_title(__('borrower.payments_page.title'))" active="payments" content-width="wide">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold mb-1">{{ __('borrower.payments_page.title') }}</h1>
            <p class="text-sm text-gray-500">{{ __('borrower.payments_page.subtitle') }}</p>
        </div>
        @if ($loans->isNotEmpty())
            <a href="{{ route('site.borrower.payments.create') }}"
               class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                {{ __('borrower.payments_page.make_repayment') }}
            </a>
        @endif
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($entries->isEmpty())
        <x-site.empty-state
            icon="💳"
            :title="__('borrower.payments_page.empty_title')"
            :description="$loans->isEmpty()
                ? __('borrower.payments_page.empty_desc_no_loans')
                : __('borrower.payments_page.empty_desc_has_loans')"
            :action-label="$loans->isNotEmpty() ? __('borrower.payments_page.empty_action_repayment') : __('borrower.payments_page.empty_action_products')"
            :action-url="$loans->isNotEmpty() ? route('site.borrower.payments.create') : route('site.borrower.dashboard')"
        />
    @else
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('borrower.payments_page.col_date') }}</th>
                            <th class="px-4 py-3">{{ __('borrower.payments_page.col_reference') }}</th>
                            <th class="px-4 py-3">{{ __('borrower.payments_page.col_type') }}</th>
                            <th class="px-4 py-3">{{ __('borrower.payments_page.col_amount') }}</th>
                            <th class="px-4 py-3">{{ __('borrower.payments_page.col_status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($entries as $entry)
                            <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ $entry['url'] }}'">
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $entry['date']?->format('d-M-Y') }}</td>
                                <td class="px-4 py-3 font-mono text-xs font-semibold text-amber-800">{{ $entry['reference'] }}</td>
                                <td class="px-4 py-3">{{ $entry['type_label'] }}</td>
                                <td class="px-4 py-3 font-medium">{{ format_money($entry['amount']) }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $badge = match ($entry['status']) {
                                            'verified', 'paid' => 'bg-emerald-50 text-emerald-700',
                                            'rejected', 'cancelled' => 'bg-red-50 text-red-700',
                                            'clarification_requested', 'awaiting_payout' => 'bg-sky-50 text-sky-700',
                                            default => 'bg-amber-50 text-amber-800',
                                        };
                                    @endphp
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">
                                        {{ $entry['status_label'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</x-site.borrower-layout>
