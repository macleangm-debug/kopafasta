<x-site.borrower-layout :title="brand_title(__('borrower.profile.kin_title'))" active="profile">
    <div class="max-w-3xl mx-auto">
        @include('site.borrower.profile._heading', [
            'title' => __('borrower.profile.kin_title'),
            'subtitle' => __('borrower.profile.kin_subtitle'),
        ])

        @include('site.borrower.profile._tabs', ['active' => 'kin'])

        @include('site.borrower.profile._completion')

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'kin']) }}" class="bg-white rounded-2xl ring-1 ring-gray-200 p-6 sm:p-8 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.full_name') }}</label>
                <input name="nok_name" value="{{ old('nok_name', $customer->nok_name) }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.relationship') }}</label>
                    <input name="nok_relationship" value="{{ old('nok_relationship', $customer->nok_relationship) }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.profile.fields.phone') }}</label>
                    <input name="nok_phone" value="{{ old('nok_phone', $customer->nok_phone) }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
            </div>

            <div class="pt-2">
                <p class="text-xs font-medium text-gray-600 mb-3">{{ __('borrower.profile.residence') }}</p>
                <x-site.address-fields
                    prefix="nok"
                    :region="old('nok_region', $customer->nok_region)"
                    :district="old('nok_district', $customer->nok_district)"
                />
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-2.5 rounded-full text-sm">{{ __('borrower.profile.save_kin') }}</button>
            </div>
        </form>
    </div>

    @stack('scripts')
</x-site.borrower-layout>
