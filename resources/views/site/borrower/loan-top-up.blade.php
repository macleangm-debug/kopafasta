<x-site.borrower-layout :title="brand_title(__('borrower.loan_actions.top_up_title'))" active="loans" content-width="wide">
    <div>
        <a href="{{ route('site.borrower.loans', ['tab' => 'active']) }}" class="text-sm font-semibold text-amber-700 hover:text-amber-800">← {{ __('borrower.loans_page.tab_active') }}</a>
        <h1 class="text-2xl font-bold mt-4 mb-2">{{ __('borrower.loan_actions.top_up_title') }}</h1>
        <p class="text-sm text-gray-500 mb-6">{{ $loan->loan_number }} · {{ __('borrower.loan_actions.outstanding_balance', ['amount' => format_money($loan->outstanding_balance)]) }}</p>

        @if ($blocked)
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm text-amber-900">{{ $blocked }}</div>
        @else
            <form method="post" action="{{ route('site.borrower.loans.top-up.submit', $loan) }}" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
                @csrf
                <p class="text-sm text-gray-700">{{ __('borrower.loan_actions.top_up_hint') }}</p>

                <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-700 font-semibold">{{ __('borrower.loan_actions.available_top_up') }}</p>
                    <p class="text-2xl font-bold text-emerald-900 mt-1">{{ format_money($available) }}</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.loan_actions.requested_amount_label') }}</label>
                    <input type="number" name="requested_amount" min="1000" max="{{ (int) $available }}" step="1000" required
                           value="{{ old('requested_amount') }}"
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    @error('requested_amount')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.loan_actions.reason_label') }}</label>
                    <textarea name="reason" rows="4" required maxlength="500"
                              class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">{{ old('reason') }}</textarea>
                    @error('reason')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    @error('top_up')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <p class="text-xs text-gray-500">{{ __('borrower.loan_actions.top_up_review_note') }}</p>

                <button type="submit" class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.loan_actions.submit_top_up') }}
                </button>
            </form>
        @endif
    </div>
</x-site.borrower-layout>
