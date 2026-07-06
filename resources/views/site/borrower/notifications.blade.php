<x-site.borrower-layout :title="brand_title(__('borrower.notifications.page_title'))" active="notifications" content-width="wide">

    <x-site.borrower-page-header
        :eyebrow="__('borrower.nav.notifications')"
        :title="__('borrower.notifications.page_title')"
        :subtitle="__('borrower.notifications.page_subtitle')">
        <x-slot:actions>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('site.borrower.notifications.read') }}">
                    @csrf
                    <button class="text-xs font-semibold px-4 py-2 rounded-xl ring-1 ring-gray-200/80 bg-white/80 hover:bg-brand-muted/40">{{ __('borrower.notifications.mark_all_read') }}</button>
                </form>
                <form method="POST" action="{{ route('site.borrower.notifications.clear-all') }}"
                      @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.notifications.clear_all_confirm_title')), message: @js(__('borrower.notifications.clear_all_confirm_message')), confirmLabel: @js(__('borrower.notifications.clear_all')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                    @csrf
                    <button class="text-xs font-semibold px-4 py-2 rounded-xl ring-1 ring-red-200 text-red-700 bg-red-50 hover:bg-red-100">{{ __('borrower.notifications.clear_all') }}</button>
                </form>
            @endif
        </x-slot:actions>
    </x-site.borrower-page-header>

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

    <x-site.page-loading-shell>
        <x-slot:skeleton>
            @for ($i = 0; $i < 4; $i++)
                <x-site.skeleton-card :lines="3" class="mb-3" />
            @endfor
        </x-slot:skeleton>

    @php
        $hasItems = collect($groups)->flatten(1)->isNotEmpty();
        $groupLabels = [
            'today' => __('borrower.notifications.groups.today'),
            'yesterday' => __('borrower.notifications.groups.yesterday'),
            'earlier' => __('borrower.notifications.groups.earlier'),
        ];
    @endphp

    @unless ($hasItems)
        <x-site.empty-state icon="🔔" :title="__('borrower.notifications.empty')" />
    @else
        <div class="space-y-8">
            @foreach ($groups as $groupKey => $items)
                @continue($items->isEmpty())
                <section>
                    <h2 class="text-xs uppercase tracking-widest text-gray-500 font-bold mb-3">{{ $groupLabels[$groupKey] ?? $groupKey }}</h2>
                    <div class="space-y-3">
                        @foreach ($items as $n)
                            @include('site.borrower._notification_item', ['n' => $n, 'center' => $center])
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endunless

    </x-site.page-loading-shell>

</x-site.borrower-layout>
