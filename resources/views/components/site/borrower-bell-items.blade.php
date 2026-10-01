@props([
    'items' => [],
    'compact' => false,
])

@php
    $pad = $compact ? 'px-5 py-3' : 'px-4 py-3';
@endphp

@forelse ($items as $item)
    @php
        $read = (bool) ($item['read'] ?? false);
        $title = (string) ($item['title'] ?? '');
        $body = (string) ($item['body'] ?? $item['message'] ?? '');
        $category = (string) ($item['category_label'] ?? $item['category'] ?? '');
        $when = (string) ($item['when'] ?? '');
        $actionUrl = $item['action_url'] ?? null;
        $actionLabel = $item['action_label'] ?? __('borrower.notifications.view_application');
        $acceptUrl = $item['accept_url'] ?? null;
        $declineUrl = $item['decline_url'] ?? null;
        $declineLabel = $item['decline_label'] ?? __('borrower.guarantor_notifications.decline_cta');
    @endphp
    <div class="{{ $pad }} border-b border-gray-50 hover:bg-brand-muted/30 {{ $read ? '' : 'bg-brand-muted/50' }}">
        @if ($category !== '')
            <p class="text-[11px] font-bold uppercase tracking-widest text-brand">{{ $category }}</p>
        @endif
        @if ($title !== '')
            <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $title }}</p>
        @endif
        @if ($body !== '')
            <p class="text-sm text-gray-800 mt-0.5">{{ $body }}</p>
        @endif
        @if ($when !== '')
            <p class="text-[11px] text-gray-400 mt-1">{{ $when }}</p>
        @endif
        @if ($acceptUrl && $declineUrl)
            <div class="mt-2 flex flex-wrap gap-2">
                <a href="{{ $acceptUrl }}" class="inline-flex items-center rounded-lg bg-brand-gold px-3 py-1.5 text-xs font-bold text-brand">
                    {{ $actionLabel }}
                </a>
                <form action="{{ $declineUrl }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50">
                        {{ $declineLabel }}
                    </button>
                </form>
            </div>
        @elseif ($actionUrl)
            <a href="{{ $actionUrl }}" @click="sheetOpen = false" class="inline-flex mt-2 text-xs font-semibold text-brand hover:underline">
                {{ $actionLabel }}
            </a>
        @endif
    </div>
@empty
    <p class="{{ $compact ? 'px-5 py-10' : 'px-4 py-8' }} text-sm text-gray-500 text-center">{{ __('borrower.layout.no_notifications') }}</p>
@endforelse
