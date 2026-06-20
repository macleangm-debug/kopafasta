<x-site.layout :title="brand_title(__('borrower.apply.group.invite_subject'))">
    <div class="max-w-lg mx-auto py-12 px-4">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 space-y-4">
            <h1 class="text-2xl font-bold">{{ __('borrower.apply.group.invite_subject') }}</h1>
            <p class="text-sm text-gray-600">
                {{ $invitation->leader?->full_name }} invited you to join the group
                <strong>{{ $invitation->displayName() }}</strong> for a group loan application.
            </p>
            <p class="text-sm text-gray-600">{{ __('borrower.apply.kyc_incomplete_hint') }}</p>
            <a href="{{ $registerUrl }}"
               class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-full text-sm">
                Register to join group
            </a>
        </div>
    </div>
</x-site.layout>
