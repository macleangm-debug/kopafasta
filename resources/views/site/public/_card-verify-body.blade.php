@php
    /** @var array<string, array{label_key: string, prefix: string, kind: string, category?: string}> $types */
    $logoUrl = brand('logo_mark_url') ?: brand('logo_url') ?: 'images/brand/kopafasta-mark.png';
    $result = $result ?? null;
    $verified = (bool) ($result['verified'] ?? false);
    $found = (bool) ($result['found'] ?? false);
    $statusColor = $result['status_color'] ?? 'slate';
    $bgGradient = match ($statusColor) {
        'green'  => 'from-[#0B3D32] via-[#127A5F] to-[#082f27]',
        'orange' => 'from-[#7a4a10] via-[#b45309] to-[#5c370c]',
        'red'    => 'from-[#7f1d1d] via-[#b91c1c] to-[#450a0a]',
        default  => 'from-slate-700 via-slate-600 to-slate-800',
    };
    $badgeClass = match ($statusColor) {
        'green'  => 'bg-white text-emerald-800',
        'orange' => 'bg-white text-amber-800',
        'red'    => 'bg-white text-rose-800',
        default  => 'bg-white text-slate-800',
    };
    $name = $result['name'] ?? null;
    $initial = $name ? strtoupper(substr($name, 0, 1)) : '?';
    $verifyAnotherUrl = $verifyAnotherUrl ?? route('site.card.verify');
    $formAction = $formAction ?? route('site.card.verify.lookup');
@endphp

