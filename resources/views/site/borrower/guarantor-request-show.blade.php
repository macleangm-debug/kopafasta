<x-site.borrower-layout :title="brand_title(__('borrower.guarantor.detail_title'))" active="guarantors" content-width="wide">

    <div>
        <a href="{{ route('site.borrower.loans', ['tab' => 'guarantor']) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-amber-700 hover:underline mb-4">
            ← {{ __('borrower.guarantor.back_to_requests') }}
        </a>

        <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('borrower.guarantor.detail_title') }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ __('borrower.guarantor.detail_subtitle') }}</p>
            </div>
            <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-amber-100 text-amber-800">{{ __('borrower.guarantor.action_required') }}</span>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @if (! ($profileStatus['met'] ?? false))
            <div class="mb-6 rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-5">
                <p class="font-semibold text-amber-900">{{ __('borrower.guarantor.profile_required_title') }}</p>
                <p class="text-sm text-amber-900 mt-1">{{ __('borrower.guarantor.profile_required_body', ['percent' => $profileStatus['percent'] ?? 0]) }}</p>
                <div class="mt-3 h-2 rounded-full bg-amber-200 overflow-hidden">
                    <div class="h-full bg-amber-500 rounded-full transition-all" style="width: {{ min(100, max(0, (int) ($profileStatus['percent'] ?? 0))) }}%"></div>
                </div>
                <a href="{{ route('site.borrower.profile.wizard') }}"
                   class="inline-flex mt-4 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.guarantor.complete_profile') }}
                </a>
            </div>
        @endif

        <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-6">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.guarantor_invite.borrower_label') }}</dt>
                    <dd class="font-medium text-gray-900 mt-0.5">{{ trim($invitation->borrower->first_name.' '.$invitation->borrower->last_name) }}</dd>
                </div>
                @if ($invitation->application)
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.loans_page.reference') }}</dt>
                        <dd class="font-medium text-gray-900 mt-0.5">{{ $invitation->application->application_number ?? '—' }}</dd>
                    </div>
                @endif
                @if ($invitation->application || $invitation->product || $invitation->requested_amount)
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.guarantor_invite.product_label') }}</dt>
                        <dd class="text-gray-700 mt-0.5">{{ $invitation->application?->product?->name ?? $invitation->product?->name ?? __('borrower.guarantor.loan') }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.guarantor_invite.amount_label') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ format_money((float) ($invitation->application?->requested_amount ?? $invitation->requested_amount ?? 0)) }}</dd>
                    </div>
                @endif
            </dl>

            @if (! empty($guarantorExposure))
                <div class="mt-5 pt-5 border-t border-gray-100 rounded-xl bg-gray-50 px-4 py-3 text-xs text-gray-700 flex flex-wrap gap-4">
                    <span>{{ __('borrower.loan_actions.guarantee_exposure') }}: <strong>{{ $guarantorExposure['count'] }}/{{ $guarantorExposure['max'] }}</strong></span>
                    <span>{{ __('borrower.loan_actions.guarantee_total') }}: <strong>{{ format_money($guarantorExposure['exposure']) }}</strong></span>
                </div>
            @endif
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h2 class="font-semibold text-gray-900 mb-1">{{ __('borrower.guarantor.your_decision') }}</h2>
            <p class="text-sm text-gray-500 mb-5">{{ __('borrower.guarantor.decision_hint') }}</p>

            @if ($profileStatus['met'] ?? false)
                <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $customerGuarantor) }}" class="space-y-4"
                      @submit.prevent="
                        const pad = $el.querySelector('[data-signature-pad]');
                        const dataUrl = pad?._x_dataStack?.[0]?.dataUrl || '';
                        const sig = $el.querySelector('[name=signature_data]');
                        if (dataUrl && sig) sig.value = dataUrl;
                        if (! (dataUrl || sig?.value)) { alert(@js(__('borrower.guarantor.draw_signature'))); return; }
                        window.confirmForm($el, { title: @js(__('borrower.guarantor.approve_title')), message: @js(__('borrower.guarantor.approve_message')), confirmLabel: @js(__('borrower.guarantor.approve_sign')), confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white' });
                      ">
                    @csrf
                    <input type="hidden" name="action" value="approve">
                    <x-site.signature-pad
                        :default-name="trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''))"
                        :readonly-name="true"
                        :verified="true"
                    />
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.guarantor.approve_sign') }}</button>
                </form>
            @else
                <p class="text-sm text-gray-600 rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3">{{ __('borrower.guarantor.sign_after_profile') }}</p>
            @endif

            <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $customerGuarantor) }}" class="mt-4 flex flex-wrap gap-3 items-end"
                  @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.guarantor.decline_title')), message: @js(__('borrower.guarantor.decline_message')), confirmLabel: @js(__('borrower.loans_page.decline')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                @csrf
                <input type="hidden" name="action" value="reject">
                <input name="notes" placeholder="{{ __('borrower.loans_page.optional_reason') }}" class="rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm flex-1 min-w-[200px]">
                <button class="bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-4 py-2 rounded-full text-sm">{{ __('borrower.loans_page.decline') }}</button>
            </form>
        </div>
    </div>

</x-site.borrower-layout>
