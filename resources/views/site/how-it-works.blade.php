@php
    $journey = [
        ['key' => 'register', 'title' => __('site.how_it_works.journey.register_title'), 'body' => __('site.how_it_works.journey.register_body')],
        ['key' => 'product', 'title' => __('site.how_it_works.journey.product_title'), 'body' => __('site.how_it_works.journey.product_body')],
        ['key' => 'apply', 'title' => __('site.how_it_works.journey.apply_title'), 'body' => __('site.how_it_works.journey.apply_body')],
        ['key' => 'screening', 'title' => __('site.how_it_works.journey.screening_title'), 'body' => __('site.how_it_works.journey.screening_body')],
        ['key' => 'offer', 'title' => __('site.how_it_works.journey.offer_title'), 'body' => __('site.how_it_works.journey.offer_body')],
        ['key' => 'conditions', 'title' => __('site.how_it_works.journey.conditions_title'), 'body' => __('site.how_it_works.journey.conditions_body')],
        ['key' => 'disburse', 'title' => __('site.how_it_works.journey.disburse_title'), 'body' => __('site.how_it_works.journey.disburse_body')],
        ['key' => 'repay', 'title' => __('site.how_it_works.journey.repay_title'), 'body' => __('site.how_it_works.journey.repay_body')],
    ];
@endphp
<x-site.layout :title="brand_title(__('site.how_it_works.title'))">
    <x-site.public-hero
        variant="feature"
        :eyebrow="__('site.how_it_works.title')"
        :title="__('site.how_it_works.title')"
        :body="__('site.how_it_works.subtitle')"
        :primary-href="route('site.register.borrower')"
        :primary-label="__('site.how_it_works.cta')"
    />

    <x-site.public-section>
        <x-site.public-carousel :title="__('site.how_it_works.journey_title')" :subtitle="__('site.how_it_works.journey_subtitle')">
            @foreach ($journey as $i => $step)
                <div data-public-slide class="snap-start shrink-0 w-[min(100%,calc(100vw-3rem))] sm:w-[260px] lg:w-[calc(25%-12px)]">
                    <x-site.public-card :eyebrow="__('site.how_it_works.step_label', ['num' => $i + 1])" :title="$step['title']">
                        {{ $step['body'] }}
                    </x-site.public-card>
                </div>
            @endforeach
        </x-site.public-carousel>

        <div class="mt-10">
            <x-site.public-cta-band
                :title="__('site.how_it_works.cta')"
                :body="__('site.how_it_works.subtitle')"
                :primary-href="route('site.register.borrower')"
                :primary-label="__('site.how_it_works.cta')"
                :secondary-href="route('site.products')"
                :secondary-label="__('site.nav.all_products')"
            />
        </div>
    </x-site.public-section>
</x-site.layout>
