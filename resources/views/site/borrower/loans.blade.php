<x-site.borrower-layout :title="brand_title(__('borrower.loans_page.title'))" active="loans">

    <h1 class="text-2xl font-bold mb-1">{{ __('borrower.loans_page.title') }}</h1>
    <p class="text-sm text-gray-500 mb-8">{{ __('borrower.loans_page.subtitle') }}</p>

    <section class="mb-10">
        <h2 class="text-lg font-semibold mb-4">{{ __('borrower.loans_page.borrowed') }}</h2>
        @if ($loans->isEmpty())
            <x-site.empty-state
                icon="📄"
                :title="__('borrower.loans_page.empty_title')"
                :description="__('borrower.loans_page.empty_desc')"
                :action-label="__('borrower.loans_page.empty_action')"
                :action-url="route('site.borrower.apply')"
            />
        @else
            <div class="space-y-4">
                @foreach ($loans as $loan)
                    @php
                        $paid = max(0, $loan->principal_amount - $loan->outstanding_balance);
                        $pct = $loan->principal_amount > 0 ? min(100, ($paid / $loan->principal_amount) * 100) : 0;
                        $statusBadge = match ($loan->status) {
                            'active','disbursed' => 'bg-emerald-100 text-emerald-700',
                            'arrears'            => 'bg-red-100 text-red-700',
                            'closed'             => 'bg-gray-100 text-gray-700',
                            default              => 'bg-amber-100 text-amber-700',
                        };
                        $monthly = $loan->tenure_months > 0 ? round(($loan->principal_amount / $loan->tenure_months) + ($loan->principal_amount * $loan->interest_rate)) : 0;
                    @endphp
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
                            <div>
                                <p class="text-xs text-gray-500">{{ $loan->product->name ?? '—' }}</p>
                                <p class="font-mono font-bold text-lg">{{ $loan->loan_number }}</p>
                            </div>
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $statusBadge }}">{{ ucfirst($loan->status) }}</span>
                        </div>

                        <div class="grid sm:grid-cols-4 gap-4 mb-5">
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_amount') }}</p>
                                <p class="font-semibold text-sm">TZS {{ number_format($loan->principal_amount) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.outstanding') }}</p>
                                <p class="font-semibold text-sm">TZS {{ number_format($loan->outstanding_balance) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.monthly') }}</p>
                                <p class="font-semibold text-sm">TZS {{ number_format($monthly) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.rate_tenure_label') }}</p>
                                <p class="font-semibold text-sm">{{ __('borrower.loans_page.rate_tenure', ['rate' => number_format($loan->interest_rate * 100, 2), 'months' => $loan->tenure_months]) }}</p>
                            </div>
                        </div>

                        <div class="mb-5">
                            <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                                <span>{{ __('borrower.loans_page.repaid_pct', ['pct' => number_format($pct, 0)]) }}</span>
                                <span>{{ __('borrower.loans_page.matures', ['date' => $loan->maturity_date ? \Carbon\Carbon::parse($loan->maturity_date)->format('d M Y') : '—']) }}</span>
                            </div>
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 flex-wrap">
                            <a href="{{ route('site.borrower.schedule', $loan->id) }}" class="bg-gray-900 hover:bg-gray-800 text-white text-xs font-semibold px-4 py-2 rounded-full">{{ __('borrower.loans_page.view_schedule') }}</a>
                            <a href="{{ route('site.borrower.payments') }}" class="bg-amber-500 hover:bg-amber-400 text-gray-900 text-xs font-semibold px-4 py-2 rounded-full">{{ __('borrower.loans_page.make_payment') }}</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section id="guarantor-requests" class="mb-10">
        <h2 class="text-lg font-semibold mb-4">{{ __('borrower.guarantor.pending_requests') }}</h2>
        @if (($pendingGuarantorRequests ?? collect())->isEmpty())
            <div class="bg-white rounded-2xl border border-gray-200 p-8 text-center text-sm text-gray-500">
                {{ __('borrower.guarantor.no_pending') }}
            </div>
        @else
            <div class="space-y-4">
                @foreach ($pendingGuarantorRequests as $invitation)
                    @php $link = $invitation->customerGuarantor; @endphp
                    <div class="bg-white rounded-2xl border border-gray-200 p-5">
                        <p class="font-semibold">{{ trim($invitation->borrower->first_name.' '.$invitation->borrower->last_name) }}</p>
                        <p class="text-sm text-gray-600 mt-1">
                            @if ($invitation->application)
                                {{ $invitation->application->product->name ?? __('borrower.guarantor.loan') }} · TZS {{ number_format((float) $invitation->application->requested_amount) }}
                            @endif
                        </p>
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
    </section>

    <section>
        <h2 class="text-lg font-semibold mb-4">{{ __('borrower.loans_page.guaranteed') }}</h2>
        @if (($guaranteedLinks ?? collect())->isEmpty())
            <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center">
                <p class="text-gray-500">{{ __('borrower.loans_page.no_guaranteed') }}</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($guaranteedLinks as $link)
                    @php
                        $app = $link->application;
                        $borrower = $app?->customer;
                        $loan = $app?->loan;
                    @endphp
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-start justify-between gap-3 mb-3 flex-wrap">
                            <div>
                                <p class="text-xs text-gray-500">{{ $app?->product?->name ?? __('borrower.loans_page.loan_application') }}</p>
                                <p class="font-semibold">{{ $borrower?->full_name ?? __('borrower.loans_page.borrower') }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 font-mono">{{ $app?->application_number }}</p>
                            </div>
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-sky-100 text-sky-800">{{ __('borrower.loans_page.guarantor_badge') }}</span>
                        </div>
                        <div class="grid sm:grid-cols-3 gap-4 text-sm">
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.requested') }}</p>
                                <p class="font-semibold">TZS {{ number_format($app?->requested_amount ?? 0) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.application_status') }}</p>
                                <p class="font-semibold capitalize">{{ str_replace('_', ' ', $app?->status ?? '—') }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_status') }}</p>
                                <p class="font-semibold capitalize">{{ $loan ? ucfirst($loan->status) : __('borrower.loans_page.not_disbursed') }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

</x-site.borrower-layout>
