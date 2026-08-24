<x-site.borrower-layout :title="brand_title(__('plus.welcome.title'))" active="dashboard">
    <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-6 space-y-4">
        <p class="text-xs uppercase tracking-widest text-brand font-semibold">Kopafasta Plus ✦</p>
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('plus.welcome.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('plus.welcome.body') }}</p>
        <a href="{{ route('site.borrower.plus.home') }}" class="inline-flex rounded-xl bg-brand text-white px-5 py-3 font-semibold">{{ __('plus.welcome.open') }}</a>
    </div>
</x-site.borrower-layout>
