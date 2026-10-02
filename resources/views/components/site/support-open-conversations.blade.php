@props([
    'conversations' => null,
    'continueRoute' => null,
    'isSw' => null,
])

@php
    $isSw = $isSw ?? str_starts_with(app()->getLocale(), 'sw');
    $rows = collect($conversations ?? []);
    $svc = app(\App\Services\Support\SupportConversationService::class);
@endphp

@if ($rows->count() === 1)
    @php
        $cnv = $rows->first();
        $unread = $svc->unreadForCustomer($cnv);
        $continueUrl = $continueRoute
            ? route($continueRoute, ['chat' => 1, 'conversation' => $cnv->id, 'section' => 'active'])
            : '#';
    @endphp
    {{-- Single ongoing CNV only — badge when unread staff replies exist --}}
    <div class="sr-only" aria-hidden="true" data-unread="{{ $unread }}"></div>
@elseif ($rows->count() > 1)
    {{-- Legacy multi-open should be reconciled before render; keep chooser as last resort --}}
    <section class="rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm overflow-hidden">
        <div class="px-4 sm:px-5 py-4 bg-gradient-to-br from-brand via-[#0f6b54] to-[#082f27] text-white">
            <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-semibold">
                {{ $isSw ? 'Mazungumzo yako yanayoendelea' : 'Your open conversations' }}
            </p>
            <p class="mt-1 text-sm text-white/80">
                {{ $isSw
                    ? 'Chagua mazungumzo ili kuendelea. Huwezi kuanza jipya hadi haya yatatuliwe.'
                    : 'Choose a conversation to continue. A new one cannot start until these are resolved.' }}
            </p>
        </div>
        <ul class="divide-y divide-gray-100">
            @foreach ($rows as $cnv)
                @php
                    $continueUrl = $continueRoute
                        ? route($continueRoute, ['chat' => 1, 'conversation' => $cnv->id, 'section' => 'active'])
                        : '#';
                    $unread = $svc->unreadForCustomer($cnv);
                @endphp
                <li class="px-4 sm:px-5 py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-[11px] uppercase tracking-widest text-brand font-semibold">{{ $cnv->publicNumber() }}</p>
                            @if ($unread > 0)
                                <span class="inline-flex items-center gap-1 rounded-full bg-brand text-white text-[10px] font-bold px-1.5 py-0.5">● {{ $unread }}</span>
                            @endif
                        </div>
                        <p class="text-sm font-bold text-gray-900 mt-0.5 truncate">
                            {{ app(\App\Services\Support\SupportAutomationService::class)->customerFacingTopicLabel((string) ($cnv->topic ?? ''), 'member', app()->getLocale()) }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ $svc->customerFacingStatusLabel((string) $cnv->status, app()->getLocale()) }}
                            · {{ format_app_datetime($cnv->last_message_at ?? $cnv->updated_at, 'd M Y · H:i') }}
                        </p>
                    </div>
                    <a href="{{ $continueUrl }}"
                       class="shrink-0 inline-flex justify-center rounded-xl bg-brand-gold text-brand font-bold text-sm px-4 py-2.5 shadow-sm">
                        {{ $isSw ? 'Endelea' : 'Continue' }}
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
