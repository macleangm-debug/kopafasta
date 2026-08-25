<x-site.layout :title="brand_title(__('site.plus.meta_title'))" :description="__('site.plus.meta_desc')">
    <section class="premium-gradient py-12 lg:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10 items-center">
            <div>
                <p class="text-xs uppercase tracking-[0.2em] text-brand font-bold">Kopafasta Plus</p>
                <h1 class="mt-3 text-3xl sm:text-5xl font-black text-gray-900 leading-tight">{{ __('site.plus.hero_title') }}</h1>
                <p class="mt-4 text-lg text-gray-600 max-w-xl">{{ __('site.plus.hero_body') }}</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('site.borrower.plus.home') }}" class="inline-flex rounded-xl bg-brand text-white px-5 py-3 font-semibold">{{ __('site.plus.join') }}</a>
                    <a href="#how" class="inline-flex rounded-xl bg-white ring-1 ring-gray-200 px-5 py-3 font-semibold text-gray-800">{{ __('site.plus.see_how') }}</a>
                </div>
                <p class="mt-4 text-sm text-gray-500">{{ __('site.plus.optional') }}</p>
            </div>
            <x-site.device-frame :caption="__('site.plus.caption_home')">
                @include('site._plus-phone-screen', [
                    'title' => __('plus.home.summary'),
                    'body' => __('site.plus.screen_home_body'),
                    'stats' => [
                        ['label' => __('plus.money.left_label'), 'value' => 'TZS 730K', 'gold' => true],
                        ['label' => __('plus.business.diff'), 'value' => '+1.15M'],
                        ['label' => __('plus.reports.trust'), 'value' => '81'],
                    ],
                    'bar' => 72,
                    'note' => __('site.plus.screen_home_note'),
                ])
            </x-site.device-frame>
        </div>
    </section>

    <section id="how" class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
            <div class="text-center max-w-2xl mx-auto">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __('site.plus.rooms_title') }}</h2>
                <p class="mt-2 text-gray-600">{{ __('site.plus.rooms_body') }}</p>
            </div>

            @foreach ([
                ['key' => 'money', 'title' => __('plus.home.money'), 'copy' => __('site.plus.room_money'), 'screen' => ['title' => __('plus.money.title'), 'body' => __('plus.money.hero_body'), 'stats' => [['label'=>__('plus.money.in'),'value'=>'2.4M'],['label'=>__('plus.money.out'),'value'=>'1.67M'],['label'=>__('plus.money.left_label'),'value'=>'730K','gold'=>true]], 'bar' => 30, 'note' => __('site.plus.screen_money_note')]],
                ['key' => 'business', 'title' => __('plus.home.business'), 'copy' => __('site.plus.room_business'), 'screen' => ['title' => __('plus.business.title'), 'body' => __('plus.business.hero_body'), 'stats' => [['label'=>__('plus.business.sold'),'value'=>'4.8M'],['label'=>__('plus.business.spent'),'value'=>'3.2M'],['label'=>__('plus.business.diff'),'value'=>'+1.6M','gold'=>true]], 'bar' => null, 'note' => __('site.plus.screen_biz_note')]],
                ['key' => 'goals', 'title' => __('plus.home.goals'), 'copy' => __('site.plus.room_goals'), 'screen' => ['title' => __('plus.goals.title'), 'body' => __('plus.goals.hero_body'), 'stats' => [['label'=>__('plus.goals.title'),'value'=>'68%','gold'=>true]], 'bar' => 68, 'note' => __('site.plus.screen_goal_note')]],
                ['key' => 'reports', 'title' => __('plus.home.reports'), 'copy' => __('site.plus.room_reports'), 'screen' => ['title' => __('plus.reports.heading'), 'body' => __('plus.reports.hero_body'), 'stats' => [['label'=>__('plus.money.left_label'),'value'=>'730K','gold'=>true],['label'=>__('plus.business.diff'),'value'=>'+1.15M'],['label'=>__('plus.reports.trust'),'value'=>'81']], 'bar' => 81, 'note' => __('site.plus.screen_report_note')]],
            ] as $i => $room)
                <div class="grid lg:grid-cols-2 gap-10 items-center {{ $i % 2 === 1 ? 'lg:[&>*:first-child]:order-2' : '' }}">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900">{{ $room['title'] }}</h3>
                        <p class="mt-3 text-gray-600">{{ $room['copy'] }}</p>
                    </div>
                    <x-site.device-frame>
                        @include('site._plus-phone-screen', $room['screen'])
                    </x-site.device-frame>
                </div>
            @endforeach
        </div>
    </section>

    <section class="py-16 bg-[#faf8f5]">
        <div class="max-w-3xl mx-auto px-4 text-center">
            <h2 class="text-2xl font-bold">{{ __('site.plus.report_title') }}</h2>
            <p class="mt-3 text-gray-600">{{ __('site.plus.report_body') }}</p>
            <div class="mt-8 rounded-3xl bg-brand text-white p-6 sm:p-8 text-left shadow-xl">
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">{{ __('site.plus.report_kicker') }}</p>
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div><p class="text-white/60 text-xs">{{ __('plus.money.left_label') }}</p><p class="text-2xl font-black">TZS 730K</p></div>
                    <div><p class="text-white/60 text-xs">{{ __('plus.business.diff') }}</p><p class="text-2xl font-black text-brand-gold">+TZS 1.15M</p></div>
                    <div><p class="text-white/60 text-xs">{{ __('plus.home.goals') }}</p><p class="text-2xl font-black">TZS 180K</p></div>
                    <div><p class="text-white/60 text-xs">{{ __('plus.reports.trust') }}</p><p class="text-2xl font-black">81 ↑</p></div>
                </div>
                <p class="mt-5 text-sm text-white/80">{{ __('site.plus.report_line') }}</p>
            </div>
        </div>
    </section>

    <section class="py-16 bg-white">
        <div class="max-w-xl mx-auto px-4 text-center">
            <h2 class="text-2xl font-bold">Kopafasta Plus</h2>
            <p class="mt-4 text-4xl font-black text-brand tabular-nums">
                {{ format_money($price['amount'] ?? 0) }}
                <span class="text-base font-semibold text-gray-500">/ {{ $cycle === 'monthly' ? __('site.plus.per_month') : __('site.plus.per_year') }}</span>
            </p>
            <ul class="mt-6 space-y-2 text-sm text-gray-700 text-left max-w-sm mx-auto">
                @foreach (__('site.plus.includes') as $item)
                    <li>✓ {{ $item }}</li>
                @endforeach
            </ul>
            <a href="{{ route('site.borrower.plus.home') }}" class="mt-8 inline-flex rounded-xl bg-brand text-white px-6 py-3 font-semibold">{{ __('site.plus.join') }}</a>
            <p class="mt-4 text-xs text-gray-500">{{ __('site.plus.optional') }}</p>
        </div>
    </section>
</x-site.layout>
