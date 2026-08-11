@php
    $hasItems = collect($groups)->flatten(1)->isNotEmpty();
    $groupLabels = [
        'today' => __('borrower.notifications.groups.today'),
        'yesterday' => __('borrower.notifications.groups.yesterday'),
        'earlier' => __('borrower.notifications.groups.earlier'),
    ];
@endphp

<x-site.borrower-layout :title="brand_title(__('borrower.notifications.page_title'))" active="notifications" content-width="wide">

    <div class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div class="min-w-0">
            <p class="text-xs uppercase tracking-widest text-brand font-bold mb-1">{{ __('borrower.nav.notifications') }}</p>
            <h1 class="text-2xl sm:text-3xl font-bold text-brand tracking-tight">{{ __('borrower.notifications.page_title') }}</h1>
            <p class="text-sm text-gray-600 mt-1">{{ __('borrower.notifications.page_subtitle') }}</p>
        </div>
        @if ($unreadCount > 0)
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <form method="POST" action="{{ route('site.borrower.notifications.read') }}">
                    @csrf
                    <button class="text-xs font-semibold px-4 py-2 rounded-xl ring-1 ring-brand/20 bg-brand-muted text-brand hover:bg-brand-muted/80">{{ __('borrower.notifications.mark_all_read') }}</button>
                </form>
                <form method="POST" action="{{ route('site.borrower.notifications.clear-all') }}"
                      @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.notifications.clear_all_confirm_title')), message: @js(__('borrower.notifications.clear_all_confirm_message')), confirmLabel: @js(__('borrower.notifications.clear_all')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                    @csrf
                    <button class="text-xs font-semibold px-4 py-2 rounded-xl ring-1 ring-red-200 text-red-700 bg-red-50 hover:bg-red-100">{{ __('borrower.notifications.clear_all') }}</button>
                </form>
            </div>
        @endif
    </div>

    <div class="mb-6 flex flex-wrap gap-2">
        <a href="{{ route('site.borrower.notifications') }}"
           @class(['px-3 py-1.5 rounded-full text-xs font-semibold ring-1 transition', $category === 'all' ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-600 ring-gray-200 hover:bg-brand-muted/40'])>
            {{ __('borrower.notifications.all_categories') }}
        </a>
        @foreach ($categories as $cat)
            <a href="{{ route('site.borrower.notifications', ['category' => $cat]) }}"
               @class(['px-3 py-1.5 rounded-full text-xs font-semibold ring-1 transition', $category === $cat ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-600 ring-gray-200 hover:bg-brand-muted/40'])>
                {{ $center->categoryLabel($cat) }}
            </a>
        @endforeach
    </div>

    @unless ($hasItems)
        <x-site.empty-state icon="🔔" :title="__('borrower.notifications.empty')" />
    @else
        <div class="rounded-2xl glass-card overflow-hidden ring-1 ring-brand/10">
            @foreach ($groups as $groupKey => $items)
                @continue($items->isEmpty())
                <div class="px-4 py-2.5 bg-brand-muted/40 border-b border-brand/10">
                    <h2 class="text-[11px] uppercase tracking-widest text-brand font-bold">{{ $groupLabels[$groupKey] ?? $groupKey }}</h2>
                </div>
                <div>
                    @foreach ($items as $n)
                        @include('site.borrower._notification_item', ['n' => $n, 'center' => $center, 'compact' => true])
                    @endforeach
                </div>
            @endforeach
        </div>
    @endunless

</x-site.borrower-layout>
