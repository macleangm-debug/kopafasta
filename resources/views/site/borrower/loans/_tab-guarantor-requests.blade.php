<div class="mb-6">
    <h2 class="text-lg font-semibold mb-1">{{ __('borrower.loans_page.tab_guarantor_requests') }}</h2>
    <p class="text-sm text-gray-500">{{ __('borrower.guarantor.pending_requests_hint') }}</p>
    @if (! empty($guarantorExposure))
        <div class="mt-3 rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3 text-xs text-gray-700 flex flex-wrap gap-4">
            <span>{{ __('borrower.loan_actions.guarantee_exposure') }}: <strong>{{ $guarantorExposure['count'] }}/{{ $guarantorExposure['max'] }}</strong></span>
            <span>{{ __('borrower.loan_actions.guarantee_total') }}: <strong>{{ format_money($guarantorExposure['exposure']) }}</strong></span>
        </div>
    @endif
</div>

@if (($pendingGuarantorRequests ?? collect())->isEmpty())
    <div class="bg-white rounded-2xl border border-gray-200 p-8 text-center text-sm text-gray-500">
        {{ __('borrower.guarantor.no_pending') }}
    </div>
@else
    <div class="space-y-4">
        @foreach ($pendingGuarantorRequests as $invitation)
            @php $link = $invitation->customerGuarantor; @endphp
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                    <p class="font-semibold text-gray-900">{{ __('borrower.guarantor.request_received') }}</p>
                    <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-amber-100 text-amber-800">{{ __('borrower.guarantor.action_required') }}</span>
                </div>
                <dl class="grid gap-2 text-sm">
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.guarantor_invite.borrower_label') }}</dt>
                        <dd class="font-medium text-gray-900">{{ trim($invitation->borrower->first_name.' '.$invitation->borrower->last_name) }}</dd>
                    </div>
                    @if ($invitation->application)
                        <div>
                            <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.loans_page.reference') }}</dt>
                            <dd class="font-medium text-gray-900">{{ $invitation->application->application_number ?? $invitation->application->draft_reference ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.guarantor_invite.product_label') }}</dt>
                            <dd class="text-gray-700">{{ $invitation->application->product->name ?? __('borrower.guarantor.loan') }} · {{ format_money((float) $invitation->application->requested_amount) }}</dd>
                        </div>
                    @endif
                </dl>
                @if ($link)
                    <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $link) }}" class="mt-4 space-y-4"
                          @submit.prevent="
                            const sig = $el.elements['signature_data'];
                            if (! sig?.value) { alert(@js(__('borrower.guarantor.draw_signature'))); return; }
                            window.confirmForm($el, { title: @js(__('borrower.guarantor.approve_title')), message: @js(__('borrower.guarantor.approve_message')), confirmLabel: @js(__('borrower.guarantor.approve_sign')), confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white' });
                          ">
                        @csrf
                        <input type="hidden" name="action" value="approve">
                        <x-site.signature-pad :default-name="trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''))" />
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2 rounded-full text-sm">{{ __('borrower.guarantor.approve_sign') }}</button>
                    </form>
                    <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $link) }}" class="mt-2 flex flex-wrap gap-3 items-end"
                          @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.guarantor.decline_title')), message: @js(__('borrower.guarantor.decline_message')), confirmLabel: @js(__('borrower.loans_page.decline')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                        @csrf
                        <input type="hidden" name="action" value="reject">
                        <input name="notes" placeholder="{{ __('borrower.loans_page.optional_reason') }}" class="rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm flex-1 min-w-[200px]">
                        <button class="bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-4 py-2 rounded-full text-sm">{{ __('borrower.loans_page.decline') }}</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
@endif
