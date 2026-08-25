@php
    /** @var string $open */
    /** @var string $title */
    /** @var string $formId */
    /** @var string $direction */
    /** @var string $amountName */
    /** @var string $amountId */
    /** @var string $amountLabel */
    /** @var string $categoryLabel */
    /** @var string $categoryModel */
    /** @var array<string, string> $options */
    /** @var string $confirmTemplate */
@endphp
<x-site.action-panel :title="$title" :open="$open">
    <form id="{{ $formId }}"
          method="post"
          action="{{ route('site.borrower.plus.money.save') }}"
          data-no-draft
          class="space-y-4"
          @submit.prevent="confirmMoney($el, {{ \Illuminate\Support\Js::from($confirmTemplate) }})">
        @csrf
        <input type="hidden" name="direction" value="{{ $direction }}">
        <x-site.numeric-input :name="$amountName" :id="$amountId" :money="true" :label="$amountLabel" required />
        <x-site.sheet-select
            name="category"
            :label="$categoryLabel"
            :options="$options"
            :model="$categoryModel"
            :setter="$categorySetter ?? null"
            :required="true"
            :placeholder="__('plus.money.choose')"
        />
        <div x-show="{{ $categoryModel }} === 'other'" x-cloak>
            <label class="block text-xs font-medium text-gray-600 mb-1">
                {{ __('plus.money.other_name') }} <span class="text-red-500">*</span>
            </label>
            <input type="text"
                   name="category_other"
                   maxlength="40"
                   class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand"
                   :required="{{ $categoryModel }} === 'other'"
                   :disabled="{{ $categoryModel }} !== 'other'">
        </div>
        <button type="submit" class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.money.save') }}</button>
    </form>
</x-site.action-panel>
