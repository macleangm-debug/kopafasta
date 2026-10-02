@php
    $isSw = str_starts_with(app()->getLocale(), 'sw');
    $filter = $filter ?? 'all';
@endphp

<x-site.borrower-layout :title="brand_title($isSw ? 'Ujumbe' : 'Messages')" active="dashboard" content-width="wide">

    <x-site.account-shell-hero
        mode="contextual"
        :title="$isSw ? 'Ujumbe' : 'Messages'"
        :body="$isSw ? 'Ujumbe wa kudumu kutoka Kopafasta.' : 'Messages from Kopafasta.'"
    />

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('site.borrower.messages', ['filter' => 'all']) }}"
               @class(['px-3 py-1.5 rounded-full text-xs font-semibold ring-1 transition', $filter === 'all' ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-600 ring-gray-200 hover:bg-brand-muted/40'])>
                {{ $isSw ? 'Zote' : 'All' }}
            </a>
            <a href="{{ route('site.borrower.messages', ['filter' => 'unread']) }}"
               @class(['px-3 py-1.5 rounded-full text-xs font-semibold ring-1 transition', $filter === 'unread' ? 'bg-brand text-white ring-brand' : 'bg-white text-gray-600 ring-gray-200 hover:bg-brand-muted/40'])>
                {{ $isSw ? 'Hazijasomwa' : 'Unread' }}
                @if (($unreadCount ?? 0) > 0)
                    <span class="ml-1 tabular-nums">({{ $unreadCount }})</span>
                @endif
            </a>
        </div>
        @if (($unreadCount ?? 0) > 0)
            <form method="POST" action="{{ route('site.borrower.messages.read') }}">
                @csrf
                <button class="text-xs font-semibold px-4 py-2 rounded-xl ring-1 ring-brand/20 bg-brand-muted text-brand hover:bg-brand-muted/80">
                    {{ $isSw ? 'Weka zote zimesomwa' : 'Mark all read' }}
                </button>
            </form>
        @endif
    </div>

    @if ($messages->isEmpty())
        <x-site.empty-state icon="✉️" :title="$isSw ? 'Hakuna ujumbe bado' : 'No messages yet'" />
    @else
        <div class="rounded-2xl glass-card overflow-hidden ring-1 ring-brand/10 divide-y divide-gray-100">
            @foreach ($messages as $m)
                @php
                    $isUnread = $m->read_at === null;
                    $title = $m->displayTitle();
                    $preview = \Illuminate\Support\Str::limit($m->displayBody(), 120);
                    $cta = app(\App\Services\NotificationCtaService::class)->resolve($m);
                    $actionUrl = $cta['action_url'] ?? null;
                @endphp
                <article class="px-4 py-4 sm:px-5 flex gap-3 {{ $isUnread ? 'bg-brand-muted/25' : '' }}">
                    <div class="mt-1 shrink-0">
                        <span class="block size-2.5 rounded-full {{ $isUnread ? 'bg-brand' : 'bg-transparent ring-1 ring-gray-300' }}" aria-hidden="true"></span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <h2 class="text-sm font-bold text-gray-900 {{ $isUnread ? '' : 'font-semibold' }}">{{ $title !== '' ? $title : ($isSw ? 'Ujumbe' : 'Message') }}</h2>
                            <time class="text-[11px] text-gray-500 tabular-nums shrink-0">{{ optional($m->created_at)->format('d M H:i') }}</time>
                        </div>
                        <p class="mt-0.5 text-[11px] font-semibold uppercase tracking-wide text-gray-500">{{ $messagesService->sourceLabel($m) }}</p>
                        @if ($preview !== '')
                            <p class="mt-1 text-sm text-gray-700 leading-snug">{{ $preview }}</p>
                        @endif
                        <div class="mt-2 flex flex-wrap gap-2">
                            @if ($isUnread)
                                <form method="POST" action="{{ route('site.borrower.messages.item.read', $m) }}">
                                    @csrf
                                    <button class="text-xs font-semibold text-brand hover:underline">{{ $isSw ? 'Weka imesomwa' : 'Mark read' }}</button>
                                </form>
                            @endif
                            @if ($actionUrl)
                                <a href="{{ $actionUrl }}" class="text-xs font-semibold text-brand hover:underline">
                                    {{ $cta['action_label'] ?? ($isSw ? 'Fungua' : 'Open') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

</x-site.borrower-layout>
