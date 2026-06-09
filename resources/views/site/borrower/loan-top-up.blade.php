<x-site.borrower-layout :title="brand_title(__('borrower.loan_actions.top_up_title'))" active="loans">
    <div class="max-w-2xl mx-auto">
        <a href="{{ route('site.borrower.loans', ['tab' => 'active']) }}" class="text-sm font-semibold text-amber-700 hover:text-amber-800">← {{ __('borrower.loans_page.tab_active') }}</a>
        <h1 class="text-2xl font-bold mt-4 mb-2">{{ __('borrower.loan_actions.top_up_title') }}</h1>
        <p class="text-sm text-gray-500 mb-6">{{ $loan->loan_number }} · {{ format_money($loan->outstanding_balance) }} outstanding</p>

        @if ($blocked)
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm text-amber-900">{{ $blocked }}</div>
        @else
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
                <p class="text-sm text-gray-700">{{ __('borrower.loan_actions.top_up_hint') }}</p>
                <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-700 font-semibold">{{ __('borrower.loan_actions.available_top_up') }}</p>
                    <p class="text-2xl font-bold text-emerald-900 mt-1">{{ format_money($available) }}</p>
                </div>
                <p class="text-xs text-gray-500">Top-up requests are assessed against repayment performance, income, assets, and guarantor support.</p>
                <a href="{{ route('site.borrower.support') }}" class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">Contact support to request</a>
            </div>
        @endif
    </div>
</x-site.borrower-layout>
