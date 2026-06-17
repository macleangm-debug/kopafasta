<x-site.borrower-layout :title="brand_title(__('borrower.notifications.page_title'))" active="notifications" content-width="wide">

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold mb-1">{{ __('borrower.notifications.page_title') }}</h1>
            <p class="text-sm text-gray-500">{{ __('borrower.notifications.page_subtitle') }}</p>
        </div>
        @if ($items->total() > 0)
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('site.borrower.notifications.read') }}">
                    @csrf
                    <button class="text-xs font-semibold px-4 py-2 rounded-full ring-1 ring-gray-200 bg-white hover:bg-gray-50">{{ __('borrower.notifications.mark_all_read') }}</button>
                </form>
                <form method="POST" action="{{ route('site.borrower.notifications.clear-all') }}"
                      @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.notifications.clear_all_confirm_title')), message: @js(__('borrower.notifications.clear_all_confirm_message')), confirmLabel: @js(__('borrower.notifications.clear_all')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                    @csrf
                    <button class="text-xs font-semibold px-4 py-2 rounded-full ring-1 ring-red-200 text-red-700 bg-red-50 hover:bg-red-100">{{ __('borrower.notifications.clear_all') }}</button>
                </form>
            </div>
        @endif
    </div>

    @if ($items->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-gray-100 grid place-items-center text-gray-400">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9z"/></svg>
            </div>
            <p class="text-gray-500">{{ __('borrower.notifications.empty') }}</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($items as $n)
                @php
                    $actionUrl = ($n->channel === 'in_app' && filled($n->recipient) && str_starts_with($n->recipient, '/'))
                        ? $n->recipient
                        : null;
                    $lines = preg_split("/\r\n|\n|\r/", (string) ($n->message ?: '')) ?: [];
                    $title = trim($lines[0] ?? '') ?: __('borrower.notifications.fallback_title');
                    $body = trim(implode(' ', array_slice($lines, 1))) ?: ($n->message ?: $n->template);
                @endphp
                <div class="bg-white rounded-2xl border border-gray-200 p-5 flex gap-4 {{ $n->read_at ? '' : 'ring-2 ring-amber-100' }}">
                    <div class="size-10 rounded-full shrink-0 grid place-items-center {{ $n->read_at ? 'bg-gray-100 text-gray-500' : 'bg-amber-100 text-amber-700' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9z"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            @if ($n->category)
                                @php
                                    $categoryLabel = __('borrower.notifications.categories.'.$n->category, [], null);
                                    $categoryLabel = $categoryLabel !== 'borrower.notifications.categories.'.$n->category
                                        ? $categoryLabel
                                        : str_replace('_', ' ', $n->category);
                                @endphp
                                <span class="text-[10px] uppercase tracking-widest font-semibold text-gray-500">{{ $categoryLabel }}</span>
                            @endif
                            @unless ($n->read_at)
                                <span class="text-[10px] font-semibold rounded-full px-2 py-0.5 bg-amber-100 text-amber-800">{{ __('borrower.notifications.unread') }}</span>
                            @endunless
                        </div>
                        <p class="text-sm font-semibold text-gray-900">{{ $title }}</p>
                        <p class="text-sm text-gray-700 mt-1">{{ $body }}</p>
                        <p class="text-xs text-gray-400 mt-2">{{ \Carbon\Carbon::parse($n->created_at)->format('d M Y, H:i') }}</p>
                        @if ($actionUrl)
                            <a href="{{ $actionUrl }}" class="inline-flex mt-3 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-4 py-2 rounded-full">
                                {{ __('borrower.notifications.view_application') }}
                            </a>
                        @endif
                    </div>
                    <div class="flex flex-col gap-2 shrink-0">
                        @unless ($n->read_at)
                            <form method="POST" action="{{ route('site.borrower.notifications.item.read', $n) }}">
                                @csrf
                                <button class="text-xs font-semibold text-amber-700 hover:underline">{{ __('borrower.notifications.mark_read') }}</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('site.borrower.notifications.item.clear', $n) }}"
                              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.notifications.clear_item_confirm_title')), message: @js(__('borrower.notifications.clear_item_confirm_message')), confirmLabel: @js(__('borrower.notifications.clear')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                            @csrf @method('DELETE')
                            <button class="text-xs font-semibold text-gray-500 hover:text-red-600">{{ __('borrower.notifications.clear') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $items->links() }}</div>
    @endif

</x-site.borrower-layout>