<section @class([
        'relative overflow-hidden',
        'min-h-[70vh] py-10 sm:py-14 px-4' => ! ($embedded ?? false),
        'py-2' => $embedded ?? false,
    ])>
    @unless ($embedded ?? false)
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(245,200,66,0.18),_transparent_55%),linear-gradient(180deg,#0B3D3212_0%,#ffffff_42%,#f7faf8_100%)] pointer-events-none"></div>
        <div class="absolute -left-24 top-24 h-64 w-64 rounded-full bg-brand/10 blur-3xl pointer-events-none"></div>
        <div class="absolute -right-16 bottom-10 h-72 w-72 rounded-full bg-brand-gold/20 blur-3xl pointer-events-none"></div>
    @endunless

    <div @class(['relative space-y-4', 'max-w-md mx-auto' => ! ($embedded ?? false)])>
        @if ($showForm ?? true)
            @include('site.public._card-verify-form', [
                'types' => $types,
                'selectedType' => $selectedType ?? 'member',
                'number' => $number ?? '',
                'action' => $formAction,
            ])
        @endif

        @if ($result)
            @if ($verified && $found)
                <div class="relative overflow-hidden rounded-[1.5rem] bg-gradient-to-br {{ $bgGradient }} text-white shadow-[0_28px_70px_rgba(8,47,39,0.38)] p-5 sm:p-6 ring-1 ring-brand-gold/40">
                    <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-brand-gold via-[#ffe9a3] to-brand-gold pointer-events-none"></div>
                    <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-brand-gold/10 pointer-events-none"></div>

                    <div class="relative flex items-center justify-between gap-3 mb-6">
                        <span class="inline-flex items-center gap-2.5 min-w-0">
                            <img src="{{ asset(ltrim((string) $logoUrl, '/')) }}" alt="" aria-hidden="true" class="h-12 w-auto object-contain shrink-0">
                            <span class="text-2xl sm:text-3xl font-bold tracking-tight text-white leading-none truncate">{{ brand_name() }}</span>
                        </span>
                        @if (! empty($result['status_label']))
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-[0.14em] {{ $badgeClass }} shrink-0 ring-1 ring-brand-gold/30">
                                {{ $result['status_label'] }}
                            </span>
                        @endif
                    </div>

                    <div class="relative flex items-start gap-4">
                        @if (! empty($result['photo_url']))
                            <img src="{{ $result['photo_url'] }}" alt="" class="size-20 rounded-2xl object-cover ring-2 ring-brand-gold/50 shrink-0">
                        @else
                            <div class="size-20 rounded-2xl bg-white/10 ring-2 ring-brand-gold/40 grid place-items-center text-2xl font-bold shrink-0">{{ $initial }}</div>
                        @endif
                        <div class="min-w-0 pt-0.5">
                            <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold leading-none">{{ $result['role'] ?? '' }}</p>
                            <h2 class="mt-1 text-xl font-bold tracking-wide leading-[1.1] break-words">{{ $name ?: '—' }}</h2>
                        </div>
                    </div>

                    <div class="relative mt-5 flex items-center gap-3 rounded-2xl bg-black/30 px-4 py-3.5 ring-1 ring-brand-gold/35">
                        <span class="size-10 rounded-full bg-brand-gold text-brand grid place-items-center shrink-0 shadow-sm" aria-hidden="true">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-white leading-tight">{{ __('site.card_verify.verified_badge') }}</p>
                            <p class="mt-0.5 text-[11px] text-brand-gold/90">{{ __('site.card_verify.verified_hint') }}</p>
                        </div>
                    </div>

                    <div class="relative mt-4 rounded-2xl bg-black/25 px-4 py-4 ring-1 ring-white/15">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-white/55 mb-2">{{ __('site.card_verify.id_label') }}</p>
                        <p class="font-mono text-xl font-bold tracking-[0.12em] break-all">{{ $result['id_display'] ?? '—' }}</p>
                    </div>

                    @if (! empty($result['issued']) || ! empty($result['expires']) || $result['days_left'] !== null)
                        <div class="relative mt-4 grid grid-cols-3 gap-3">
                            <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10">
                                <p class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">{{ __('borrower.membership.issued_label') }}</p>
                                <p class="mt-1.5 text-sm font-semibold tabular-nums leading-tight">{{ $result['issued'] ?? '—' }}</p>
                            </div>
                            <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10">
                                <p class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">{{ __('borrower.membership.expires_label') }}</p>
                                <p class="mt-1.5 text-sm font-semibold tabular-nums leading-tight">{{ $result['expires'] ?? '—' }}</p>
                            </div>
                            <div class="rounded-xl bg-brand-gold/15 px-3 py-3 ring-1 ring-brand-gold/40">
                                <p class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">{{ __('site.member_verify.days_left_label') }}</p>
                                <p class="mt-1.5 text-sm font-bold tabular-nums leading-tight">{{ $result['days_left'] ?? '—' }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            @elseif ($found)
                <div class="relative overflow-hidden rounded-[1.5rem] bg-gradient-to-br {{ $bgGradient }} text-white shadow-xl p-6 ring-1 ring-brand-gold/25">
                    <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-brand-gold via-[#ffe9a3] to-brand-gold"></div>
                    <div class="relative flex items-center gap-2.5 mb-5">
                        <img src="{{ asset(ltrim((string) $logoUrl, '/')) }}" alt="" class="h-9 w-auto object-contain">
                        <span class="text-xl font-bold tracking-tight">{{ brand_name() }}</span>
                    </div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">{{ __('site.card_verify.inactive_title') }}</p>
                    <h2 class="mt-2 text-xl font-bold tracking-wide">{{ $name ?: '—' }}</h2>
                    <p class="mt-1 text-xs uppercase tracking-wider text-white/70">{{ $result['role'] ?? '' }}</p>
                    <p class="mt-2 font-mono text-sm text-white/85">{{ $result['id_display'] ?? '' }}</p>
                    <p class="mt-4 text-sm text-white/80">{{ __('site.card_verify.inactive_body') }}</p>
                </div>
            @else
                <div class="rounded-[1.5rem] bg-white/95 backdrop-blur ring-1 ring-brand/10 shadow-[0_20px_50px_rgba(8,47,39,0.1)] p-8 text-center">
                    <div class="mx-auto mb-4 size-14 rounded-full bg-brand-muted text-brand grid place-items-center text-2xl font-bold" aria-hidden="true">?</div>
                    <h2 class="text-xl font-bold text-gray-900">{{ __('site.card_verify.not_found_title') }}</h2>
                    <p class="mt-2 text-sm text-gray-600">{{ __('site.card_verify.not_found_body', ['id' => $result['id_display'] ?? '']) }}</p>
                </div>
            @endif

            <a href="{{ $verifyAnotherUrl }}"
               class="inline-flex items-center justify-center w-full bg-white hover:bg-brand-muted/40 text-brand font-bold px-5 py-3 rounded-xl text-sm ring-1 ring-brand/20 shadow-sm">
                {{ __('site.card_verify.verify_another') }}
            </a>
        @endif
    </div>
</section>
