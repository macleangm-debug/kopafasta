<x-site.borrower-layout :title="brand_title(__('plus.home.goals'))" active="dashboard">
    @php
        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $kindOptions = collect($kinds)->mapWithKeys(fn ($meta, $key) => [$key => ($meta['icon'] ?? '').' '.$meta[$locale]])->all();
        $minDate = now()->addDay()->toDateString();
    @endphp
    <div class="space-y-5" x-data="{ newOpen: false, addId: null, menuId: null, editId: null, kind: '' }">
        <x-site.plus-nav />

        <x-site.plus-hero kicker="Kopafasta Plus" :title="__('plus.goals.title')" :body="__('plus.goals.hero_body')">
            <div class="flex items-end justify-between gap-3">
                <div>
                    <p class="text-sm text-white/80">{{ __('plus.goals.active_count', ['count' => $active->count()]) }}</p>
                </div>
                <button type="button" @click="newOpen = true" class="rounded-xl bg-brand-gold text-brand px-4 py-2.5 text-sm font-bold">{{ __('plus.goals.new') }}</button>
            </div>
        </x-site.plus-hero>

        @forelse ($goals as $goal)
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-bold text-gray-900 text-lg">{{ $goal->kindIcon() }} {{ $goal->title }}</p>
                        @if ($goal->target_date)
                            <p class="text-xs text-brand font-semibold mt-1">{{ __('plus.goals.by', ['date' => $goal->target_date->locale(app()->getLocale())->isoFormat('D MMM YYYY')]) }}</p>
                        @endif
                    </div>
                    @unless ($goal->isComplete())
                        <div class="relative">
                            <button type="button" class="p-2 rounded-lg text-gray-500 hover:bg-gray-50" @click="menuId = menuId === {{ $goal->id }} ? null : {{ $goal->id }}" aria-label="{{ __('plus.goals.more') }}">•••</button>
                            <div x-cloak x-show="menuId === {{ $goal->id }}" @click.outside="menuId = null" class="absolute right-0 mt-1 w-40 rounded-xl bg-white shadow-lg ring-1 ring-gray-200 py-1 z-10">
                                <button type="button" class="block w-full text-left px-3 py-2 text-sm" @click="editId = {{ $goal->id }}; menuId = null">{{ __('plus.goals.edit') }}</button>
                                <form method="post" action="{{ route('site.borrower.plus.goals.pause', $goal) }}">
                                    @csrf
                                    <button class="block w-full text-left px-3 py-2 text-sm">{{ $goal->isPaused() ? __('plus.goals.resume') : __('plus.goals.pause') }}</button>
                                </form>
                                <form method="post" action="{{ route('site.borrower.plus.goals.complete', $goal) }}">
                                    @csrf
                                    <button class="block w-full text-left px-3 py-2 text-sm">{{ __('plus.goals.mark_complete') }}</button>
                                </form>
                            </div>
                        </div>
                    @endunless
                </div>
                <p class="text-sm text-gray-600 mt-3 tabular-nums" title="{{ format_money($goal->saved_amount) }} / {{ format_money($goal->target_amount) }}">
                    <span class="font-bold text-gray-900">{{ format_money_compact($goal->saved_amount) }}</span>
                    / {{ format_money_compact($goal->target_amount) }}
                </p>
                <div class="mt-2 h-2.5 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full bg-brand rounded-full transition-all duration-700" style="width: {{ $goal->progressPercent() }}%"></div>
                </div>
                <p class="text-sm font-semibold mt-2">{{ $goal->progressPercent() }}% · {{ __('plus.goals.remaining', ['amount' => format_money_compact($goal->remaining())]) }}</p>
                @if ($goal->isComplete())
                    <p class="text-sm text-emerald-700 font-medium mt-2">{{ __('plus.goals.completed') }}</p>
                @elseif ($goal->isPaused())
                    <p class="text-sm text-gray-500 mt-2">{{ __('plus.goals.paused') }}</p>
                @else
                    <button type="button" @click="addId = {{ $goal->id }}" class="mt-3 rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">+ {{ __('plus.goals.add_money') }}</button>
                @endif

                @if ($goal->contributions->isNotEmpty())
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-2">{{ __('plus.money.history') }}</p>
                        <div class="hidden lg:block">
                            <table class="w-full text-sm">
                                @foreach ($goal->contributions as $row)
                                    <tr class="border-t border-gray-50">
                                        <td class="py-2 text-gray-600">{{ $row->created_at->locale(app()->getLocale())->isoFormat('D MMM') }}</td>
                                        <td class="py-2 text-right font-semibold tabular-nums">+ {{ format_money($row->amount) }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                        <div class="lg:hidden space-y-2">
                            @foreach ($goal->contributions as $row)
                                <div class="rounded-xl bg-gray-50 px-3 py-2 text-sm flex justify-between">
                                    <span>{{ $row->created_at->locale(app()->getLocale())->isoFormat('D MMM') }}</span>
                                    <span class="font-semibold tabular-nums">+ {{ format_money($row->amount) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <x-site.action-panel title="{{ __('plus.goals.add_money') }}" open="addId === {{ $goal->id }}">
                    <div x-data="{ step: 'form', message: '', cta: '' }" x-effect="if (addId !== {{ $goal->id }}) step = 'form'">
                        <form x-ref="addForm" method="post" action="{{ route('site.borrower.plus.goals.contribute', $goal) }}" data-no-draft class="space-y-4"
                              x-show="step === 'form'"
                              @submit.prevent="
                                const amount = $el.querySelector('[data-money-input]')?.value || '';
                                message = {{ \Illuminate\Support\Js::from(__('plus.goals.confirm_add')) }}.replaceAll(':amount', amount);
                                cta = {{ \Illuminate\Support\Js::from(__('plus.goals.add')) }} + ' ' + amount;
                                step = 'confirm';
                              ">
                            @csrf
                            <x-site.plus-money-input name="amount" :id="'goal-add-'.$goal->id" :label="__('plus.goals.how_much')" required />
                            <button class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.goals.add') }}</button>
                        </form>
                        <div class="space-y-4" x-show="step === 'confirm'" x-cloak>
                            <p class="text-sm font-semibold" x-text="message"></p>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" class="rounded-xl bg-white ring-1 ring-gray-200 py-3 text-sm font-semibold" @click="step = 'form'">{{ __('plus.learn.prev') }}</button>
                                <button type="button" class="rounded-xl bg-brand text-white py-3 text-sm font-semibold" x-text="cta" @click="$refs.addForm.submit()"></button>
                            </div>
                        </div>
                    </div>
                </x-site.action-panel>

                <x-site.action-panel title="{{ __('plus.goals.edit') }}" open="editId === {{ $goal->id }}">
                    <form method="post" action="{{ route('site.borrower.plus.goals.update', $goal) }}" class="space-y-4">
                        @csrf
                        <label class="block text-xs font-medium text-gray-600">{{ __('plus.goals.name') }}
                            <input name="title" value="{{ $goal->title }}" required class="mt-1 w-full rounded-xl ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        </label>
                        <x-site.plus-money-input name="target_amount" :id="'goal-edit-'.$goal->id" :label="__('plus.goals.target')" :value="$goal->target_amount" required />
                        <x-site.date-input
                            name="target_date"
                            :label="__('plus.goals.date')"
                            :min="$minDate"
                            :max="now()->addYears(10)->toDateString()"
                            :value="$goal->target_date?->toDateString()"
                            :default="$goal->target_date?->toDateString() ?: now()->addMonths(3)->toDateString()"
                        />
                        <button class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.goals.save') }}</button>
                    </form>
                </x-site.action-panel>
            </div>
        @empty
            <x-site.empty-state compact icon="🎯" :title="__('plus.goals.empty')" />
        @endforelse

        <x-site.action-panel title="{{ __('plus.goals.kind') }}" open="newOpen">
            <form method="post" action="{{ route('site.borrower.plus.goals.save') }}" data-no-draft class="space-y-4">
                @csrf
                <x-site.sheet-select
                    name="kind"
                    :label="__('plus.goals.kind')"
                    :options="$kindOptions"
                    model="kind"
                    :required="true"
                    :placeholder="__('plus.money.choose')"
                />
                <div x-show="kind === 'other'" x-cloak>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('plus.goals.other_name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="title" maxlength="80" class="w-full rounded-xl ring-1 ring-gray-200 px-3 py-2.5 text-sm" :required="kind === 'other'" :disabled="kind !== 'other'">
                </div>
                <x-site.plus-money-input name="target_amount" :label="__('plus.goals.target')" required />
                <x-site.date-input
                    name="target_date"
                    :label="__('plus.goals.date')"
                    :min="$minDate"
                    :max="now()->addYears(10)->toDateString()"
                    :default="now()->addMonths(3)->toDateString()"
                />
                <button class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.goals.save') }}</button>
            </form>
        </x-site.action-panel>
    </div>
</x-site.borrower-layout>
