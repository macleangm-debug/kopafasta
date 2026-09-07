<x-site.borrower-layout :title="brand_title(__('plus.home.rewards'))" active="plus">
    <div class="space-y-5">
        <x-site.plus-hero kicker="Kopafasta Plus" :title="__('plus.home.rewards')" :body="__('plus.rewards.hero_body')" />
        @include('site.borrower.engagement._rewards-panel')
    </div>
</x-site.borrower-layout>
