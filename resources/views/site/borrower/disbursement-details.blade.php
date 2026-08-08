<x-site.borrower-layout :title="brand_title(__('borrower.disbursement_details.page_title'))" active="loans" content-width="wide">

    <div>
        <a href="{{ route('site.borrower.application', $application) }}" class="text-sm text-amber-700 hover:underline">&larr; {{ __('borrower.disbursement_details.back') }}</a>

        @if (session('status'))
            <div class="mt-3 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mt-3 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mt-3 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mt-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h1 class="text-xl font-bold text-gray-900">{{ __('borrower.disbursement_details.title') }}</h1>
            <p class="text-sm text-gray-600 mt-1">{{ __('borrower.disbursement_details.select_account_subtitle') }}</p>

            <div class="mt-4 rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3 text-sm">
                <span class="text-gray-500">{{ __('borrower.disbursement_details.loan_amount') }}:</span>
                <span class="font-bold text-gray-900 ml-1">{{ format_money($loanAmount) }}</span>
            </div>

            @if (! $paymentComplete)
                <div class="mt-4 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
                    {{ __('borrower.disbursement_details.complete_profile_first') }}
                    <a href="{{ route('site.borrower.profile', ['section' => 'payment', 'edit' => 1, 'return' => route('site.borrower.application.disbursement-details', $application)]) }}"
                       class="font-semibold underline ml-1">{{ __('borrower.disbursement_details.add_payment_details') }}</a>
                </div>
            @else
                <form method="POST" action="{{ route('site.borrower.application.disbursement-details.confirm', $application) }}" class="mt-6 space-y-4"
                      @submit.prevent="window.confirmForm($el, {
                          title: @js(__('borrower.disbursement_details.confirm_title')),
                          message: @js(__('borrower.disbursement_details.confirm_message')),
                          confirmLabel: @js(__('borrower.disbursement_details.confirm_account_button')),
                          tone: 'confirm'
                      })">
                    @csrf
                    <p class="text-sm font-semibold text-gray-900">{{ __('borrower.disbursement_details.select_account') }}</p>
                    <p class="text-xs text-gray-500">{{ __('borrower.payment_details.name_must_match', ['name' => $borrowerLegalName]) }}</p>

                    <div class="space-y-3">
                        @foreach ($accounts as $account)
                            <label class="flex items-start gap-3 rounded-xl ring-1 ring-gray-200 px-4 py-3 cursor-pointer hover:bg-amber-50/50 has-[:checked]:ring-amber-400 has-[:checked]:bg-amber-50/60">
                                <input type="radio" name="disbursement_account_id" value="{{ $account->id }}" class="mt-1 text-amber-600" @checked(old('disbursement_account_id', $accounts->firstWhere('is_default', true)?->id ?? $accounts->first()?->id) == $account->id) required>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-gray-900">{{ $detailsService->accountLabel($account) }}</span>
                                    <span class="block text-xs text-gray-500 mt-0.5">{{ $account->account_name }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <button type="submit"
                                class="inline-flex justify-center items-center bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-full text-sm">
                            {{ __('borrower.disbursement_details.confirm_account_button') }}
                        </button>
                        <a href="{{ route('site.borrower.profile', ['section' => 'payment', 'edit' => 1, 'return' => route('site.borrower.application.disbursement-details', $application)]) }}"
                           class="text-sm font-semibold text-amber-700 hover:text-amber-800">
                            {{ __('borrower.disbursement_details.manage_accounts') }}
                        </a>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-site.borrower-layout>
