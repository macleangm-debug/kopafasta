@props([
    'title' => null,
    'heading' => null,
    'subheading' => null,
    'backUrl' => null,
    'backLabel' => 'Back',
])

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-currency" content="{{ currency_code() }}">
    <meta http-equiv="Permissions-Policy" content="camera=(self), microphone=(), geolocation=(), notifications=(), push=()">
    <title>{{ $title ?? 'Console' }} · {{ brand_name() }}</title>
    <link rel="icon" href="{{ asset(ltrim((string) brand('logo_mark_url', 'images/brand/kopafasta-mark.png'), '/')) }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset(ltrim((string) brand('logo_mark_url', 'images/brand/kopafasta-mark.png'), '/')) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/alpine-init.js'])
    @livewireStyles
    <style>
        [x-cloak]{display:none!important}
        .admin-menu details > summary{list-style:none;cursor:pointer}
        .admin-menu details > summary::-webkit-details-marker{display:none}
        dialog{margin:auto;border:0;padding:0;max-width:calc(100vw - 2rem)}
        dialog::backdrop{background:rgba(0,0,0,.4)}
    </style>
</head>
<body class="h-full bg-[#f4f7f5] text-gray-900 antialiased">

@php
    $currentRoute = request()->route()?->getName();
    $consoleNav = app(\App\Services\ConsoleNavService::class);
    $visibleSections = $consoleNav->visibleSections(auth()->user(), $currentRoute);
    $activeSectionTabs = collect($visibleSections)->firstWhere('isActive')['items'] ?? [];

    $adminAlerts = app(\App\Services\AdminAlertService::class);
    $canManagePartners = $consoleNav->canManagePartners(auth()->user());
    $adminAlertItems = $adminAlerts->alerts(auth()->user());
    $adminPersonalNotifications = collect();
    if (\Illuminate\Support\Facades\Schema::hasColumn('notification_logs', 'user_id') && auth()->id()) {
        $adminPersonalNotifications = \App\Models\NotificationLog::query()
            ->where('user_id', auth()->id())
            ->where('category', 'admin')
            ->latest()
            ->limit(8)
            ->get();
    }
    $adminBellCount = $adminAlertItems->sum(fn ($item) => (int) ($item['count'] ?? 0)) + $adminPersonalNotifications->count();
    $shortcutService = app(\App\Services\AdminShortcutService::class);
    $adminShortcuts = auth()->user() ? $shortcutService->list(auth()->user()) : [];
    $shortcutCandidate = auth()->user() ? $shortcutService->currentCandidate(auth()->user(), $currentRoute) : null;
    $shortcutPinned = $shortcutCandidate ? $shortcutService->isSaved(auth()->user(), $shortcutCandidate['route']) : false;
@endphp

