<x-site.layout :title="brand_name().' — '.__('site.rewards.title')">
    <x-site.public-hero
        variant="feature"
        :eyebrow="__('site.rewards.title')"
        :title="__('site.rewards.kicker')"
        :body="__('site.rewards.intro')"
        :primary-href="auth()->check() ? route('site.borrower.engagement') : route('site.register.borrower')"
        :primary-label="auth()->check() ? __('site.rewards.see') : __('site.rewards.cta')"
    />

    <section class="py-10 lg:py-14">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
            <div class="max-w-3xl">
                <h2 class="text-xl font-bold text-gray-900">{{ __('site.rewards.how_title') }}</h2>
                <p class="mt-3 text-sm sm:text-[15px] text-gray-600 leading-relaxed">{{ __('site.rewards.how_body') }}</p>
                <p class="mt-3 text-sm text-gray-600 leading-relaxed">{{ __('site.rewards.signin_note') }}</p>
            </div>

            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ __('site.rewards.qualify_title') }}</h2>
                <p class="mt-2 text-sm text-gray-500">{{ __('site.rewards.not_every') }}</p>
                @if (count($earn) === 0)
                    <p class="mt-4 text-sm text-gray-500">{{ __('site.rewards.earn_empty') }}</p>
                @else
                    <div class="mt-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach ($earn as $row)
                            <div class="h-full rounded-2xl ring-1 ring-gray-200 bg-white px-4 py-4 flex flex-col justify-between gap-3">
                                <span class="text-sm font-medium text-gray-800">{{ $row['label'] }}</span>
                                <span class="text-sm font-black tabular-nums text-brand">+{{ number_format($row['points']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ __('site.rewards.catalog_title') }}</h2>
                <p class="mt-2 text-sm text-gray-500">{{ __('site.rewards.redeem_how') }}</p>
                @if (count($catalog) === 0)
                    <p class="mt-4 text-sm text-gray-500">{{ __('site.rewards.catalog_empty') }}</p>
                @else
                    <div class="mt-4 flex gap-3 overflow-x-auto pb-2 snap-x snap-mandatory lg:grid lg:grid-cols-3 lg:overflow-visible lg:pb-0">
                        @foreach ($catalog as $reward)
                            <div class="snap-start shrink-0 w-[min(78vw,18rem)] lg:w-auto h-full min-h-[9.5rem] rounded-2xl ring-1 ring-brand/15 bg-white px-4 py-4 flex flex-col">
                                <p class="text-xs uppercase tracking-widest font-bold text-brand">{{ number_format($reward['points']) }} pts</p>
                                <p class="mt-1 font-bold text-gray-900">{{ $reward['label'] }}</p>
                                @if (! empty($reward['description']))
                                    <p class="mt-1 text-sm text-gray-600 flex-1">{{ $reward['description'] }}</p>
                                @endif
                                @if (! empty($reward['validity']))
                                    <p class="mt-2 text-xs text-gray-500">{{ $reward['validity'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
</x-site.layout>
