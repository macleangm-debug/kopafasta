<x-site.borrower-layout :title="brand_title(__('borrower.loan_actions.top_up_title'))" active="loans" content-width="wide">
    <div>
        <a href="{{ route('site.borrower.loans.show', $loan) }}" class="text-sm font-semibold text-brand hover:underline">← {{ $loan->loan_number }}</a>

        <x-site.borrower-page-header
            class="mt-4"
            :title="__('borrower.loan_actions.top_up_title')"
            :subtitle="__('borrower.loan_actions.outstanding_balance', ['amount' => format_money($loan->outstanding_balance)])"
        />

        @if ($blocked)
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm text-amber-900">{{ $blocked }}</div>
        @else
            <form method="post" action="{{ route('site.borrower.loans.top-up.submit', $loan) }}" class="glass-card p-6 space-y-5"
                  @submit.prevent="window.confirmForm($el, {
                      title: @js(__('borrower.loan_actions.top_up_confirm_title')),
                      message: @js(__('borrower.loan_actions.top_up_confirm_message')),
                      confirmLabel: @js(__('borrower.loan_actions.submit_top_up')),
                      tone: 'confirm'
                  })">
                @csrf
                <p class="text-sm text-gray-700">{{ __('borrower.loan_actions.top_up_hint') }}</p>

                <div class="rounded-xl bg-brand-muted ring-1 ring-brand/15 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_actions.available_top_up') }}</p>
                    <p class="text-2xl font-bold text-brand mt-1 tabular-nums">{{ format_money($available) }}</p>
                </div>

                <div>
                    <x-site.numeric-input name="requested_amount" :label="__('borrower.loan_actions.requested_amount_label')" :value="old('requested_amount')" :required="true" min="1000" max="{{ (int) $available }}" step="1000" />
                    @error('requested_amount')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.loan_actions.reason_label') }}</label>
                    <textarea name="reason" rows="4" required maxlength="500"
                              class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand">{{ old('reason') }}</textarea>
                    @error('reason')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    @error('top_up')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <p class="text-xs text-gray-500">{{ __('borrower.loan_actions.top_up_review_note') }}</p>

                <button type="submit" class="inline-flex bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-2.5 rounded-xl text-sm">
                    {{ __('borrower.loan_actions.submit_top_up') }}
                </button>
            </form>
        @endif
    </div>
</x-site.borrower-layout>
