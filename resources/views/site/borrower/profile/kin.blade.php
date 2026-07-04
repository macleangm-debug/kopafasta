<x-site.borrower-layout :title="brand_title(__('borrower.profile.kin_title'))" active="profile" content-width="wide">
    <div>
        @include('site.borrower.profile._profile_shell', [
            'title' => __('borrower.profile.kin_title'),
            'subtitle' => __('borrower.profile.kin_subtitle'),
            'customer' => $customer,
            'active' => 'kin',
            'wizardMode' => false,
        ])

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'kin']) }}" class="glass-card p-6 sm:p-8 space-y-4">
            @csrf @method('PUT')

            <x-site.kin-fields :customer="$customer" input-class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" />

            <div class="pt-2">
                <p class="text-xs font-medium text-gray-600 mb-3">{{ __('borrower.profile.residence') }}</p>
                <x-site.address-fields
                    prefix="nok"
                    :region="old('nok_region', $customer->nok_region)"
                    :district="old('nok_district', $customer->nok_district)"
                    :ward="old('nok_ward', $customer->nok_ward)"
                    :street="old('nok_street', $customer->nok_street)"
                />
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-2.5 rounded-full text-sm">{{ __('borrower.profile.save_kin') }}</button>
            </div>
        </form>
    </div>

    @stack('scripts')
</x-site.borrower-layout>
