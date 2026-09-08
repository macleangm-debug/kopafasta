{{--
  Canonical invitee card — guarantor (wizard + detail) and group members.
  Calm premium Kopafasta card: subtle brand accent, badge-only status, no stage rows.
--}}
@props([
    'compact' => false,
])

<div {{ $attributes->class([
    'rounded-2xl bg-white ring-1 ring-brand/15 shadow-sm overflow-hidden',
]) }}>
    <div class="h-1 w-full bg-gradient-to-r from-brand via-brand to-brand-gold/80" aria-hidden="true"></div>
    <div @class([
        'space-y-3 px-4 py-4' => $compact,
        'space-y-4 px-5 py-5' => ! $compact,
    ])>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                @isset($title)
                    <div class="min-w-0">{{ $title }}</div>
                @endisset
                @isset($meta)
                    <div class="mt-1 text-xs text-gray-500">{{ $meta }}</div>
                @endisset
            </div>
            @isset($badges)
                <div class="flex flex-wrap items-center gap-1.5 shrink-0">
                    {{ $badges }}
                </div>
            @endisset
        </div>

        @isset($details)
            <div class="grid sm:grid-cols-2 gap-3 text-sm">
                {{ $details }}
            </div>
        @endisset

        @isset($actions)
            <div class="flex flex-wrap gap-2 pt-0.5">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>