<div class="min-h-screen flex flex-col">

    {{-- Top bar: brand + utilities --}}
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-brand/10 shadow-sm">
        <div class="flex h-14 items-center justify-between gap-4 px-4 lg:px-6"
             x-data="adminGlobalSearch(@js(route('admin.search')))"
             @keydown.window.prevent.meta.k="openSearch()"
             @keydown.window.prevent.ctrl.k="openSearch()">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 shrink-0">
                <x-site.brand-mark size="sm" />
                <div class="hidden sm:block">
                    <div class="text-sm font-semibold text-gray-900 leading-tight">{{ brand_name() }}</div>
                    <div class="text-[11px] text-brand leading-tight font-semibold">Console</div>
                </div>
            </a>

            <div class="flex-1 max-w-xl mx-2 hidden md:block">
                <button type="button" @click="openSearch()"
                        class="w-full flex items-center gap-2 rounded-xl bg-gray-50 ring-1 ring-gray-200 px-3 py-2 text-sm text-gray-500 hover:ring-brand/30">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10 18a8 8 0 100-16 8 8 0 000 16z"/></svg>
                    <span class="flex-1 text-left">Search Kopafasta…</span>
                    <kbd class="hidden lg:inline text-[10px] font-semibold text-gray-400 ring-1 ring-gray-200 rounded px-1.5 py-0.5">⌘K / Ctrl+K</kbd>
                </button>
            </div>
            <button type="button" class="md:hidden p-2 rounded-lg text-gray-600 hover:bg-gray-100"
                    @click="openSearch()" aria-label="Search">
                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10 18a8 8 0 100-16 8 8 0 000 16z"/></svg>
            </button>
            <template x-teleport="body">
                    <div x-show="open" x-cloak class="fixed inset-0 z-[80]" @keydown.escape.window="closeSearch()">
                    <div class="absolute inset-0 bg-black/40" @click="open = false"></div>
                    <div class="relative mx-auto mt-0 md:mt-[8vh] w-full max-w-xl h-full md:h-auto px-0 md:px-4">
                        <div class="rounded-none md:rounded-2xl bg-white shadow-2xl ring-1 ring-black/10 overflow-hidden h-full md:h-auto flex flex-col">
                            <input type="search" x-ref="input" x-model="q" @input.debounce.250ms="run()"
                                   @keydown.arrow-down.prevent="move(1)"
                                   @keydown.arrow-up.prevent="move(-1)"
                                   @keydown.enter.prevent="openActive()"
                                   placeholder="What are you looking for?"
                                   class="w-full border-0 px-4 py-3 text-base outline-none ring-0">
                            <div class="flex-1 max-h-none md:max-h-[60vh] overflow-y-auto border-t border-gray-100">
                                <template x-if="q.trim() === ''">
                                    <div>
                                        <p class="px-4 pt-4 text-[10px] font-bold uppercase tracking-widest text-gray-400">Recent</p>
                                        <template x-for="item in recents" :key="item.url">
                                            <a :href="item.url" class="block px-4 py-2 hover:bg-brand-muted/40" @click="remember(item)">
                                                <p class="text-sm font-semibold text-gray-900" x-text="item.title"></p>
                                                <p class="text-xs text-gray-500" x-text="item.subtitle"></p>
                                            </a>
                                        </template>
                                        <p class="px-4 py-6 text-sm text-gray-500" x-show="recents.length === 0">Type a customer, loan, campaign, or an action like Create demo. ↑ ↓ Enter Esc.</p>
                                    </div>
                                </template>
                                <template x-if="loading">
                                    <p class="px-4 py-6 text-sm text-gray-500">Searching…</p>
                                </template>
                                <template x-if="error && !loading">
                                    <p class="px-4 py-6 text-sm text-red-700">Search failed. Try again.</p>
                                </template>
                                <template x-if="q.trim() !== '' && groups.length === 0 && !loading && !error">
                                    <p class="px-4 py-6 text-sm text-gray-500">No matches you have permission to see.</p>
                                </template>
                                <template x-if="!loading && !error">
                                    <div>
                                <template x-for="group in groups" :key="group.group">
                                    <div class="py-2">
                                        <p class="px-4 text-[10px] font-bold uppercase tracking-widest text-gray-400" x-text="group.group"></p>
                                        <template x-for="(item, idx) in group.items" :key="item.url + item.title">
                                            <a :href="item.url" class="block px-4 py-2"
                                               :class="flat[activeIndex] && flat[activeIndex].url === item.url ? 'bg-brand-muted/60' : 'hover:bg-brand-muted/40'"
                                               @click="remember(item)">
                                                <p class="text-sm font-semibold text-gray-900" x-text="item.title"></p>
                                                <p class="text-xs text-gray-500" x-text="item.subtitle"></p>
                                            </a>
                                        </template>
                                    </div>
                                </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div class="admin-menu flex items-center gap-2 sm:gap-3">
            @if ($shortcutCandidate)
                <form method="post" action="{{ $shortcutPinned ? route('admin.nav.shortcuts.destroy') : route('admin.nav.shortcuts.store') }}"
                      @if ($shortcutPinned)
                          onsubmit="event.preventDefault(); confirmForm(this, { title: 'Remove shortcut?', message: 'It will disappear from ☆ Shortcuts.' })"
                      @endif>
                    @csrf
                    @if ($shortcutPinned) @method('DELETE') @endif
                    <input type="hidden" name="route" value="{{ $shortcutCandidate['route'] }}">
                    <input type="hidden" name="label" value="{{ $shortcutCandidate['label'] }}">
                    <button type="submit" class="p-2 rounded-lg text-gray-600 hover:bg-gray-100" title="{{ $shortcutPinned ? 'Remove shortcut' : 'Add to ☆ Shortcuts (max 6)' }}" aria-label="{{ $shortcutPinned ? 'Remove shortcut' : 'Add shortcut' }}">
                        @if ($shortcutPinned)
                            <span class="text-brand-gold text-lg leading-none">★</span>
                        @else
                            <span class="text-lg leading-none">☆</span>
                        @endif
                    </button>
                </form>
            @endif
                <details class="relative">
                    <summary class="relative inline-flex items-center gap-1.5 p-2 rounded-lg text-gray-600 hover:bg-gray-100 cursor-pointer" aria-label="Alerts">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9z"/></svg>
                        <span class="hidden sm:inline text-xs font-semibold">Alerts</span>
                        @if ($adminBellCount > 0)
                            <span class="absolute -top-0.5 -right-0.5 min-w-[1.125rem] h-[1.125rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold grid place-items-center">{{ $adminBellCount > 9 ? '9+' : $adminBellCount }}</span>
                        @endif
                    </summary>
                    <div class="absolute right-0 top-full mt-2 w-96 rounded-2xl bg-white/95 shadow-xl ring-1 ring-brand/10 overflow-hidden z-50 backdrop-blur max-h-[28rem] overflow-y-auto">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-900">Admin alerts</p>
                        </div>
                        @if ($adminPersonalNotifications->isNotEmpty())
                            <div class="px-4 py-2 bg-brand-muted/40 border-b border-gray-100">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-brand">Assignments</p>
                            </div>
                            @foreach ($adminPersonalNotifications as $note)
                                @php
                                    $lines = preg_split("/\r\n|\n|\r/", (string) $note->message) ?: [];
                                    $noteTitle = $lines[0] ?? 'Update';
                                    $noteBody = trim(implode("\n", array_slice($lines, 1)));
                                    $noteUrl = str_starts_with((string) $note->recipient, '/') ? $note->recipient : null;
                                @endphp
                                <a href="{{ $noteUrl ?: '#' }}" class="block px-4 py-3 hover:bg-brand-muted/30 border-b border-gray-50">
                                    <p class="text-sm text-gray-800 font-medium">{{ $noteTitle }}</p>
                                    @if ($noteBody !== '')
                                        <p class="text-xs text-gray-500 mt-0.5">{{ \Illuminate\Support\Str::limit($noteBody, 120) }}</p>
                                    @endif
                                </a>
                            @endforeach
                        @endif
                        @forelse ($adminAlertItems as $alert)
                            <a href="{{ $alert['url'] }}" class="block px-4 py-3 hover:bg-brand-muted/30 border-b border-gray-50">
                                <p class="text-[11px] font-bold uppercase tracking-widest text-brand">{{ $alert['group'] ?? 'Queue' }}</p>
                                <p class="text-sm text-gray-800 mt-0.5">{{ $alert['label'] }}</p>
                                <p class="text-xs text-brand font-semibold mt-0.5">{{ $alert['count'] }} pending</p>
                            </a>
                        @empty
                            @if ($adminPersonalNotifications->isEmpty())
                                <p class="px-4 py-8 text-sm text-gray-500 text-center">No pending alerts.</p>
                                @if ($canManagePartners)
                                    <p class="px-4 pb-6 text-xs text-gray-400 text-center">When screening asks for a partner in a missing region, it appears here as “Partner needed in …”. Partner support or an admin acts on it.</p>
                                @endif
                            @endif
                        @endforelse
                    </div>
                </details>

                <details class="relative">
                    <summary class="flex items-center gap-2 rounded-full pl-2 pr-1.5 py-1 border border-transparent hover:border-gray-200 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand/30 transition">
                        <span class="text-sm font-medium text-gray-700 hidden md:block max-w-[8rem] truncate">
                            {{ auth()->user()?->name }}
                        </span>
                        <div class="size-8 rounded-full bg-gradient-to-br from-brand-gold to-brand grid place-items-center text-white text-sm font-semibold shadow-sm ring-2 ring-white">
                            {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                        </div>
                        <svg class="size-4 text-gray-400 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </summary>
                    <div class="absolute right-0 top-full mt-2 w-64 bg-white rounded-xl shadow-xl ring-1 ring-black/5 overflow-hidden z-50">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <div class="text-sm font-semibold text-gray-900 truncate">{{ auth()->user()?->name }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ auth()->user()?->email }}</div>
                            <div class="mt-1.5">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold tracking-wide bg-brand-muted text-brand">
                                    {{ auth()->user()?->roleLabel() }}
                                </span>
                            </div>
                        </div>
                        <div class="border-t border-gray-100 py-1">
                            <a href="{{ route('admin.settings.account-security') }}"
                               class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <svg class="size-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                Account security
                            </a>
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </details>
            </div>
        </div>

        {{-- Horizontal main navigation --}}
        <nav class="admin-menu bg-brand border-t border-white/10" aria-label="Main navigation">
            <div class="flex items-stretch gap-0.5 px-2 lg:px-4 overflow-x-auto">
                @foreach ($visibleSections as $section)
                    <a href="{{ route($section['targetRoute']) }}"
                       class="shrink-0 inline-flex items-center px-3 py-2.5 text-sm font-medium whitespace-nowrap rounded-t-lg transition
                              {{ ($section['separated'] ?? false) ? 'ml-auto' : '' }}
                              {{ $section['isActive']
                                   ? 'bg-brand-gold text-brand font-bold'
                                   : 'text-white/85 hover:text-white hover:bg-white/10' }}">
                        {{ $section['label'] }}
                    </a>
                @endforeach
            </div>
        </nav>
        @php
            $workspaceItems = collect($activeSectionTabs)->reject(fn ($item) => ($item[1] ?? '') === '__group__');
            $pinned = $workspaceItems->reject(fn ($item) => ($item[4]['nav'] ?? null) === 'more');
            $more = $workspaceItems->filter(fn ($item) => ($item[4]['nav'] ?? null) === 'more');
        @endphp
        @if ($workspaceItems->count() > 1)
            <div class="bg-white border-b border-brand/10">
                <nav class="flex items-center gap-1 overflow-x-auto px-2 lg:px-4" aria-label="Workspace">
                    @foreach ($pinned as $item)
                        @php
                            $itemRoute = $item[1];
                            $itemQuery = is_array($item[3] ?? null) ? $item[3] : [];
                            $itemActive = $currentRoute === $itemRoute;
                        @endphp
                        <a href="{{ route($itemRoute, $itemQuery) }}"
                           class="shrink-0 whitespace-nowrap px-3 py-2.5 text-sm font-medium border-b-2 -mb-px
                                  {{ $itemActive ? 'border-brand text-brand font-semibold' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                            {{ $item[0] }}
                        </a>
                    @endforeach
                    @if ($more->isNotEmpty())
                        <details class="relative shrink-0">
                            <summary class="px-3 py-2.5 text-sm font-medium text-gray-500 cursor-pointer">More</summary>
                            <div class="absolute left-0 top-full z-40 min-w-[12rem] rounded-xl bg-white shadow-xl ring-1 ring-gray-200 py-1">
                                @foreach ($more as $item)
                                    <a href="{{ route($item[1], is_array($item[3] ?? null) ? $item[3] : []) }}"
                                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">{{ $item[0] }}</a>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </nav>
            </div>
        @endif
        @if (count($adminShortcuts) > 0)
            <div class="bg-white border-b border-brand/10 px-2 lg:px-4 py-2 flex items-center gap-2 overflow-x-auto">
                <p class="text-[10px] uppercase tracking-widest text-gray-400 font-bold shrink-0">☆ Shortcuts {{ count($adminShortcuts) }}/6</p>
                @foreach ($adminShortcuts as $index => $shortcut)
                    <div class="shrink-0 inline-flex items-center gap-1 rounded-full bg-brand-muted text-brand text-xs font-semibold pl-3 pr-1 py-1">
                        @if ($index > 0)
                            <form method="post" action="{{ route('admin.nav.shortcuts.reorder') }}" class="inline">
                                @csrf @method('PUT')
                                @foreach (array_merge(
                                    array_slice($adminShortcuts, 0, $index - 1),
                                    [$adminShortcuts[$index], $adminShortcuts[$index - 1]],
                                    array_slice($adminShortcuts, $index + 1)
                                ) as $item)
                                    <input type="hidden" name="routes[]" value="{{ $item['route'] }}">
                                @endforeach
                                <button class="text-brand/60 hover:text-brand px-0.5" title="Move left">‹</button>
                            </form>
                        @endif
                        <a href="{{ $shortcut['url'] }}">{{ $shortcut['label'] }}</a>
                        @if ($index < count($adminShortcuts) - 1)
                            <form method="post" action="{{ route('admin.nav.shortcuts.reorder') }}" class="inline">
                                @csrf @method('PUT')
                                @foreach (array_merge(
                                    array_slice($adminShortcuts, 0, $index),
                                    [$adminShortcuts[$index + 1], $adminShortcuts[$index]],
                                    array_slice($adminShortcuts, $index + 2)
                                ) as $item)
                                    <input type="hidden" name="routes[]" value="{{ $item['route'] }}">
                                @endforeach
                                <button class="text-brand/60 hover:text-brand px-0.5" title="Move right">›</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('admin.nav.shortcuts.destroy') }}" class="inline"
                              onsubmit="event.preventDefault(); confirmForm(this, { title: 'Remove shortcut?' })">
                            @csrf @method('DELETE')
                            <input type="hidden" name="route" value="{{ $shortcut['route'] }}">
                            <button class="text-brand/50 hover:text-red-600 px-1" title="Remove" aria-label="Remove shortcut">×</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </header>

    {{-- Page content --}}
    <main class="flex-1 p-4 lg:p-6">
        <div class="mb-5">
            @if ($backUrl)
                <a href="{{ $backUrl }}"
                   class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-800 mb-3">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    {{ $backLabel }}
                </a>
            @endif
            @if ($heading !== '')
                <h1 class="text-xl font-semibold text-brand">{{ $heading ?? ($title ?? 'Dashboard') }}</h1>
                @isset($subheading)
                    <p class="text-sm text-gray-600 mt-0.5">{{ $subheading }}</p>
                @endisset
            @endif
        </div>

        {{-- Flash + validation feedback is shown via premium modal (below). --}}

        {{ $slot }}
    </main>
