<x-site.borrower-layout :title="brand_title('Profile — Residence')" active="profile">

    <div>
        @include('site.borrower.profile._heading', [
            'title' => __('borrower.profile.title'),
            'subtitle' => __('borrower.profile.residence_subtitle'),
        ])

        @include('site.borrower.profile._tabs', ['active' => 'residence'])

        @include('site.borrower.profile._completion')

        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'residence']) }}"
              enctype="multipart/form-data" class="bg-white rounded-2xl border border-gray-200 p-6"
              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.profile.save_confirm_title')), message: @js(__('borrower.profile.save_confirm_message')), confirmLabel: @js(__('borrower.profile.save')), confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900' })">
            @csrf @method('PUT')

            <h2 class="font-semibold mb-4">{{ __('borrower.profile.residence') }}</h2>
            <x-site.address-fields
                :region="old('region', $customer->region)"
                :district="old('district', $customer->district)"
                :ward="old('ward', $customer->ward)"
                :street="old('street', $customer->street ?? $customer->address)"
            />

            <div class="mt-6 pt-6 border-t border-gray-100">
                <label class="block text-sm font-semibold text-gray-900 mb-1">{{ __('borrower.profile.residence_letter') }}</label>
                <p class="text-xs text-gray-500 mb-3">{{ __('borrower.profile.residence_letter_hint') }}</p>
                <x-site.multi-page-residence-upload />
                <p class="text-xs text-gray-400 mt-3">{{ __('borrower.profile.residence_letter_single') }}</p>
                <input type="file" name="residence_letter" accept=".jpg,.jpeg,.png,.pdf"
                       class="mt-2 w-full text-sm file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-amber-50 file:text-amber-800 file:font-semibold">
                @error('residence_letter')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('residence_letter_pages.*')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <button class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.profile.save_residence') }}</button>
        </form>
    </div>

    @stack('scripts')
</x-site.borrower-layout>
