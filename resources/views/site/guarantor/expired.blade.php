<x-site.layout :title="brand_title(__('borrower.guarantor_invite.expired_title'))">
    <div class="max-w-xl mx-auto px-4 py-12 text-center">
        <div class="bg-white rounded-2xl border border-gray-200 p-8">
            <h1 class="text-xl font-bold mb-2">{{ __('borrower.guarantor_invite.expired_title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('borrower.guarantor_invite.expired_message') }}</p>
        </div>
    </div>
</x-site.layout>
