<x-site.borrower-layout :title="brand_title('Profile — Activity')" active="profile">

    <div>
        @include('site.borrower.profile._heading', [
            'title' => __('borrower.profile.title'),
            'subtitle' => __('borrower.profile.activity_subtitle'),
        ])

        @include('site.borrower.profile._tabs', ['active' => 'activity'])

        @include('site.borrower.profile._completion')

        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'activity']) }}" class="bg-white rounded-2xl border border-gray-200 p-6"
              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.profile.save_confirm_title')), message: @js(__('borrower.profile.save_confirm_message')), confirmLabel: @js(__('borrower.profile.save')), confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900' })">
            @csrf @method('PUT')

            <h2 class="font-semibold mb-4">{{ __('borrower.profile.activity') }}</h2>
            <x-site.activity-fields
                :activity-type="old('activity_type', $customer->activity_type ?? $customer->employment_type)"
                :activity-details="old('activity_details', $customer->activity_details ?? [])"
                :income-range="old('income_range', $customer->income_range)"
            />

            <button class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.profile.save_activity') }}</button>
        </form>
    </div>

    @stack('scripts')
</x-site.borrower-layout>
