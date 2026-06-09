<x-site.borrower-layout :title="brand_title(__('borrower.loan_actions.restructure_title'))" active="loans">
    <div class="max-w-2xl mx-auto">
        <a href="{{ route('site.borrower.loans', ['tab' => 'active']) }}" class="text-sm font-semibold text-amber-700 hover:text-amber-800">← {{ __('borrower.loans_page.tab_active') }}</a>
        <h1 class="text-2xl font-bold mt-4 mb-2">{{ __('borrower.loan_actions.restructure_title') }}</h1>
        <p class="text-sm text-gray-500 mb-6">{{ $loan->loan_number }} · {{ $loan->product?->name }}</p>

        @if ($blocked)
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm text-amber-900">{{ $blocked }}</div>
        @else
            <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4 text-sm">
                <p class="text-gray-700">{{ __('borrower.loan_actions.restructure_hint') }}</p>
                <ul class="list-disc ml-5 space-y-1 text-gray-600">
                    <li>Extend term</li>
                    <li>Reduce instalment</li>
                    <li>Payment holiday</li>
                    <li>Interest adjustment</li>
                </ul>
                <p class="text-xs text-gray-500">Submit a request and our team will review your loan before generating a new schedule.</p>
                <a href="{{ route('site.borrower.support') }}" class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">Contact support to request</a>
            </div>
        @endif
    </div>
</x-site.borrower-layout>
