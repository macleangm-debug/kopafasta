<x-site.borrower-layout
    :title="brand_title(__('borrower.guarantor.detail_title'))"
    active="loans"
    content-width="narrow">

    @php
        $borrowerName = trim(($invitation->borrower->first_name ?? '').' '.($invitation->borrower->last_name ?? '')) ?: '—';
        $productName = $invitation->application?->product?->localizedName()
            ?? $invitation->product?->localizedName()
            ?? __('borrower.guarantor.loan');
        $amount = $invitation->application?->requested_amount ?? $invitation->requested_amount;
        $reference = $invitation->application?->application_number
            ?? $invitation->application?->draft_reference
            ?? ($invitation->short_code ? strtoupper((string) $invitation->short_code) : '—');
        $profileMet = (bool) ($profileStatus['met'] ?? false);
        $profilePercent = (int) ($profileStatus['percent'] ?? 0);
    @endphp

    <div class="mb-4">
        <a href="{{ route('site.borrower.loans', ['tab' => 'guarantor']) }}" class="text-sm font-semibold text-brand hover:underline">
            ← {{ __('borrower.guarantor.back_to_requests') }}
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <x-site.borrower-page-header
        :eyebrow="__('borrower.loans_page.guarantor_badge')"
        :title="$productName"
        :subtitle="$borrowerName.' · '.$reference"
    >
        <x-slot:actions>
            <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1.5 bg-amber-100 text-amber-900">
                {{ __('borrower.guarantor.action_required') }}
            </span>
        </x-slot:actions>
    </x-site.borrower-page-header>

    {{-- At a glance --}}
    <div class="mb-6 glass-card overflow-hidden">
        <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 sm:px-6 py-5 border-b border-gray-100/80">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guaranteed.detail_eyebrow') }}</p>
                    <h2 class="text-lg sm:text-xl font-bold text-gray-900 mt-1">{{ __('borrower.guaranteed.detail_glance_title') }}</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ __('borrower.guarantor.awaiting_your_decision') }}</p>
                </div>
            </div>

            <div class="mt-5 rounded-xl bg-white ring-1 ring-gray-200/80 px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.application_progress') }}</p>
                <div class="flex items-center gap-3 mt-2">
                    <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full bg-brand" style="width: 10%"></div>
                    </div>
                    <span class="text-sm font-bold tabular-nums text-gray-900">10%</span>
                </div>
                <p class="text-xs text-gray-500 mt-1">{{ __('borrower.guarantor.action_required') }}</p>
            </div>

            <div class="mt-4 rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-gradient-to-br from-brand-muted/40 via-white to-white px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loans_page.loan_amount') }}</p>
                <p class="text-sm text-gray-700 mt-0.5">
                    {{ $amount !== null ? format_money((float) $amount) : '—' }}
                    · {{ __('borrower.loans_page.not_disbursed') }}
                </p>
            </div>
        </div>
    </div>

    @unless ($profileMet)
        <div class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15">
            <div class="px-5 sm:px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_profile.profile_completion') }}</p>
                    <div class="flex items-center gap-3 mt-3 max-w-md">
                        <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full bg-brand" style="width: {{ $profilePercent }}%"></div>
                        </div>
                        <span class="text-sm font-bold tabular-nums text-gray-900">{{ $profilePercent }}%</span>
                    </div>
                    <p class="text-sm font-semibold text-gray-900 mt-2">{{ __('borrower.guarantor.profile_after_accept_title') }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ __('borrower.guarantor.profile_after_accept_body', ['percent' => $profilePercent]) }}</p>
                </div>
                <a href="{{ route('site.borrower.profile') }}"
                   class="inline-flex items-center justify-center font-bold px-5 py-2.5 rounded-xl text-sm shrink-0 bg-brand-gold hover:bg-yellow-400 text-brand">
                    {{ __('borrower.loan_profile.complete_profile') }}
                </a>
            </div>
        </div>
    @endunless

    <div class="glass-card p-5 mb-6 ring-1 ring-brand/15">
        <div class="mb-4">
            <h2 class="font-semibold">{{ __('borrower.loan_profile.summary_title') }}</h2>
        </div>
        <div class="grid sm:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.borrower') }}</p>
                <p class="font-semibold mt-1">{{ $borrowerName }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guarantor_invite.product_label') }}</p>
                <p class="font-semibold mt-1">{{ $productName }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.applications_list.amount') }}</p>
                <p class="font-semibold mt-1">{{ $amount !== null ? format_money((float) $amount) : '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loans_page.reference') }}</p>
                <p class="font-semibold mt-1 font-mono">{{ $reference }}</p>
            </div>
        </div>
        @if (! empty($guarantorExposure))
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-2xl bg-gradient-to-br from-brand-muted/50 to-white ring-1 ring-brand/10 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_actions.guarantee_exposure') }}</p>
                    <p class="mt-1 text-lg font-bold tabular-nums text-gray-900">{{ $guarantorExposure['count'] }}/{{ $guarantorExposure['max'] }}</p>
                </div>
                <div class="rounded-2xl bg-gradient-to-br from-brand-muted/50 to-white ring-1 ring-brand/10 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_actions.guarantee_total') }}</p>
                    <p class="mt-1 text-lg font-bold tabular-nums text-gray-900">{{ format_money($guarantorExposure['exposure']) }}</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Decision (primary action) --}}
    <div class="mb-2 glass-card overflow-hidden ring-1 ring-brand/15">
        <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 sm:px-6 py-5">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.guarantor.disclaimer_eyebrow') }}</p>
            <h2 class="text-lg font-bold text-gray-900 mt-1">{{ __('borrower.guarantor.your_decision') }}</h2>
            <p class="mt-2 text-sm text-gray-700 leading-relaxed">{{ __('borrower.guarantor.disclaimer_body') }}</p>

            <div class="mt-6 flex flex-col-reverse sm:flex-row gap-3 sm:items-center sm:justify-end">
                <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $customerGuarantor) }}"
                      @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.guarantor.decline_title')), message: @js(__('borrower.guarantor.decline_message')), confirmLabel: @js(__('borrower.loans_page.decline')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                    @csrf
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="w-full sm:w-auto bg-white ring-1 ring-gray-300 hover:bg-gray-50 text-gray-800 font-semibold px-5 py-2.5 rounded-xl text-sm">
                        {{ __('borrower.loans_page.decline') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $customerGuarantor) }}"
                      @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.guarantor.approve_title')), message: @js(__('borrower.guarantor.approve_message')), confirmLabel: @js(__('borrower.guarantor.approve_cta')), confirmClass: 'bg-brand hover:bg-brand-light text-white' })">
                    @csrf
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="w-full sm:w-auto bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-8 py-3.5 rounded-xl text-sm shadow-sm">
                        {{ __('borrower.guarantor.approve_cta') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

</x-site.borrower-layout>