</div>

<x-site.feedback-modal name="admin" title="Console" />
<x-site.confirm-modal name="admin" />
<script>
    window.showAdminFeedback = (detail = {}) => {
        window.dispatchEvent(new CustomEvent('open-feedback-admin', {
            detail: typeof detail === 'string' ? { message: detail } : detail,
        }));
    };
    window.confirmForm = (form, detail = {}) => {
        const tone = detail.tone
            || (String(detail.confirmClass || '').includes('red') ? 'warning' : 'confirm');
        window.dispatchEvent(new CustomEvent('open-confirm-admin', {
            detail: { form: form || null, tone, ...detail },
        }));
    };
    window.confirmAction = (detail = {}) => window.confirmForm(null, detail);
    document.addEventListener('DOMContentLoaded', () => {
        @if (session('feedback'))
            @php $feedback = session('feedback'); @endphp
            window.showAdminFeedback({
                tone: @js($feedback['tone'] ?? 'info'),
                title: @js($feedback['title'] ?? 'Console'),
                message: @js($feedback['message'] ?? ''),
                lines: @js($feedback['lines'] ?? []),
            });
        @elseif (session('status'))
            @php
                $statusMessage = (string) session('status');
                $statusTone = str_contains(strtolower($statusMessage), 'fail')
                    || str_contains(strtolower($statusMessage), 'error')
                    ? 'error'
                    : 'success';
            @endphp
            window.showAdminFeedback({
                tone: @js($statusTone),
                title: @js($statusTone === 'error' ? __('borrower.feedback.tones.error') : __('borrower.feedback.tones.success')),
                message: @js($statusMessage),
            });
        @endif
        @if (session('error'))
            window.showAdminFeedback({
                tone: 'error',
                title: @js(__('borrower.feedback.tones.error')),
                message: @js(session('error')),
            });
        @endif
        @if ($errors instanceof \Illuminate\Support\ViewErrorBag && $errors->any())
            window.showAdminFeedback({
                tone: 'error',
                title: @js(__('borrower.layout.form_errors')),
                message: '',
                lines: @js($errors->all()),
            });
        @endif
    });
