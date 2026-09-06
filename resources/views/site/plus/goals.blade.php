@php
    $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
    $kindOptions = collect($kinds)->mapWithKeys(fn ($meta, $key) => [$key => ($meta['icon'] ?? '').' '.$meta[$locale]])->all();
    $minDate = now()->addDay()->toDateString();
    $selectedGoal = $selected ?? null;
    $cardTitle = $selectedGoal
        ? $selectedGoal->title
        : __('plus.goals.your_goals');
    $selectedLabel = $selectedGoal
        ? $selectedGoal->title
        : __('plus.goals.all_goals');
    $completed = ($goals ?? collect())->filter(fn ($g) => $g->isComplete());
    $openGoals = ($goals ?? collect())->filter(fn ($g) => ! $g->isComplete());
@endphp
<x-site.borrower-layout :title="brand_title(__('plus.home.goals'))" active="plus">
    <div class="space-y-5" x-data="{
        newOpen: {{ ($errors->any() && ! old('amount')) ? 'true' : 'false' }},
        addOpen: {{ ! empty($open_add) ? 'true' : 'false' }},
        goalPickerOpen: false,
        desktopOpen: false,
        menuId: null,
        editId: null
    }">
        <x-site.plus-nav />

        @if (session('status'))
            <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif

        <x-site.plus-hero kicker="Kopafasta Plus" :title="$cardTitle" :body="__('plus.goals.hero_body')">
            <div class="relative mb-4" @keydown.escape.window="desktopOpen = false; goalPickerOpen = false">
                <button type="button"
                        class="w-full inline-flex items-center gap-3 rounded-xl bg-white/10 ring-1 ring-white/20 px-4 py-3 text-sm font-semibold text-white hover:bg-white/15"
                        @click="window.matchMedia('(max-width: 1023px)').matches ? goalPickerOpen = true : desktopOpen = !desktopOpen">
                    <span class="flex-1 text-left truncate">{{ $selectedLabel }}</span>
                    <svg class="w-4 h-4 text-white/70 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                </button>
                <div class="hidden lg:block absolute z-20 mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-xl py-1 text-gray-900"
                     x-cloak x-show="desktopOpen" @click.outside="desktopOpen = false">
                    <a href="{{ route('site.borrower.plus.goals', ['goal' => 'all']) }}"
                       class="block px-4 py-2.5 text-sm {{ empty($goal_id) ? 'bg-brand-muted text-brand font-semibold' : 'text-gray-800 hover:bg-gray-50' }}">{{ __('plus.goals.all_goals') }}</a>
                    @foreach ($openGoals as $g)
                        <a href="{{ route('site.borrower.plus.goals', ['goal' => $g->id]) }}"
                           class="block px-4 py-2.5 text-sm {{ (int) ($goal_id ?? 0) === (int) $g->id ? 'bg-brand-muted text-brand font-semibold' : 'text-gray-800 hover:bg-gray-50' }}">{{ $g->kindIcon() }} {{ $g->title }}</a>
                    @endforeach
                    <button type="button" @click="desktopOpen = false; newOpen = true"
                            class="w-full text-left px-4 py-2.5 text-sm font-semibold text-brand hover:bg-brand-muted">{{ __('plus.goals.add_new_goal') }}</button>
                </div>
                <x-site.bottom-sheet :title="__('plus.goals.choose_goal')" open="goalPickerOpen">
                    <div class="space-y-1">
                        <a href="{{ route('site.borrower.plus.goals', ['goal' => 'all']) }}"
                           class="block px-4 py-3 rounded-xl text-sm {{ empty($goal_id) ? 'bg-brand-muted text-brand font-semibold ring-1 ring-brand/20' : 'text-gray-800 hover:bg-gray-50' }}">{{ __('plus.goals.all_goals') }}</a>
                        @foreach ($openGoals as $g)
                            <a href="{{ route('site.borrower.plus.goals', ['goal' => $g->id]) }}"
                               class="block px-4 py-3 rounded-xl text-sm {{ (int) ($goal_id ?? 0) === (int) $g->id ? 'bg-brand-muted text-brand font-semibold ring-1 ring-brand/20' : 'text-gray-800 hover:bg-gray-50' }}">{{ $g->kindIcon() }} {{ $g->title }}</a>
                        @endforeach
                        <button type="button" @click="goalPickerOpen = false; newOpen = true"
                                class="w-full text-left px-4 py-3 rounded-xl text-sm font-semibold text-brand hover:bg-brand-muted">{{ __('plus.goals.add_new_goal') }}</button>
                    </div>
                </x-site.bottom-sheet>
            </div>

            <p class="text-sm text-white/80">{{ __('plus.goals.active_count', ['count' => $active->count()]) }}</p>

            @if ($selectedGoal && ! $selectedGoal->isComplete())
                <div class="mt-4">
                    <p class="text-sm text-white/90 tabular-nums">
                        <span class="font-bold text-white">{{ format_money_compact($selectedGoal->saved_amount) }}</span>
                        / {{ format_money_compact($selectedGoal->target_amount) }}
                    </p>
                    <div class="mt-2 h-2.5 rounded-full bg-white/15 overflow-hidden">
                        <div class="h-full bg-brand-gold rounded-full" style="width: {{ $selectedGoal->progressPercent() }}%"></div>
                    </div>
                    <p class="text-sm font-semibold mt-2 text-white">{{ $selectedGoal->progressPercent() }}% · {{ __('plus.goals.remaining', ['amount' => format_money_compact($selectedGoal->remaining())]) }}</p>
                    <button type="button" @click="addOpen = true" class="mt-4 rounded-xl bg-brand-gold text-brand px-4 py-2.5 text-sm font-bold">+ {{ __('plus.goals.add_money') }}</button>
                </div>
            @elseif ($lead)
                <div class="mt-4">
                    <p class="text-sm text-white/80">{{ $lead->title }} · {{ $lead->progressPercent() }}%</p>
                    <div class="mt-2 h-2.5 rounded-full bg-white/15 overflow-hidden">
                        <div class="h-full bg-brand-gold rounded-full" style="width: {{ $lead->progressPercent() }}%"></div>
                    </div>
                </div>
            @endif
        </x-site.plus-hero>

        @php $list = $selectedGoal ? collect([$selectedGoal]) : $openGoals; @endphp
        @forelse ($list as $goal)
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 shadow-sm relative z-0">
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
                            <div x-cloak x-show="menuId === {{ $goal->id }}" @click.outside="if (window.matchMedia('(min-width: 1024px)').matches) menuId = null" class="hidden lg:block absolute right-0 mt-1 w-44 rounded-xl bg-white shadow-lg ring-1 ring-gray-200 py-1 z-30">
                                <button type="button" class="block w-full text-left px-3 py-2 text-sm" @click="editId = {{ $goal->id }}; menuId = null">{{ __('plus.goals.edit') }}</button>
                                <form method="post" action="{{ route('site.borrower.plus.goals.pause', $goal) }}">
                                    @csrf
                                    <button class="block w-full text-left px-3 py-2 text-sm">{{ $goal->isPaused() ? __('plus.goals.resume') : __('plus.goals.pause') }}</button>
                                </form>
                            </div>
                        </div>
                    @endunless
                </div>
                <p class="text-sm text-gray-600 mt-3 tabular-nums">
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
                    <a href="{{ route('site.borrower.plus.goals', ['goal' => $goal->id, 'add' => 1]) }}" class="mt-3 inline-flex rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">+ {{ __('plus.goals.add_money') }}</a>
                @endif

                @if ($goal->contributions->isNotEmpty())
                    <div class="mt-4 pt-4 border-t border-gray-100" x-data="{ histOpen: true }">
                        <button type="button" class="w-full flex items-center justify-between text-left" @click="histOpen = !histOpen">
                            <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold">{{ __('plus.money.history') }}</p>
                        </button>
                        <div class="mt-2 max-h-[13.75rem] overflow-y-auto overscroll-contain space-y-2 pr-1" x-show="histOpen" x-cloak>
                            @foreach ($goal->contributions as $row)
                                <div class="rounded-xl bg-gray-50 px-3 py-2 text-sm flex justify-between">
                                    <span>{{ $row->created_at->locale(app()->getLocale())->isoFormat('D MMM') }}</span>
                                    <span class="font-semibold tabular-nums">+ {{ format_money($row->amount) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

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
                            :help="__('plus.goals.date_help')"
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

        @if ($completed->isNotEmpty() && ! $selectedGoal)
            <section class="space-y-3">
                <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('plus.goals.archived_title') }}</p>
                @foreach ($completed as $goal)
                    <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 opacity-95">
                        <p class="font-bold text-gray-900">{{ $goal->kindIcon() }} {{ $goal->title }}</p>
                        <p class="text-sm text-emerald-700 font-medium mt-1">{{ __('plus.goals.completed') }} · 100%</p>
                        <p class="text-sm text-gray-600 mt-2 tabular-nums">{{ format_money_compact($goal->saved_amount) }} / {{ format_money_compact($goal->target_amount) }}</p>
                        @if ($goal->contributions->isNotEmpty())
                            <div class="mt-3 space-y-1 max-h-40 overflow-y-auto">
                                @foreach ($goal->contributions as $row)
                                    <div class="text-xs flex justify-between text-gray-600">
                                        <span>{{ $row->created_at->locale(app()->getLocale())->isoFormat('D MMM YYYY') }}</span>
                                        <span>+ {{ format_money($row->amount) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif

        @php $addTarget = $selectedGoal && ! $selectedGoal->isComplete() ? $selectedGoal : $lead; @endphp
        @if ($addTarget && ! $addTarget->isComplete())
            <x-site.action-panel title="{{ __('plus.goals.add_money') }}" open="addOpen">
                <div x-data="{ step: 'form', message: '', cta: '' }" x-effect="if (! addOpen) step = 'form'">
                    <form x-ref="addForm" method="post" action="{{ route('site.borrower.plus.goals.contribute', $addTarget) }}" data-no-draft class="space-y-4"
                          x-show="step === 'form'"
                          @submit.prevent="
                            const amount = $el.querySelector('[data-money-input]')?.value || '';
                            message = {{ \Illuminate\Support\Js::from(__('plus.goals.confirm_add')) }}.replaceAll(':amount', amount);
                            cta = {{ \Illuminate\Support\Js::from(__('plus.goals.add')) }} + ' ' + amount;
                            step = 'confirm';
                          ">
                        @csrf
                        <p class="text-sm text-gray-600">{{ $addTarget->title }}</p>
                        <x-site.plus-money-input name="amount" :id="'goal-add-'.$addTarget->id" :label="__('plus.goals.how_much')" required />
                        <p class="text-xs text-gray-500">{{ __('plus.goals.record_only') }}</p>
                        <button class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.goals.add') }}</button>
                    </form>
                    <div class="space-y-4" x-show="step === 'confirm'" x-cloak>
                        <p class="text-sm font-semibold" x-text="message"></p>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" class="rounded-xl bg-white ring-1 ring-gray-200 py-3 text-sm font-semibold" @click="step = 'form'">{{ __('plus.learn.prev') }}</button>
                            <button type="button" class="rounded-xl bg-brand text-white py-3 text-sm font-semibold" x-text="cta" @click="if (window.kfMarkBusy) window.kfMarkBusy($event.currentTarget); $refs.addForm.submit()"></button>
                        </div>
                    </div>
                </div>
            </x-site.action-panel>
        @endif

        <x-site.action-panel title="{{ __('plus.goals.kind') }}" open="newOpen">
            <form method="post" action="{{ route('site.borrower.plus.goals.save') }}" data-no-draft class="space-y-4">
                @csrf
                <x-site.sheet-select
                    name="kind"
                    :label="__('plus.goals.kind')"
                    :options="$kindOptions"
                    :required="true"
                    :placeholder="__('plus.money.choose')"
                    other-name="title"
                    :other-label="__('plus.goals.other_name')"
                />
                <x-site.plus-money-input name="target_amount" :label="__('plus.goals.target')" required />
                <x-site.date-input
                    name="target_date"
                    :label="__('plus.goals.date')"
                    :help="__('plus.goals.date_help')"
                    :min="$minDate"
                    :max="now()->addYears(10)->toDateString()"
                    :default="now()->addMonths(3)->toDateString()"
                />
                <button class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.goals.save') }}</button>
            </form>
        </x-site.action-panel>
    </div>
</x-site.borrower-layout>
