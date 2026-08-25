@php
    /** @var string $open */
    /** @var string $title */
    /** @var string $formId */
    /** @var string $direction */
    /** @var string $amountName */
    /** @var string $amountId */
    /** @var string $amountLabel */
    /** @var string $categoryLabel */
    /** @var array<string, string> $options */
    /** @var string $confirmTemplate */
    /** @var string $saveLabel */
@endphp
<x-site.action-panel :title="$title" :open="$open">
    <div x-data="{
            step: 'form',
            message: '',
            cta: @js($saveLabel ?? __('plus.money.save')),
         }"
         x-effect="if (!{{ $open }}) { step = 'form'; message = ''; }">
        <form id="{{ $formId }}"
              x-ref="captureForm"
              method="post"
              action="{{ route('site.borrower.plus.money.save') }}"
              data-no-draft
              class="space-y-4"
              x-show="step === 'form'"
              @submit.prevent="
                const amount = $el.querySelector('[data-money-input]')?.value || '';
                const select = $el.querySelector('[name=category]');
                const other = ($el.querySelector('[name=category_other]')?.value || '').trim();
                const cat = other || (select?.options[select.selectedIndex]?.text || '');
                message = {{ \Illuminate\Support\Js::from($confirmTemplate) }}.replaceAll(':amount', amount).replaceAll(':category', cat);
                cta = {{ \Illuminate\Support\Js::from($saveLabel ?? __('plus.money.save')) }} + ' ' + amount;
                step = 'confirm';
              ">
            @csrf
            <input type="hidden" name="direction" value="{{ $direction }}">
            <x-site.plus-money-input :name="$amountName" :id="$amountId" :label="$amountLabel" required />
            <x-site.sheet-select
                name="category"
                :label="$categoryLabel"
                :options="$options"
                :required="true"
                :placeholder="__('plus.money.choose')"
            />
            <button type="submit" class="w-full rounded-xl bg-brand text-white py-3 font-semibold" x-text="cta || {{ \Illuminate\Support\Js::from($saveLabel ?? __('plus.money.save')) }}"></button>
        </form>
        <div class="space-y-4" x-show="step === 'confirm'" x-cloak>
            <p class="text-sm font-semibold text-gray-900" x-text="message"></p>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" class="rounded-xl bg-white ring-1 ring-gray-200 py-3 text-sm font-semibold" @click="step = 'form'">{{ __('plus.learn.prev') }}</button>
                <button type="button" class="rounded-xl bg-brand text-white py-3 text-sm font-semibold" x-text="cta" @click="if (window.kfMarkBusy) window.kfMarkBusy($event.currentTarget); $refs.captureForm.submit()"></button>
            </div>
        </div>
    </div>
</x-site.action-panel>
