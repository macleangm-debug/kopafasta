<x-site.vendor-layout :title="__('site.partner_portal.nav_notifications')" active="notifications">
    <h1 class="text-2xl font-extrabold mb-1">{{ __('site.partner_portal.notifications') }}</h1>
    <p class="text-sm text-gray-500 mb-5">{{ __('site.partner_portal.notifications_subtitle') }}</p>

    @if ($notifications->isEmpty())
        <x-site.empty-state
            icon="🔔"
            :title="__('site.partner_portal.notifications_empty_title')"
            :description="__('site.partner_portal.notifications_empty_desc')"
        />
    @else
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
            <ul class="divide-y divide-gray-100">
                @foreach ($notifications as $n)
                    @php
                        $lines = preg_split("/\r\n|\n|\r/", (string) ($n->message ?? ''), 2);
                        $title = $n->subject ?: ($lines[0] ?? __('site.partner_portal.notifications'));
                        $body = $n->subject ? ($n->message ?? '') : ($lines[1] ?? '');
                        $href = str_starts_with((string) $n->recipient, '/') ? $n->recipient : null;
                    @endphp
                    <li class="px-5 py-4 flex items-start gap-3 {{ $href ? 'hover:bg-gray-50' : '' }}">
                        <span class="size-9 rounded-full bg-brand-muted text-brand grid place-items-center text-xs font-bold uppercase shrink-0">
                            {{ strtoupper(substr($n->channel ?? 'N', 0, 1)) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            @if ($href)
                                <a href="{{ $href }}" class="text-sm font-semibold text-gray-900 hover:text-brand">{{ $title }}</a>
                            @else
                                <p class="text-sm font-semibold text-gray-900">{{ $title }}</p>
                            @endif
                            @if (filled($body))
                                <p class="text-sm text-gray-600 mt-0.5">{{ $body }}</p>
                            @endif
                            <p class="text-[11px] text-gray-400 mt-1 uppercase tracking-wide">
                                {{ $n->created_at?->diffForHumans() }}
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="mt-6">{{ $notifications->links() }}</div>
    @endif
</x-site.vendor-layout>