</script>

{{-- Centered document preview popup (underwriting) --}}
<div id="kf-doc-drawer" class="fixed inset-0 z-[100] hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-black/50" onclick="window.kfCloseDocumentPreview()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4 sm:p-8 pointer-events-none">
        <div class="pointer-events-auto w-full max-w-3xl max-h-[90vh] bg-white rounded-2xl shadow-2xl ring-1 ring-black/10 flex flex-col overflow-hidden"
             role="dialog" aria-modal="true" aria-labelledby="kf-doc-drawer-title">
            <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-200 bg-gray-50 shrink-0">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">Document preview</p>
                    <p id="kf-doc-drawer-title" class="text-sm font-semibold text-gray-900 truncate"></p>
                    <p id="kf-doc-drawer-meta" class="text-xs text-gray-500 truncate hidden"></p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a id="kf-doc-drawer-open-tab" href="#" target="_blank" rel="noopener"
                       class="text-xs font-semibold text-brand hover:text-brand-light px-3 py-1.5 rounded-lg ring-1 ring-brand/15 bg-white">
                        Open original
                    </a>
                    <button type="button" id="kf-doc-drawer-close" onclick="window.kfCloseDocumentPreview()"
                            class="text-xs font-bold text-slate-700 hover:text-slate-900 px-3 py-1.5 rounded-lg ring-1 ring-slate-200 bg-white">
                        Back to Review
                    </button>
                </div>
            </div>
            <div class="flex-1 min-h-0 bg-gray-100 p-3 overflow-auto" style="min-height: 280px; max-height: calc(90vh - 4rem);">
                <iframe id="kf-doc-drawer-frame" class="hidden w-full rounded-lg bg-white ring-1 ring-gray-200" style="height: min(70vh, 640px);" title="Document preview"></iframe>
                <div id="kf-doc-drawer-image-wrap" class="hidden w-full flex items-center justify-center">
                    <img id="kf-doc-drawer-image" alt="" class="max-w-full max-h-[70vh] rounded-lg shadow-sm ring-1 ring-gray-200 object-contain">
                </div>
            </div>
            <div class="sm:hidden px-4 py-3 border-t border-gray-200 bg-white shrink-0">
                <button type="button" id="kf-doc-drawer-close-mobile" onclick="window.kfCloseDocumentPreview()"
                        class="w-full rounded-xl bg-brand text-white font-bold text-sm py-3">Back to Review</button>
            </div>
        </div>
    </div>
