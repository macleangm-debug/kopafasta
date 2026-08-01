<x-site.borrower-layout :title="brand_title(__('borrower.loan_actions.restructure_title'))" active="loans" content-width="wide">
    <div>
        <a href="{{ route('site.borrower.loans.show', $loan) }}" class="text-sm font-semibold text-brand hover:underline">← {{ $loan->loan_number }}</a>

        <x-site.borrower-page-header
            class="mt-4"
            :title="__('borrower.loan_actions.restructure_title')"
            :subtitle="$loan->loan_number.' · '.($loan->product?->name ?? '')"
        />

        @if ($blocked)
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm text-amber-900">{{ $blocked }}</div>
        @else
            <form method="post" action="{{ route('site.borrower.loans.restructure.submit', $loan) }}" class="glass-card p-6 space-y-5"
                  x-data="{
                    type: @js(old('restructure_type', '')),
                    pickerOpen: false,
                    options: @js($types),
                    pick(val) { this.type = val; this.pickerOpen = false; }
                  }">
                @csrf
                <p class="text-sm text-gray-700">{{ __('borrower.loan_actions.restructure_hint') }}</p>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.loan_actions.restructure_type_label') }}</label>
                    <div class="lg:hidden">
                        <button type="button" @click="pickerOpen = true"
                                class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800">
                            <span class="flex-1 text-left truncate" x-text="options[type] || @js(__('borrower.profile.select'))"></span>
                            <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                        </button>
                        <x-site.bottom-sheet :title="__('borrower.loan_actions.restructure_type_label')" open="pickerOpen">
                            <div class="space-y-1 max-h-[60vh] overflow-y-auto">
                                <template x-for="(label, key) in options" :key="key">
                                    <button type="button" @click="pick(key)"
                                            class="w-full text-left px-4 py-3 rounded-xl text-sm font-medium text-gray-800 hover:bg-gray-50"
                                            :class="type === key ? 'bg-brand-muted text-brand ring-1 ring-brand/20' : ''"
                                            x-text="label"></button>
                                </template>
                            </div>
                        </x-site.bottom-sheet>
                    </div>
                    <select name="restructure_type" required x-model="type"
                            class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand max-lg:absolute max-lg:opacity-0 max-lg:pointer-events-none max-lg:h-0 max-lg:overflow-hidden">
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
                    <x-site.numeric-input name="new_tenure_months" :value="old('new_tenure_months')" min="1" max="{{ $holidayMaxMonths ?? 120 }}" step="1" placeholder="e.g. 3" />
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
                              class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand">{{ old('reason') }}</textarea>
                    @error('reason')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    @error('restructure')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="inline-flex bg-brand hover:bg-brand-light text-white font-semibold px-5 py-2.5 rounded-xl text-sm">
                    {{ __('borrower.loan_actions.submit_restructure') }}
                </button>
            </form>
        @endif
    </div>
</x-site.borrower-layout>
