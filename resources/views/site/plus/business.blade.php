<x-site.borrower-layout :title="brand_title(__('plus.home.business'))" active="dashboard">
    <div class="space-y-5" x-data="{
        saleOpen: false,
        spendOpen: false,
        confirmTitle: @js(__('plus.business.confirm_title')),
        confirmLabel: @js(__('plus.money.save')),
        resetAmount(id) {
            const el = document.getElementById(id);
            if (! el) return;
            el.value = '';
            el.dispatchEvent(new Event('input', { bubbles: true }));
        },
        confirmAmount(form, template) {
            const amount = form.querySelector('[data-money-input]')?.value || '';
            window.confirmForm(form, {
                title: this.confirmTitle,
                message: String(template).replaceAll(':amount', amount),
                confirmLabel: this.confirmLabel,
                confirmClass: 'bg-brand hover:bg-brand-light text-white',
            });
        }
    }">
        <x-site.plus-nav />

        <x-site.plus-hero kicker="Kopafasta Plus · {{ __('plus.business.today') }}" :title="__('plus.business.title')" :body="__('plus.business.hero_body')">
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('plus.business.sold') }}</p>
                    <p class="font-bold tabular-nums mt-1">{{ format_money($today['sold']) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('plus.business.spent') }}</p>
                    <p class="font-bold tabular-nums mt-1">{{ format_money($today['spent']) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand-gold">{{ __('plus.business.diff') }}</p>
                    <p class="font-extrabold tabular-nums mt-1 text-brand-gold">{{ ($today['difference'] >= 0 ? '+' : '').format_money($today['difference']) }}</p>
                </div>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-2">
                <button type="button" @click="saleOpen = true; resetAmount('business-sale-amount')" class="rounded-xl bg-brand-gold text-brand px-4 py-3 text-sm font-bold">{{ __('plus.business.sold_action') }}</button>
                <button type="button" @click="spendOpen = true; resetAmount('business-spend-amount')" class="rounded-xl bg-white/10 ring-1 ring-white/20 text-white px-4 py-3 text-sm font-semibold">{{ __('plus.business.spent_action') }}</button>
            </div>

            <div class="mt-5 rounded-2xl bg-white/10 ring-1 ring-white/15 p-4">
                <p class="text-[10px] uppercase tracking-[0.16em] text-brand-gold font-bold">{{ __('plus.business.week') }}</p>
                <div class="mt-2 grid grid-cols-3 gap-2 text-sm">
                    <div>
                        <p class="text-white/60">{{ __('plus.business.sold') }}</p>
                        <p class="font-semibold tabular-nums">{{ format_money($week['sold']) }}</p>
                    </div>
                    <div>
                        <p class="text-white/60">{{ __('plus.business.spent') }}</p>
                        <p class="font-semibold tabular-nums">{{ format_money($week['spent']) }}</p>
                    </div>
                    <div>
                        <p class="text-white/60">{{ __('plus.business.diff') }}</p>
                        <p class="font-bold tabular-nums">{{ format_money($week['difference']) }}</p>
                    </div>
                </div>
                @if ($points->count() > 1)
                    <div class="mt-4 flex items-end gap-1 h-16">
                        @php $max = max(1, $points->max('sold')); @endphp
                        @foreach ($points as $point)
                            <div class="flex-1 bg-brand-gold/40 rounded-t" style="height: {{ max(8, round(($point['sold'] / $max) * 100)) }}%"></div>
                        @endforeach
                    </div>
                @endif
                @if ($insight)
                    <p class="mt-3 text-sm text-white/85">{{ $insight }}</p>
                @endif
            </div>
        </x-site.plus-hero>

        <div>
            <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-2">{{ __('plus.business.history') }}</p>
            @forelse ($history->take(8) as $entry)
                <div class="rounded-xl bg-white ring-1 ring-gray-100 px-4 py-3 text-sm flex justify-between gap-3 mb-2">
                    <span>{{ $entry->entry_date?->locale(app()->getLocale())->isoFormat('D MMM') }}</span>
                    <span class="tabular-nums">{{ __('plus.business.sold') }} {{ format_money($entry->sold) }} · {{ __('plus.business.spent') }} {{ format_money($entry->spent) }}</span>
                </div>
            @empty
                <x-site.empty-state compact icon="🏪" :title="__('plus.business.empty')" />
            @endforelse
        </div>

        <x-site.action-panel title="{{ __('plus.business.sold_action') }}" open="saleOpen">
            <form id="plus-business-sale" method="post" action="{{ route('site.borrower.plus.business.save') }}" data-no-draft class="space-y-4"
                  @submit.prevent="confirmAmount($el, {{ \Illuminate\Support\Js::from(__('plus.business.confirm_sale')) }})">
                @csrf
                <x-site.numeric-input name="sold" id="business-sale-amount" :money="true" :label="__('plus.business.amount')" required />
                <button class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.money.save') }}</button>
            </form>
        </x-site.action-panel>
        <x-site.action-panel title="{{ __('plus.business.spent_action') }}" open="spendOpen">
            <form id="plus-business-spend" method="post" action="{{ route('site.borrower.plus.business.save') }}" data-no-draft class="space-y-4"
                  @submit.prevent="confirmAmount($el, {{ \Illuminate\Support\Js::from(__('plus.business.confirm_spend')) }})">
                @csrf
                <x-site.numeric-input name="spent" id="business-spend-amount" :money="true" :label="__('plus.business.amount')" required />
                <button class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.money.save') }}</button>
            </form>
        </x-site.action-panel>
    </div>
</x-site.borrower-layout>
