<x-site.borrower-layout :title="brand_title(__('borrower.loan_actions.restructure_title'))" active="loans" content-width="wide">
    <div>
        <a href="{{ route('site.borrower.loans', ['tab' => 'active']) }}" class="text-sm font-semibold text-amber-700 hover:text-amber-800">← {{ __('borrower.loans_page.tab_active') }}</a>
        <h1 class="text-2xl font-bold mt-4 mb-2">{{ __('borrower.loan_actions.restructure_title') }}</h1>
        <p class="text-sm text-gray-500 mb-6">{{ $loan->loan_number }} · {{ $loan->product?->name }}</p>

        @if ($blocked)
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm text-amber-900">{{ $blocked }}</div>
        @else
            <form method="post" action="{{ route('site.borrower.loans.restructure.submit', $loan) }}" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5" x-data="{ type: @js(old('restructure_type', '')) }">
                @csrf
                <p class="text-sm text-gray-700">{{ __('borrower.loan_actions.restructure_hint') }}</p>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.loan_actions.restructure_type_label') }}</label>
                    <select name="restructure_type" required x-model="type" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        <option value="">{{ __('borrower.profile.select') }}</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('restructure_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('restructure_type')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        <span x-show="type === 'payment_holiday'">{{ __('borrower.loan_actions.holiday_months_label') }}</span>
                        <span x-show="type !== 'payment_holiday'">{{ __('borrower.loan_actions.new_tenure_label') }} {{ __('borrower.profile.optional') }}</span>
                    </label>
                    <input type="number" name="new_tenure_months" min="1" max="{{ $holidayMaxMonths ?? 120 }}" value="{{ old('new_tenure_months') }}"
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" placeholder="e.g. 3">
                    <p x-show="type === 'payment_holiday'" class="mt-2 text-xs text-gray-500">
                        {{ __('borrower.loan_actions.holiday_months_max_hint', ['max' => $holidayMaxMonths ?? 3]) }}
                        @if ($holidayAccrueInterest ?? true)
                            {{ __('borrower.loan_actions.holiday_interest_accrues') }}
                        @else
                            {{ __('borrower.loan_actions.holiday_interest_paused') }}
                        @endif
                    </p>
                    @error('new_tenure_months')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.loan_actions.reason_label') }}</label>
                    <textarea name="reason" rows="4" required maxlength="500"
                              class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">{{ old('reason') }}</textarea>
                    @error('reason')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    @error('restructure')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                    {{ __('borrower.loan_actions.submit_restructure') }}
                </button>
            </form>
        @endif
    </div>
</x-site.borrower-layout>
