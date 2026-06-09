<x-site.layout :title="brand_title(__('borrower.guarantor_invite.declined_thanks_title'))">
    <div class="max-w-xl mx-auto px-4 py-12 text-center">
        <div class="bg-white rounded-2xl border border-gray-200 p-8">
            <h1 class="text-2xl font-bold mb-2">{{ __('borrower.guarantor_invite.declined_thanks_title') }}</h1>
            <p class="text-sm text-gray-600 mb-8">{{ __('borrower.guarantor_invite.declined_thanks_message') }}</p>

            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-5 py-6 text-sm text-amber-950 text-left">
                <p class="font-semibold text-base mb-2">{{ __('borrower.guarantor_invite.declined_cta_title') }}</p>
                <p class="text-amber-900 mb-4">{{ __('borrower.guarantor_invite.declined_benefits') }}</p>
                <ul class="space-y-2 mb-6 text-amber-900">
                    <li class="flex gap-2"><span>✓</span>{{ __('borrower.guarantor_invite.declined_benefit_fast') }}</li>
                    <li class="flex gap-2"><span>✓</span>{{ __('borrower.guarantor_invite.declined_benefit_flexible') }}</li>
                    <li class="flex gap-2"><span>✓</span>{{ __('borrower.guarantor_invite.declined_benefit_mobile') }}</li>
                </ul>
                <div class="flex flex-wrap justify-center gap-3">
                    <a href="{{ route('site.register.borrower') }}"
                       class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                        {{ __('borrower.guarantor_invite.declined_cta_member') }}
                    </a>
                    <a href="{{ route('site.products') }}"
                       class="inline-flex bg-white ring-1 ring-amber-300 hover:bg-amber-50 text-amber-950 font-semibold px-5 py-2.5 rounded-full text-sm">
                        {{ __('borrower.guarantor_invite.declined_cta_apply') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-site.layout>
