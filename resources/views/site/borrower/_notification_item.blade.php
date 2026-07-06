@php
    $actionUrl = ($n->channel === 'in_app' && filled($n->recipient) && str_starts_with($n->recipient, '/'))
        ? $n->recipient
        : null;
    $lines = preg_split("/\r\n|\n|\r/", (string) ($n->message ?: '')) ?: [];
    $title = trim($lines[0] ?? '') ?: __('borrower.notifications.fallback_title');
    $body = trim(implode(' ', array_slice($lines, 1))) ?: ($n->message ?: $n->template);
    $displayCategory = $center->normalizeCategory($n->category);
@endphp
<div @class([
    'glass-card p-5 flex gap-4 transition',
    ! $n->read_at ? 'ring-2 ring-brand-gold/30' : '',
])>
    <div class="size-10 rounded-full shrink-0 grid place-items-center {{ $n->read_at ? 'bg-gray-100 text-gray-500' : 'bg-brand-muted text-brand' }}">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9z"/></svg>
    </div>
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2 mb-1">
            <span class="text-[10px] uppercase tracking-widest font-semibold text-gray-500">{{ $center->categoryLabel($displayCategory) }}</span>
            @unless ($n->read_at)
                <span class="text-[10px] font-semibold rounded-full px-2 py-0.5 bg-brand-gold/30 text-brand">{{ __('borrower.notifications.unread') }}</span>
            @endunless
        </div>
        <p class="text-sm font-semibold text-gray-900">{{ $title }}</p>
        <p class="text-sm text-gray-700 mt-1">{{ $body }}</p>
        <p class="text-xs text-gray-400 mt-2">{{ \Carbon\Carbon::parse($n->created_at)->format('d M Y, H:i') }}</p>
        @if ($actionUrl)
            <a href="{{ $actionUrl }}" class="inline-flex mt-3 text-sm font-semibold text-brand bg-brand-muted hover:bg-brand-muted/80 px-4 py-2 rounded-xl">
                {{ __('borrower.notifications.view_application') }}
            </a>
        @endif
    </div>
    <div class="flex flex-col gap-2 shrink-0">
        @unless ($n->read_at)
            <form method="POST" action="{{ route('site.borrower.notifications.item.read', $n) }}">
                @csrf
                <button class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full bg-brand-muted text-brand hover:bg-brand-muted/80 ring-1 ring-brand/20 transition">
                    {{ __('borrower.notifications.mark_read') }}
                </button>
            </form>
        @endif
        <form method="POST" action="{{ route('site.borrower.notifications.item.clear', $n) }}"
              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.notifications.clear_item_confirm_title')), message: @js(__('borrower.notifications.clear_item_confirm_message')), confirmLabel: @js(__('borrower.notifications.clear')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
            @csrf @method('DELETE')
            <button class="text-xs font-semibold text-gray-500 hover:text-red-600">{{ __('borrower.notifications.clear') }}</button>
        </form>
    </div>
</div>