</div>

@livewireScripts
<script>
window.kfLockBodyScroll = function () {
    if (document.body.dataset.kfScrollLocked === '1') return;
    var y = window.scrollY || window.pageYOffset || 0;
    document.body.dataset.kfScrollY = String(y);
    document.body.dataset.kfScrollLocked = '1';
    document.body.style.position = 'fixed';
    document.body.style.top = '-' + y + 'px';
    document.body.style.left = '0';
    document.body.style.right = '0';
    document.body.style.width = '100%';
};

window.kfUnlockBodyScroll = function () {
    if (document.body.dataset.kfScrollLocked !== '1') {
        document.body.classList.remove('overflow-hidden');
        return;
    }
    var y = parseInt(document.body.dataset.kfScrollY || '0', 10) || 0;
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.left = '';
    document.body.style.right = '';
    document.body.style.width = '';
    delete document.body.dataset.kfScrollLocked;
    delete document.body.dataset.kfScrollY;
    document.body.classList.remove('overflow-hidden');
    window.scrollTo(0, y);
};

window.kfOpenDocumentPreview = function (url, title, type) {
    var drawer = document.getElementById('kf-doc-drawer');
    var frame = document.getElementById('kf-doc-drawer-frame');
    var imageWrap = document.getElementById('kf-doc-drawer-image-wrap');
    var image = document.getElementById('kf-doc-drawer-image');
    var titleEl = document.getElementById('kf-doc-drawer-title');
    var openTab = document.getElementById('kf-doc-drawer-open-tab');
    var closeBtn = document.getElementById('kf-doc-drawer-close');
    var closeMobile = document.getElementById('kf-doc-drawer-close-mobile');
    var panel = drawer ? drawer.querySelector('[role="dialog"]') : null;

    if (! drawer) return;

    var fromReview = !!document.querySelector('[data-guided-review]');
    titleEl.textContent = title || 'Document';
    openTab.href = url;
    var closeLabel = fromReview ? 'Back to Review' : 'Close';
    if (closeBtn) {
        closeBtn.textContent = closeLabel;
    }
    if (closeMobile) {
        closeMobile.textContent = closeLabel;
    }
    if (panel) {
        if (window.matchMedia('(max-width: 640px)').matches) {
            panel.classList.remove('max-w-3xl', 'max-h-[90vh]', 'rounded-2xl');
            panel.classList.add('max-w-none', 'h-full', 'rounded-none');
        } else {
            panel.classList.add('max-w-3xl', 'max-h-[90vh]', 'rounded-2xl');
            panel.classList.remove('max-w-none', 'h-full', 'rounded-none');
        }
    }

    var urlLower = String(url || '').toLowerCase();
    if (type === 'pdf' || urlLower.indexOf('.pdf') !== -1 || urlLower.indexOf('loan-agreements') !== -1 || urlLower.indexOf('rejection-letter') !== -1) {
        frame.classList.remove('hidden');
        imageWrap.classList.add('hidden');
        frame.src = url;
    } else {
        frame.classList.add('hidden');
        frame.removeAttribute('src');
        imageWrap.classList.remove('hidden');
        image.src = url;
        image.alt = title || 'Document';
    }

    drawer.classList.remove('hidden');
    drawer.setAttribute('aria-hidden', 'false');
    window.kfLockBodyScroll();
};

