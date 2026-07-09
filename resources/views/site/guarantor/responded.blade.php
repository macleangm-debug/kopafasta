<x-site.guarantor-invite-shell
    :title="brand_title(__('borrower.guarantor_invite.page_title'))"
    :heading="__('borrower.guarantor_invite.heading')"
    :lede="__('borrower.guarantor_invite.request_explanation')"
>
    <div class="text-center">
        <h1 class="text-2xl font-bold tracking-tight mb-2">{{ __('borrower.guarantor_invite.heading') }}</h1>
        <p class="text-sm text-gray-600 mb-6">
            @if ($invitation->status === 'rejected')
                {{ __('borrower.guarantor_invite.decline_message') }}
            @else
                {{ __('borrower.guarantor_invite.already_responded', ['status' => str_replace('_', ' ', $invitation->status)]) }}
            @endif
        </p>

        @if ($invitation->status === 'rejected' && $invitation->type === 'external')
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-5 text-sm text-amber-950 text-left">
                <p class="font-semibold mb-4 text-center">{{ __('borrower.guarantor_invite.declined_cta_title') }}</p>
                <div class="flex flex-col gap-3">
                    <a href="{{ route('site.register.borrower') }}"
                       class="inline-flex justify-center bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-3 rounded-full text-sm">
                        {{ __('borrower.guarantor_invite.declined_cta_apply') }}
                    </a>
                    <a href="{{ route('site.home') }}"
                       class="inline-flex justify-center bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-5 py-3 rounded-full text-sm">
                        {{ __('borrower.guarantor_invite.declined_cta_not_now') }}
                    </a>
                </div>
            </div>
        @endif
    </div>
</x-site.guarantor-invite-shell>
