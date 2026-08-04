@php
    $ctas = app(\App\Services\NotificationCtaService::class)->resolve($n);
    $acceptUrl = $ctas['accept_url'];
    $declineUrl = $ctas['decline_url'];
    $actionUrl = $ctas['action_url'];
    $actionLabel = $ctas['action_label'];
    $title = $n->displayTitle();
    $body = $n->displayBody();
    $displayCategory = $center->normalizeCategory($n->category);
    $compact = $compact ?? false;
@endphp
<div @class([
    'px-4 py-3 border-b border-gray-50 last:border-0 flex gap-3 transition',
    ! $n->read_at ? 'bg-brand-muted/40' : 'bg-white/90',
    'hover:bg-brand-muted/30',
])>
    <div class="size-9 rounded-full shrink-0 grid place-items-center {{ $n->read_at ? 'bg-gray-100 text-gray-500' : 'bg-brand-muted text-brand' }}">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9z"/></svg>
    </div>
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2 mb-0.5">
            <span class="text-[11px] uppercase tracking-widest font-bold text-brand">{{ $center->categoryLabel($displayCategory) }}</span>
            @unless ($n->read_at)
                <span class="text-[10px] font-semibold rounded-full px-2 py-0.5 bg-brand-gold/40 text-brand">{{ __('borrower.notifications.unread') }}</span>
            @endunless
        </div>
        <p class="text-sm font-semibold text-gray-900">{{ $title }}</p>
        <p class="text-sm text-gray-800 mt-0.5">{{ $body }}</p>
        <p class="text-[11px] text-gray-400 mt-1">{{ \Carbon\Carbon::parse($n->created_at)->format('d M Y, H:i') }}</p>
        @if ($acceptUrl && $declineUrl)
            <div class="mt-2 flex flex-wrap gap-2">
                <a href="{{ $acceptUrl }}"
                   class="inline-flex items-center rounded-lg bg-brand-gold px-3 py-1.5 text-xs font-bold text-brand">
                    {{ $actionLabel ?: __('borrower.guarantor_notifications.accept_cta') }}
                </a>
                <form method="POST" action="{{ $declineUrl }}" class="inline">
                    @csrf
                    <input type="hidden" name="action" value="reject">
                    <button type="submit"
                            class="inline-flex items-center rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50">
                        {{ $ctas['decline_label'] ?: __('borrower.guarantor_notifications.decline_cta') }}
                    </button>
                </form>
            </div>
        @elseif ($actionUrl)
            <a href="{{ $actionUrl }}" class="inline-flex mt-2 text-xs font-semibold text-brand hover:underline">
                {{ $actionLabel }}
            </a>
        @endif
    </div>
    <div class="flex flex-col gap-1.5 shrink-0 items-end">
        @unless ($n->read_at)
            <form method="POST" action="{{ route('site.borrower.notifications.item.read', $n) }}">
                @csrf
                <button class="text-[11px] font-semibold text-brand hover:underline">{{ __('borrower.notifications.mark_read') }}</button>
            </form>
        @endif
        <form method="POST" action="{{ route('site.borrower.notifications.item.clear', $n) }}"
              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.notifications.clear_item_confirm_title')), message: @js(__('borrower.notifications.clear_item_confirm_message')), confirmLabel: @js(__('borrower.notifications.clear')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
            @csrf @method('DELETE')
            <button class="text-[11px] font-semibold text-gray-400 hover:text-red-600">{{ __('borrower.notifications.clear') }}</button>
        </form>
    </div>
</div>
