<x-site.borrower-layout :title="brand_title(__('borrower.guarantor.detail_title'))" active="guarantors" content-width="wide">

    <div class="space-y-6">
        <a href="{{ route('site.borrower.loans', ['tab' => 'guarantor']) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand hover:underline">
            ← {{ __('borrower.guarantor.back_to_requests') }}
        </a>

        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand via-brand to-brand-light text-white shadow-lg shadow-brand/20">
            <div class="absolute inset-0 opacity-[0.14]" style="background-image: radial-gradient(circle at 15% 20%, #fff 0, transparent 40%), radial-gradient(circle at 90% 0%, #fbbf24 0, transparent 35%);"></div>
            <div class="relative px-5 sm:px-7 py-6 sm:py-7 flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-white/70">{{ __('borrower.guarantor.detail_title') }}</p>
                    <h1 class="mt-2 text-2xl sm:text-3xl font-bold tracking-tight">{{ trim($invitation->borrower->first_name.' '.$invitation->borrower->last_name) }}</h1>
                    <p class="mt-2 text-sm text-white/80">{{ __('borrower.guarantor.detail_subtitle') }}</p>
                </div>
                <span class="inline-flex items-center rounded-full bg-brand-gold/95 text-brand px-3 py-1 text-xs font-bold shadow-sm">
                    {{ __('borrower.guarantor.action_required') }}
                </span>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @if (! ($profileStatus['met'] ?? false))
            <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-5">
                <p class="font-semibold text-amber-900">{{ __('borrower.guarantor.profile_after_accept_title') }}</p>
                <p class="text-sm text-amber-900 mt-1">{{ __('borrower.guarantor.profile_after_accept_body', ['percent' => $profileStatus['percent'] ?? 0]) }}</p>
                <div class="mt-3 h-2 rounded-full bg-amber-200 overflow-hidden max-w-xs">
                    <div class="h-full bg-amber-500 rounded-full transition-all" style="width: {{ min(100, max(0, (int) ($profileStatus['percent'] ?? 0))) }}%"></div>
                </div>
            </div>
        @endif

        <section class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden shadow-sm">
            <div class="px-5 sm:px-6 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white">
                <h2 class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.guarantor.loan_summary') }}</h2>
            </div>
            <dl class="px-5 sm:px-6 py-5 grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.guarantor_invite.borrower_label') }}</dt>
                    <dd class="font-semibold text-gray-900 mt-1">{{ trim($invitation->borrower->first_name.' '.$invitation->borrower->last_name) }}</dd>
                </div>
                @if ($invitation->application || $invitation->product || $invitation->requested_amount)
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.guarantor_invite.product_label') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-1">{{ $invitation->application?->product?->name ?? $invitation->product?->name ?? __('borrower.guarantor.loan') }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.guarantor_invite.amount_label') }}</dt>
                        <dd class="font-extrabold text-gray-900 mt-1 tabular-nums">{{ format_money((float) ($invitation->application?->requested_amount ?? $invitation->requested_amount ?? 0)) }}</dd>
                    </div>
                @endif
                @if ($invitation->application)
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.loans_page.reference') }}</dt>
                        <dd class="font-medium text-gray-900 mt-1">{{ $invitation->application->application_number ?? '—' }}</dd>
                    </div>
                @endif
            </dl>
            @if (! empty($guarantorExposure))
                <div class="mx-5 sm:mx-6 mb-5 rounded-xl bg-slate-50 ring-1 ring-gray-100 px-4 py-3 text-xs text-gray-700 flex flex-wrap gap-4">
                    <span>{{ __('borrower.loan_actions.guarantee_exposure') }}: <strong>{{ $guarantorExposure['count'] }}/{{ $guarantorExposure['max'] }}</strong></span>
                    <span>{{ __('borrower.loan_actions.guarantee_total') }}: <strong>{{ format_money($guarantorExposure['exposure']) }}</strong></span>
                </div>
            @endif
        </section>

        <section class="relative overflow-hidden rounded-3xl ring-2 ring-amber-400/70 bg-gradient-to-br from-amber-50 via-white to-brand-muted/30 shadow-md">
            <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-amber-500"></div>
            <div class="px-5 sm:px-7 py-6 sm:py-7">
                <p class="text-[10px] uppercase tracking-[0.2em] font-bold text-amber-800">{{ __('borrower.guarantor.disclaimer_eyebrow') }}</p>
                <h2 class="mt-2 text-xl font-bold text-gray-900">{{ __('borrower.guarantor.your_decision') }}</h2>
                <p class="mt-3 text-sm leading-relaxed text-gray-700 max-w-2xl">
                    {{ __('borrower.guarantor.disclaimer_body') }}
                </p>

                <div class="mt-6 flex flex-col-reverse sm:flex-row gap-3 sm:items-center sm:justify-end">
                    <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $customerGuarantor) }}"
                          @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.guarantor.decline_title')), message: @js(__('borrower.guarantor.decline_message')), confirmLabel: @js(__('borrower.loans_page.decline')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                        @csrf
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="w-full sm:w-auto bg-white ring-1 ring-gray-300 hover:bg-gray-50 text-gray-800 font-semibold px-5 py-2.5 rounded-full text-sm">
                            {{ __('borrower.loans_page.decline') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $customerGuarantor) }}"
                          @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.guarantor.approve_title')), message: @js(__('borrower.guarantor.approve_message')), confirmLabel: @js(__('borrower.guarantor.approve_cta')), confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white' })">
                        @csrf
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-full text-sm">
                            {{ __('borrower.guarantor.approve_cta') }}
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </div>

</x-site.borrower-layout>
