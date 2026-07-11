<x-site.borrower-layout :title="brand_title(__('borrower.guarantor_requests_page.title'))" active="guarantors" content-width="wide">

    <x-site.borrower-page-header
        :title="__('borrower.guarantor_requests_page.title')"
        :subtitle="__('borrower.guarantor_requests_page.subtitle')"
    />

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="space-y-4 max-w-3xl">
        @forelse ($requests as $invitation)
            @php $link = $invitation->customerGuarantor; @endphp
            <div class="glass-card p-5 sm:p-6">
                <p class="font-semibold text-gray-900">{{ trim(($invitation->borrower->first_name ?? '').' '.($invitation->borrower->last_name ?? '')) }}</p>
                <p class="text-sm text-gray-600 mt-1">
                    @if ($invitation->application)
                        {{ $invitation->application->product->name ?? __('borrower.nav.loans') }}
                        · {{ format_money((float) $invitation->application->requested_amount) }}
                    @endif
                </p>
                @if ($link)
                    <div class="mt-4 flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $link) }}"
                              @submit.prevent="window.confirmForm($el, {
                                  title: @js(__('borrower.guarantor_requests_page.approve_title')),
                                  message: @js(__('borrower.guarantor_requests_page.approve_message')),
                                  confirmLabel: @js(__('borrower.guarantor_requests_page.approve_label')),
                                  confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white'
                              })">
                            @csrf
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-full text-sm">
                                {{ __('borrower.guarantor_requests_page.approve_label') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $link) }}"
                              class="flex flex-wrap gap-2 items-center flex-1 min-w-[220px]"
                              @submit.prevent="window.confirmForm($el, {
                                  title: @js(__('borrower.guarantor_requests_page.decline_title')),
                                  message: @js(__('borrower.guarantor_requests_page.decline_message')),
                                  confirmLabel: @js(__('borrower.guarantor_requests_page.decline_label')),
                                  confirmClass: 'bg-red-600 hover:bg-red-700 text-white'
                              })">
                            @csrf
                            <input type="hidden" name="action" value="reject">
                            <input name="notes" placeholder="{{ __('borrower.guarantor_requests_page.optional_reason') }}"
                                   class="rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm flex-1 min-w-[160px]">
                            <button type="submit" class="bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-5 py-2.5 rounded-full text-sm">
                                {{ __('borrower.guarantor_requests_page.decline_label') }}
                            </button>
                        </form>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        {{ __('borrower.guarantor_invite.declined_upsell_lede') }}
                        <a href="{{ route('site.borrower.loan-products') }}" class="font-semibold text-amber-700 hover:underline">
                            {{ __('borrower.guarantor_invite.declined_cta_apply') }}
                        </a>
                    </p>
                @endif
            </div>
        @empty
            <x-site.empty-state
                :title="__('borrower.guarantor_requests_page.empty_title')"
                :description="__('borrower.guarantor_requests_page.empty_desc')"
            />
        @endforelse
    </div>

</x-site.borrower-layout>
