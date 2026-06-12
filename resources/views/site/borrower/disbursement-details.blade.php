<x-site.borrower-layout :title="brand_title(__('borrower.disbursement_details.page_title'))" active="loans">

    <div class="max-w-2xl mx-auto">
        <a href="{{ route('site.borrower.application', $application) }}" class="text-sm text-amber-700 hover:underline">&larr; {{ __('borrower.disbursement_details.back') }}</a>

        @if (session('status'))
            <div class="mt-3 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mt-3 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="mt-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h1 class="text-xl font-bold text-gray-900">{{ __('borrower.disbursement_details.title') }}</h1>
            <p class="text-sm text-gray-600 mt-1">{{ __('borrower.disbursement_details.subtitle') }}</p>

            <div class="mt-6 rounded-xl bg-gray-50 ring-1 ring-gray-100 p-5">
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.disbursement_details.loan_amount') }}</dt>
                        <dd class="font-bold text-gray-900 mt-1 text-lg">{{ format_money($loanAmount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.disbursement_details.method') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-1">{{ $detailsService->methodLabel($snapshot['method'] ?? null) }}</dd>
                    </div>
                    @foreach ($detailsService->displayLines($snapshot) as $label => $value)
                        @if (! in_array($label, [__('borrower.payment_details.method')], true))
                            <div>
                                <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ $label }}</dt>
                                <dd class="font-semibold text-gray-900 mt-1">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>

                <div class="mt-4 pt-4 border-t border-gray-200">
                    <a href="{{ route('site.borrower.profile', ['section' => 'payment', 'edit' => 1, 'return' => route('site.borrower.application.disbursement-details', $application)]) }}"
                       class="inline-flex items-center text-sm font-semibold text-amber-700 hover:text-amber-800">
                        {{ __('borrower.disbursement_details.change') }} &rarr;
                    </a>
                </div>
            </div>

            @if (! $paymentComplete)
                <div class="mt-4 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
                    {{ __('borrower.disbursement_details.complete_profile_first') }}
                    <a href="{{ route('site.borrower.profile', ['section' => 'payment', 'edit' => 1, 'return' => route('site.borrower.application.disbursement-details', $application)]) }}"
                       class="font-semibold underline ml-1">{{ __('borrower.disbursement_details.add_payment_details') }}</a>
                </div>
            @else
                <form method="POST" action="{{ route('site.borrower.application.disbursement-details.confirm', $application) }}" class="mt-6">
                    @csrf
                    <p class="text-sm text-gray-600 mb-4">{{ __('borrower.disbursement_details.confirm_hint') }}</p>
                    <button type="submit"
                            class="w-full sm:w-auto inline-flex justify-center items-center bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-full text-sm">
                        {{ __('borrower.disbursement_details.confirm_button') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
</x-site.borrower-layout>
