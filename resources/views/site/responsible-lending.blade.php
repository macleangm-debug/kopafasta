<x-site.layout :title="brand_title(__('site.responsible_lending.title'))" :description="__('site.responsible_lending.meta')">
    <x-site.public-hero
        variant="compact"
        :eyebrow="__('site.responsible_lending.title')"
        :title="__('site.responsible_lending.title')"
        :body="__('site.responsible_lending.intro')"
        :primary-href="route('site.products')"
        :primary-label="__('site.responsible_lending.cta_products')"
        :secondary-href="route('site.support')"
        :secondary-label="__('site.responsible_lending.cta_support')"
    />

    <x-site.public-section>
        <div class="grid md:grid-cols-2 gap-4">
            <x-site.public-card :title="__('site.responsible_lending.title')">
                <ul class="space-y-2">
                    @foreach (__('site.responsible_lending.principles') as $item)
                        <li class="flex gap-2"><span class="text-brand font-bold">›</span><span>{{ $item }}</span></li>
                    @endforeach
                </ul>
            </x-site.public-card>
            <x-site.public-card :title="__('site.product_detail.fees_heading')">
                <p>{{ __('site.responsible_lending.fees') }}</p>
            </x-site.public-card>
            <x-site.public-card :title="__('site.products.repayment')">
                <p>{{ __('site.responsible_lending.repayment') }}</p>
            </x-site.public-card>
            <x-site.public-card :title="__('site.footer.complaints_heading')">
                <p>{{ __('site.responsible_lending.complaints') }}</p>
                <p class="mt-2">{{ __('site.responsible_lending.privacy') }}</p>
            </x-site.public-card>
        </div>
        <p class="mt-8 text-sm text-gray-500 max-w-3xl">{{ __('site.responsible_lending.more') }}</p>
    </x-site.public-section>
</x-site.layout>
