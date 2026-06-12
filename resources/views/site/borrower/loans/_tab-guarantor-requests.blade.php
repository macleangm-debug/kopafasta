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
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($pendingGuarantorRequests as $row)
            @php
                $link = $row->link;
                $borrower = $row->borrower;
                $application = $row->application;
                $borrowerName = trim(($borrower->first_name ?? '').' '.($borrower->last_name ?? ''));
                $productName = $application?->product?->name ?? __('borrower.guarantor.loan');
                $amount = $application ? format_money((float) $application->requested_amount) : '—';
                $reference = $application?->application_number ?? $application?->draft_reference ?? '—';
            @endphp
            <a href="{{ route('site.borrower.guarantor-requests.show', $link) }}"
               class="block bg-white rounded-2xl border border-gray-200 p-5 hover:border-amber-300 hover:shadow-md transition-all group">
                <div class="flex items-start justify-between gap-2 mb-3">
                    <p class="font-semibold text-gray-900 group-hover:text-amber-800">{{ $borrowerName ?: '—' }}</p>
                    <span class="shrink-0 text-xs font-semibold rounded-full px-2.5 py-1 bg-amber-100 text-amber-800">{{ __('borrower.guarantor.action_required') }}</span>
                </div>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.guarantor_invite.product_label') }}</dt>
                        <dd class="text-gray-700">{{ $productName }}</dd>
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <div>
                            <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.guarantor_invite.amount_label') }}</dt>
                            <dd class="font-semibold text-gray-900">{{ $amount }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.loans_page.reference') }}</dt>
                            <dd class="text-gray-700">{{ $reference }}</dd>
                        </div>
                    </div>
                </dl>
                <p class="mt-4 text-sm font-semibold text-amber-700">{{ __('borrower.guarantor.view_details') }} →</p>
            </a>
        @endforeach
    </div>
@endif
