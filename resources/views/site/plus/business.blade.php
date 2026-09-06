@php
    $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
    $saleOptions = collect($sale_types)->mapWithKeys(fn ($row, $key) => [$key => $row[$locale]])->all();
    $spendOptions = collect($spend_types)->mapWithKeys(fn ($row, $key) => [$key => $row[$locale]])->all();
    $selectedBusiness = collect($businesses ?? [])->firstWhere('id', (int) ($business_id ?? 0));
    $selectedLabel = $selectedBusiness
        ? $selectedBusiness->name
        : __('plus.business.all_businesses');
    $typeOptions = __('plus.business.types');
@endphp
<x-site.borrower-layout :title="brand_title(__('plus.home.business'))" active="plus">
    <div class="space-y-5" x-data="{
        saleOpen: false,
        spendOpen: false,
        bizPickerOpen: false,
        addOpen: {{ ($errors->has('name') || $errors->has('type') || $errors->has('type_other')) ? 'true' : 'false' }},
        resetAmount(id) {
            const el = document.getElementById(id);
            if (! el) return;
            el.value = '';
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }">
        <x-site.plus-nav />

        @if (session('status'))
            <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif

        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4 space-y-3">
            <p class="text-sm font-semibold text-gray-900">{{ __('plus.business.profiles_title') }}</p>

            <div class="relative" x-data="{ desktopOpen: false }" @keydown.escape.window="desktopOpen = false; bizPickerOpen = false">
                <button type="button"
                        class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm font-semibold text-gray-900 hover:border-brand/30"
                        @click="window.matchMedia('(max-width: 1023px)').matches ? bizPickerOpen = true : desktopOpen = !desktopOpen">
                    <span class="flex-1 text-left truncate">{{ $selectedLabel }}</span>
                    <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                </button>

                <div class="hidden lg:block absolute z-20 mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-xl py-1"
                     x-cloak x-show="desktopOpen" @click.outside="desktopOpen = false">
                    <a href="{{ route('site.borrower.plus.business', ['period' => $period, 'business' => 'all']) }}"
                       class="block px-4 py-2.5 text-sm {{ empty($business_id) ? 'bg-brand-muted text-brand font-semibold' : 'text-gray-800 hover:bg-gray-50' }}">{{ __('plus.business.all_businesses') }}</a>
                    @foreach (($businesses ?? []) as $biz)
                        <a href="{{ route('site.borrower.plus.business', ['period' => $period, 'business' => $biz->id]) }}"
                           class="block px-4 py-2.5 text-sm {{ (int) ($business_id ?? 0) === (int) $biz->id ? 'bg-brand-muted text-brand font-semibold' : 'text-gray-800 hover:bg-gray-50' }}">{{ $biz->name }}</a>
                    @endforeach
                    <button type="button" @click="desktopOpen = false; addOpen = true"
                            class="w-full text-left px-4 py-2.5 text-sm font-semibold text-brand hover:bg-brand-muted">{{ __('plus.business.add_new_business') }}</button>
                </div>

                <x-site.bottom-sheet :title="__('plus.business.choose_business')" open="bizPickerOpen">
                    <div class="space-y-1">
                        <a href="{{ route('site.borrower.plus.business', ['period' => $period, 'business' => 'all']) }}"
                           class="block px-4 py-3 rounded-xl text-sm {{ empty($business_id) ? 'bg-brand-muted text-brand font-semibold ring-1 ring-brand/20' : 'text-gray-800 hover:bg-gray-50' }}">{{ __('plus.business.all_businesses') }}</a>
                        @foreach (($businesses ?? []) as $biz)
                            <a href="{{ route('site.borrower.plus.business', ['period' => $period, 'business' => $biz->id]) }}"
                               class="block px-4 py-3 rounded-xl text-sm {{ (int) ($business_id ?? 0) === (int) $biz->id ? 'bg-brand-muted text-brand font-semibold ring-1 ring-brand/20' : 'text-gray-800 hover:bg-gray-50' }}">{{ $biz->name }}</a>
                        @endforeach
                        <button type="button" @click="bizPickerOpen = false; addOpen = true"
                                class="w-full text-left px-4 py-3 rounded-xl text-sm font-semibold text-brand hover:bg-brand-muted">{{ __('plus.business.add_new_business') }}</button>
                    </div>
                </x-site.bottom-sheet>
            </div>

            @if (($businesses ?? collect())->isEmpty())
                <p class="text-xs text-gray-500">{{ __('plus.business.add_first_hint') }}</p>
            @endif
        </div>

        <x-site.action-panel open="addOpen" :title="__('plus.business.add_business')" size="md">
            <form method="post" action="{{ route('site.borrower.plus.business.profile') }}" data-no-draft class="space-y-4">
                @csrf
                <input type="hidden" name="period" value="{{ $period }}">
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('plus.business.name_placeholder') }}</label>
                    <input type="text" name="name" required maxlength="120" value="{{ old('name') }}"
                           placeholder="{{ __('plus.business.name_placeholder') }}"
                           class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
                <div x-data="{ type: @js(old('type', '')), otherText: @js(old('type_other', '')) }" class="space-y-3">
                    <label class="block text-sm font-semibold text-gray-800">{{ __('plus.business.type_placeholder') }} <span class="text-rose-500">*</span></label>
                    <div class="space-y-1 max-h-48 overflow-y-auto rounded-xl ring-1 ring-gray-200 p-1.5">
                        @foreach ($typeOptions as $key => $label)
                            <label class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm cursor-pointer hover:bg-brand-muted/50"
                                   :class="type === '{{ $key }}' ? 'bg-brand-muted text-brand font-semibold ring-1 ring-brand/20' : 'text-gray-800'">
                                <input type="radio" name="type" value="{{ $key }}" x-model="type" class="text-brand focus:ring-brand" required>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div x-show="type === 'other'" x-cloak>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('plus.business.type_other_label') }} <span class="text-rose-500">*</span></label>
                        <input type="text" name="type_other" x-model="otherText" maxlength="80"
                               :required="type === 'other'"
                               class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    </div>
                </div>
                <button type="submit" class="w-full rounded-xl bg-brand text-white px-4 py-3 text-sm font-bold">{{ __('plus.business.save_business') }}</button>
            </form>
        </x-site.action-panel>

        <x-site.plus-hero kicker="Kopafasta Plus · {{ $period_label }}" :title="__('plus.business.title')" :body="__('plus.business.hero_body')">
            @if ($selectedBusiness)
                <p class="mb-3 text-sm font-semibold text-brand-gold">{{ __('plus.business.for_business', ['name' => $selectedBusiness->name]) }}</p>
            @endif
            <div class="flex gap-1 rounded-full bg-white/10 p-1 mb-4">
                @foreach (['today' => __('plus.business.today'), 'week' => __('plus.business.week'), 'month' => __('plus.business.month')] as $key => $label)
                    <a href="{{ route('site.borrower.plus.business', array_filter(['period' => $key, 'business' => $business_id ?: null])) }}"
                       class="flex-1 text-center rounded-full px-2 py-1.5 text-xs font-semibold {{ $period === $key ? 'bg-brand-gold text-brand' : 'text-white/80' }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('plus.business.sold') }}</p>
                    <p class="font-bold tabular-nums mt-1 text-lg" title="{{ format_money($summary['sold']) }}">{{ format_money_compact($summary['sold']) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-white/60">{{ __('plus.business.spent') }}</p>
                    <p class="font-bold tabular-nums mt-1 text-lg" title="{{ format_money($summary['spent']) }}">{{ format_money_compact($summary['spent']) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand-gold">{{ __('plus.business.diff') }}</p>
                    <p class="font-extrabold tabular-nums mt-1 text-lg text-brand-gold" title="{{ format_money($summary['difference']) }}">{{ ($summary['difference'] >= 0 ? '+' : '').format_money_compact($summary['difference']) }}</p>
                </div>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-2">
                <button type="button" @click="saleOpen = true; resetAmount('business-sale-amount')" class="rounded-xl bg-brand-gold text-brand px-4 py-3 text-sm font-bold">{{ __('plus.business.sold_action') }}</button>
                <button type="button" @click="spendOpen = true; resetAmount('business-spend-amount')" class="rounded-xl bg-white/10 ring-1 ring-white/20 text-white px-4 py-3 text-sm font-semibold">{{ __('plus.business.spent_action') }}</button>
            </div>

            <div class="mt-5 rounded-2xl bg-white/10 ring-1 ring-white/15 p-4">
                @if ($chart_ready)
                    <x-site.plus-week-chart :days="$chart" />
                @else
                    <p class="text-sm font-semibold text-white">{{ __('plus.business.chart_empty_title') }}</p>
                    <p class="mt-1 text-sm text-white/75">{{ __('plus.business.chart_empty_body') }}</p>
                @endif
                @if ($insight)
                    <p class="mt-3 text-sm text-white/85">{{ $insight }}</p>
                @endif
            </div>
        </x-site.plus-hero>

        @if (count($history_rows))
            @include('site.plus._history-table', [
                'title' => __('plus.business.history'),
                'dateLabel' => __('plus.business.col_date'),
                'whatLabel' => __('plus.business.col_what'),
                'amountLabel' => __('plus.business.amount'),
                'rows' => collect($history_rows)->map(fn ($row) => [
                    'date' => $row['date']->locale(app()->getLocale())->isoFormat('D MMM'),
                    'label' => ($row['kind'] === 'sale' ? __('plus.business.sale') : __('plus.business.spend')).' · '.$row['label'],
                    'in' => $row['kind'] === 'sale',
                    'amount' => $row['amount'],
                ])->all(),
            ])
        @else
            <x-site.empty-state compact icon="🏪" :title="__('plus.business.empty')" />
        @endif

        @include('site.plus._business-capture', [
            'open' => 'saleOpen',
            'title' => __('plus.business.sold_action'),
            'formId' => 'plus-business-sale',
            'kind' => 'sale',
            'amountName' => 'sold',
            'amountId' => 'business-sale-amount',
            'amountLabel' => __('plus.business.how_much_sold'),
            'categoryLabel' => __('plus.business.what_sold'),
            'options' => $saleOptions,
            'confirmTemplate' => __('plus.business.confirm_sale'),
            'noteLabel' => __('plus.business.note'),
            'selectedBusinessId' => $business_id ?: null,
        ])
        @include('site.plus._business-capture', [
            'open' => 'spendOpen',
            'title' => __('plus.business.spent_action'),
            'formId' => 'plus-business-spend',
            'kind' => 'spend',
            'amountName' => 'spent',
            'amountId' => 'business-spend-amount',
            'amountLabel' => __('plus.business.how_much_spent'),
            'categoryLabel' => __('plus.business.what_spent'),
            'options' => $spendOptions,
            'confirmTemplate' => __('plus.business.confirm_spend'),
            'noteLabel' => __('plus.business.note'),
            'selectedBusinessId' => $business_id ?: null,
        ])
    </div>
</x-site.borrower-layout>
