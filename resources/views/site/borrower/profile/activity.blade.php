<x-site.borrower-layout :title="brand_title('Profile — Activity')" active="profile">

    <div>
        @include('site.borrower.profile._heading', [
            'title' => __('borrower.profile.title'),
            'subtitle' => __('borrower.profile.activity_subtitle'),
        ])

        @include('site.borrower.profile._tabs', ['active' => 'activity'])
        @include('site.borrower.profile._kyc_progress', ['customer' => $customer, 'active' => 'activity'])

        @include('site.borrower.profile._completion')

        <form method="POST" action="{{ route('site.borrower.profile.update', ['section' => 'activity']) }}{{ ! empty($returnUrl) ? '?return='.urlencode($returnUrl) : '' }}" enctype="multipart/form-data" class="bg-white rounded-2xl border border-gray-200 p-6"
              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.profile.save_confirm_title')), message: @js(__('borrower.profile.save_confirm_message')), confirmLabel: @js(__('borrower.profile.save')), confirmClass: 'bg-amber-500 hover:bg-amber-400 text-gray-900' })">
            @csrf @method('PUT')
            @if (! empty($returnUrl))
                <input type="hidden" name="return" value="{{ $returnUrl }}">
            @endif

            @error('employment_contract')<p class="text-xs text-red-600 mb-3">{{ $message }}</p>@enderror
            @if ($employmentContract ?? null)
                <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-3 py-2 text-xs text-emerald-800 flex items-center justify-between gap-3 flex-wrap">
                    <span>{{ __('borrower.profile.employment_contract_uploaded') }}</span>
                    <a href="{{ asset('storage/'.$employmentContract->file_path) }}" target="_blank" class="font-semibold text-emerald-700 hover:underline">
                        {{ __('borrower.profile.view_document') }}
                    </a>
                </div>
            @endif

            <x-site.activity-fields
                :activity-type="old('activity_type', $customer->activity_type ?? $customer->employment_type)"
                :activity-details="old('activity_details', $customer->activity_details ?? [])"
                :income-range="old('income_range', $customer->income_range)"
                :grouped-sections="true"
            />

            <button class="mt-6 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">{{ __('borrower.profile.save_activity') }}</button>
        </form>
    </div>

    @stack('scripts')
</x-site.borrower-layout>
