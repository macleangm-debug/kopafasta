<x-site.borrower-layout :title="brand_title(__('borrower.kyc.reconfirm_title'))" active="profile">

    <div class="max-w-3xl">
        <h1 class="text-2xl font-bold mb-1">{{ __('borrower.kyc.reconfirm_title') }}</h1>
        <p class="text-sm text-gray-500 mb-6">{{ __('borrower.kyc.reconfirm_intro') }}</p>

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('site.borrower.kyc-reconfirm.update') }}" class="space-y-6">
            @csrf @method('PUT')

            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="font-semibold mb-4">{{ __('borrower.kyc.residence') }}</h2>
                <x-site.address-fields
                    :region="old('region', $customer->region)"
                    :district="old('district', $customer->district)"
                    :ward="old('ward', $customer->ward)"
                    :street="old('street', $customer->street ?? $customer->address)"
                />
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h2 class="font-semibold mb-4">{{ __('borrower.kyc.activity') }}</h2>
                <x-site.activity-fields
                    :activity-type="old('activity_type', $customer->activity_type ?? $customer->employment_type)"
                    :activity-details="old('activity_details', $customer->activity_details ?? [])"
                    :income-range="old('income_range', $customer->income_range)"
                />
            </div>

            <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                {{ __('borrower.kyc.save') }}
            </button>
        </form>
    </div>

    @stack('scripts')
</x-site.borrower-layout>