window.kfCloseDocumentPreview = function () {
    var drawer = document.getElementById('kf-doc-drawer');
    var frame = document.getElementById('kf-doc-drawer-frame');
    if (! drawer) return;
    drawer.classList.add('hidden');
    drawer.setAttribute('aria-hidden', 'true');
    if (frame) {
        frame.removeAttribute('src');
    }
    window.kfUnlockBodyScroll();
};

document.addEventListener('click', function (event) {
    var openBtn = event.target.closest('[data-open-dialog]');
    if (openBtn) {
        var dialog = document.getElementById(openBtn.getAttribute('data-open-dialog'));
        if (dialog && typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
        return;
    }
    var closeBtn = event.target.closest('[data-close-dialog]');
    if (closeBtn) {
        var closeDialog = document.getElementById(closeBtn.getAttribute('data-close-dialog'));
        if (closeDialog && typeof closeDialog.close === 'function') {
            closeDialog.close();
        }
        return;
    }
    document.querySelectorAll('.admin-menu details[open]').forEach(function (details) {
        if (! details.contains(event.target)) {
            details.removeAttribute('open');
        }
    });
});
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        window.kfCloseDocumentPreview();
        document.querySelectorAll('.admin-menu details[open]').forEach(function (details) {
            details.removeAttribute('open');
        });
        document.querySelectorAll('dialog[open]').forEach(function (dialog) {
            dialog.close();
        });
    }
});
</script>
<x-admin.number-format-script />
@stack('scripts')
</body>
</html>
