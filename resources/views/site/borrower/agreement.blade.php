<x-site.borrower-layout :title="brand_title(__('borrower.agreement.page_title', ['number' => $application->application_number]))" active="loans" content-width="wide">

    <div>
        <a href="{{ route('site.borrower.application', $application) }}" data-kf-motion="pop" class="text-sm text-amber-700 hover:underline">&larr; {{ __('borrower.agreement.back_to_application') }}</a>

        @if (session('status'))
            <div class="mt-3 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if (session('otp_sent'))
            <div class="mt-3 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-800">{{ session('otp_sent') }}</div>
        @endif
        @if (session('error'))
            <div class="mt-3 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <div class="mt-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-gray-900 to-amber-900 text-white px-6 py-4 flex items-center justify-between gap-3">
                <x-site.brand-mark size="sm" variant="light" />
                <span class="text-xs uppercase tracking-widest text-white/80">{{ __('borrower.agreement.offer_summary') }}</span>
            </div>
            <div class="p-6">
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-1">{{ __('borrower.offer.label') }}</p>
            <h1 class="text-xl font-bold text-gray-900">{{ __('borrower.offer.your_loan_offer') }}</h1>
            <p class="text-sm text-gray-600 mt-1">{{ __('borrower.offer.intro_approved', ['reference' => $application->application_number]) }}</p>

            @php
                $documentStatuses = __('borrower.agreement.document_statuses');
                $repaymentCadences = __('borrower.agreement.repayment_cadences');
                $offerFacts = $offerFacts ?? app(\App\Services\LendingJourneyService::class)->offerPresentation($application);
            @endphp

            @if (! $agreement)
                <div class="mt-6 rounded-lg bg-gray-50 ring-1 ring-gray-200 p-5 text-sm text-gray-700">
                    {{ __('borrower.agreement.not_issued_yet') }}
                </div>
            @else
                @php
                    $snap = $agreement->snapshot ?? [];
                    $statusLabel = match (true) {
                        $agreement->status === 'signed' => __('borrower.agreement.status_accepted'),
                        $agreement->isCancelled() => __('borrower.applications_list.statuses.offer_declined'),
                        default => $documentStatuses[$agreement->status] ?? ucfirst($agreement->status),
                    };
                @endphp

                <div class="mt-5">
                    @include('site.borrower._offer_facts', ['offerFacts' => $offerFacts])
                </div>

                <p class="mt-4 text-xs text-gray-500 rounded-lg bg-sky-50 ring-1 ring-sky-100 px-3 py-2">
                    {{ __('borrower.agreement.schedule_dates_note') }}
                </p>

                @if ($agreement->isOfferExpired())
                    <div class="mt-5 rounded-lg bg-red-50 ring-1 ring-red-200 p-4 text-sm text-red-800">
                        {{ __('borrower.agreement.expired') }}
                    </div>
                @elseif ($offerDeclined ?? false)
                    <div class="mt-6 rounded-lg bg-amber-50 ring-1 ring-amber-200 p-4 text-sm text-amber-900">
                        <p class="font-semibold">{{ __('borrower.applications_list.statuses.offer_declined') }}</p>
                        <p class="mt-1">{{ __('borrower.agreement.declined_detail') }}</p>
                        <a href="{{ route('site.borrower.application', $application) }}"
                           class="inline-flex mt-3 text-sm font-semibold text-amber-800 hover:text-amber-900">
                            {{ __('borrower.agreement.back_to_application') }} &rarr;
                        </a>
                    </div>
                @elseif ($agreement->isSigned())
                    <div class="mt-6 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 p-4 text-sm text-emerald-800">
                        <strong>{{ __('borrower.agreement.signed_on', ['date' => $agreement->signed_at->format('d M Y H:i')]) }}</strong>
                        <p class="mt-1">{{ __('borrower.agreement.signed_next_fees') }}</p>
                        @php $readiness = app(\App\Services\ApplicationDisbursementReadinessService::class); @endphp
                        @if ($readiness->needsPostApprovalFees($application))
                            <a href="{{ route('site.borrower.application.post-approval-fees', $application) }}"
                               class="inline-flex mt-3 text-sm font-semibold text-emerald-800 hover:text-emerald-900">
                                {{ __('borrower.loan_profile.actions.pay_post_approval_fees') }} &rarr;
                            </a>
                        @endif
                    </div>
                @elseif ($canRespondToOffer ?? false)
                    <div class="mt-6 border-t border-gray-200 pt-5">
                        <h2 class="text-sm font-semibold text-gray-900">{{ __('borrower.agreement.decision_title') }}</h2>
                        @if ($requireAcceptanceCode ?? false)
                            <p class="text-xs text-gray-600 mt-1">{{ __('borrower.agreement.pin_help') }}</p>
                            <form method="POST" action="{{ route('site.borrower.application.agreement.sign', $application) }}" class="mt-4 flex flex-wrap items-end gap-3"
                                  @submit.prevent="window.confirmForm($el, {
                                      title: @js(__('borrower.agreement.accept_confirm_title')),
                                      message: @js(__('borrower.agreement.accept_confirm')),
                                      confirmLabel: @js(__('borrower.offer.accept')),
                                      confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white',
                                      tone: 'confirm'
                                  })">
                                @csrf
                                <div>
                                    <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">{{ __('borrower.agreement.pin_label') }}</label>
                                    <input type="password" name="pin" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" autocomplete="off" required
                                           class="font-mono text-lg tracking-[0.4em] w-36 rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500">
                                </div>
                                <button type="submit" class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2.5 rounded-lg">
                                    {{ __('borrower.offer.accept') }}
                                </button>
                            </form>
                        @else
                            <p class="text-xs text-gray-600 mt-1">{{ __('borrower.agreement.decision_help') }}</p>
                            <div class="mt-4 flex flex-wrap gap-3">
                                <form method="POST" action="{{ route('site.borrower.application.agreement.accept', $application) }}"
                                      @submit.prevent="window.confirmForm($el, {
                                          title: @js(__('borrower.agreement.accept_confirm_title')),
                                          message: @js(__('borrower.agreement.accept_confirm')),
                                          confirmLabel: @js(__('borrower.offer.accept')),
                                          confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white',
                                          tone: 'confirm'
                                      })">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2.5 rounded-lg">
                                        {{ __('borrower.offer.accept') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('site.borrower.application.agreement.decline', $application) }}"
                                      @submit.prevent="window.confirmForm($el, {
                                          title: @js(__('borrower.agreement.decline_confirm_title')),
                                          message: @js(__('borrower.agreement.decline_confirm')),
                                          confirmLabel: @js(__('borrower.agreement.decline_button')),
                                          confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                                          tone: 'warning'
                                      })">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-2 text-sm font-semibold text-red-700 bg-red-50 hover:bg-red-100 px-5 py-2.5 rounded-lg">
                                        {{ __('borrower.agreement.decline_button') }}
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="mt-6 rounded-lg bg-gray-50 ring-1 ring-gray-200 p-4 text-sm text-gray-700">
                        {{ __('borrower.agreement.not_available') }}
                    </div>
                @endif
            @endif
            </div>
        </div>
    </div>

</x-site.borrower-layout>
