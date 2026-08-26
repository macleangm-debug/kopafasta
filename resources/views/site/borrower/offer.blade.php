<x-site.borrower-layout
    :title="brand_title(__('borrower.offer.title'))"
    active="loans"
    content-width="wide">

    <div class="mb-4">
        <a href="{{ route('site.borrower.application', $application->id) }}" class="text-xs text-gray-500 hover:text-gray-700">
            {{ __('borrower.loan_profile.back') }}
        </a>
    </div>

    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">{{ __('borrower.offer.label') }}</p>
        <h1 class="text-2xl sm:text-3xl font-bold">{{ __('borrower.offer.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1 font-mono">{{ $application->application_number }}</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
        <p class="text-sm text-gray-600 mb-4">{{ __('borrower.offer.intro') }}</p>

        @include('site.borrower._offer_facts', ['offerFacts' => $offerFacts ?? []])

        @if ($application->committee_recommendation)
            <div class="rounded-xl bg-sky-50 ring-1 ring-sky-100 px-4 py-3 text-sm text-sky-900 mb-6">
                <p class="font-semibold">{{ __('borrower.offer.committee_note') }}</p>
                <p class="mt-1">{{ $application->committee_recommendation }}</p>
            </div>
        @endif

        <div class="flex flex-wrap gap-3">
            <form method="POST" action="{{ route('site.borrower.application.offer.respond', $application->id) }}"
                  @submit.prevent="window.confirmForm($el, {
                      title: @js(__('borrower.offer.accept_confirm_title')),
                      message: @js(__('borrower.offer.accept_confirm')),
                      confirmLabel: @js(__('borrower.offer.accept')),
                      confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white',
                      tone: 'confirm'
                  })">
                @csrf
                <input type="hidden" name="decision" value="accept">
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-6 py-3 text-sm">
                    {{ __('borrower.offer.accept') }}
                </button>
            </form>
            <form method="POST" action="{{ route('site.borrower.application.offer.respond', $application->id) }}"
                  @submit.prevent="window.confirmForm($el, {
                      title: @js(__('borrower.offer.decline_confirm_title')),
                      message: @js(__('borrower.offer.decline_confirm')),
                      confirmLabel: @js(__('borrower.offer.decline')),
                      confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                      tone: 'warning'
                  })">
                @csrf
                <input type="hidden" name="decision" value="decline">
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-white hover:bg-gray-50 text-gray-800 font-semibold px-6 py-3 text-sm ring-1 ring-gray-300">
                    {{ __('borrower.offer.decline') }}
                </button>
            </form>
        </div>
    </div>
</x-site.borrower-layout>
