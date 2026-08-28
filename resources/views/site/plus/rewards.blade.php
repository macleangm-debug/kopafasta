<x-site.borrower-layout :title="brand_title(__('plus.home.rewards'))" active="plus">
    <div class="space-y-5">
        <x-site.plus-nav />
        @include('site.borrower.engagement._rewards-panel')
    </div>
</x-site.borrower-layout>
