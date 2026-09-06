@php
    /** @var string $open */
    /** @var string $kind */
@endphp
<x-site.action-panel :title="$title" :open="$open">
    <div x-data="{
            step: 'form',
            message: '',
            cta: @js(__('plus.money.save')),
         }"
         x-effect="if (!{{ $open }}) { step = 'form'; message = ''; }">
        <form id="{{ $formId }}"
              x-ref="captureForm"
              method="post"
              action="{{ route('site.borrower.plus.business.save') }}"
              data-no-draft
              class="space-y-4"
              x-show="step === 'form'"
              @submit.prevent="
                const amount = $el.querySelector('[data-money-input]')?.value || '';
                const select = $el.querySelector('[name=category]');
                const other = ($el.querySelector('[name=category_other]')?.value || '').trim();
                const cat = other || (select?.options[select.selectedIndex]?.text || '');
                message = {{ \Illuminate\Support\Js::from($confirmTemplate) }}.replaceAll(':amount', amount).replaceAll(':category', cat);
                cta = {{ \Illuminate\Support\Js::from(__('plus.money.save')) }} + ' ' + amount;
                step = 'confirm';
              ">
            @csrf
            <input type="hidden" name="kind" value="{{ $kind }}">
            @php
                $bizList = $businesses ?? collect();
                $preselectedBusinessId = $selectedBusinessId ?? null;
            @endphp
            @if ($preselectedBusinessId)
                <input type="hidden" name="plus_business_id" value="{{ $preselectedBusinessId }}">
            @elseif ($bizList->count() > 1)
                <x-site.sheet-select
                    name="plus_business_id"
                    :label="__('plus.business.which_business')"
                    :options="$bizList->mapWithKeys(fn ($b) => [$b->id => $b->name])->all()"
                    :required="true"
                    :placeholder="__('plus.money.choose')"
                />
            @elseif ($bizList->count() === 1)
                <input type="hidden" name="plus_business_id" value="{{ $bizList->first()->id }}">
                <p class="text-xs text-gray-500">{{ __('plus.business.for_business', ['name' => $bizList->first()->name]) }}</p>
            @endif
            <x-site.plus-money-input :name="$amountName" :id="$amountId" :label="$amountLabel" required />
            <x-site.sheet-select
                name="category"
                :label="$categoryLabel"
                :options="$options"
                :required="true"
                :placeholder="__('plus.money.choose')"
            />
            <label class="block text-xs font-medium text-gray-600">
                {{ $noteLabel }}
                <input type="text" name="note" maxlength="160" class="mt-1 w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
            </label>
            <button type="submit" class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.money.save') }}</button>
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
