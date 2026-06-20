<x-site.layout :title="brand_title(__('borrower.apply.group.invite_accepted_title'))">
    <div class="max-w-lg mx-auto py-12 px-4">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 space-y-4">
            <h1 class="text-2xl font-bold">{{ __('borrower.apply.group.invite_accepted_title') }}</h1>
            <p class="text-sm text-gray-600">{{ __('borrower.apply.group.invite_accepted_body') }}</p>
            @guest
                <a href="{{ route('site.register.borrower') }}"
                   class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-full text-sm">
                    {{ __('borrower.apply.group.create_account') }}
                </a>
            @else
                <a href="{{ route('site.borrower.dashboard') }}"
                   class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-full text-sm">
                    {{ __('borrower.apply.group.continue_in_portal') }}
                </a>
            @endguest
        </div>
    </div>
</x-site.layout>
