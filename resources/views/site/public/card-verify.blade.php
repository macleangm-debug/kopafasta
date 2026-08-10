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
    $prefixes = collect($types)->mapWithKeys(fn ($meta, $key) => [$key => $meta['prefix']])->all();
@endphp

<x-site.layout :title="brand_title(__('site.card_verify.page_title'))">
    <section class="relative min-h-[70vh] py-10 sm:py-14 px-4">
        <div class="absolute inset-0 bg-gradient-to-b from-[#0B3D32]/10 via-white to-[#f7faf8] pointer-events-none"></div>
        <div class="relative max-w-md mx-auto space-y-4">

            @if ($showForm)
                <div class="rounded-[1.35rem] bg-white ring-1 ring-brand/10 shadow-sm p-5 sm:p-6"
                     x-data="{
                        type: @js($selectedType),
                        prefixes: @js($prefixes),
                        get prefix() { return this.prefixes[this.type] || '' }
                     }">
                    <p class="text-[11px] uppercase tracking-[0.16em] text-brand font-semibold">{{ __('site.card_verify.eyebrow') }}</p>
                    <h1 class="mt-1.5 text-2xl font-bold tracking-tight text-gray-900">{{ __('site.card_verify.heading') }}</h1>
                    <p class="mt-2 text-sm text-gray-600">{{ __('site.card_verify.subtitle') }}</p>

                    <form method="POST" action="{{ route('site.card.verify.lookup') }}" class="mt-5 space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.card_verify.type_label') }}</label>
                            <select name="type" x-model="type" required
                                    class="w-full rounded-xl border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:ring-brand">
                                @foreach ($types as $key => $meta)
                                    <option value="{{ $key }}" @selected($selectedType === $key)>{{ __($meta['label_key']) }}</option>
                                @endforeach
                            </select>
                            @error('type') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.card_verify.number_label') }}</label>
                            <div class="flex rounded-xl overflow-hidden ring-1 ring-gray-200 focus-within:ring-brand">
                                <span class="inline-flex items-center px-3 bg-brand-muted/60 text-brand text-xs sm:text-sm font-mono font-semibold border-r border-gray-200 whitespace-nowrap"
                                      x-text="prefix"></span>
                                <input type="text"
                                       name="number"
                                       value="{{ $number }}"
                                       required
                                       maxlength="24"
                                       autocomplete="off"
                                       spellcheck="false"
                                       inputmode="text"
                                       placeholder="{{ __('site.card_verify.number_placeholder') }}"
                                       class="flex-1 border-0 px-3 py-2.5 text-sm font-mono tracking-wider uppercase focus:ring-0">
                            </div>
                            <p class="mt-1.5 text-[11px] text-gray-500">{{ __('site.card_verify.number_hint') }}</p>
                            @error('number') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <button type="submit"
                                class="w-full inline-flex items-center justify-center bg-brand hover:bg-brand-light text-white font-bold px-5 py-3 rounded-xl text-sm">
                            {{ __('site.card_verify.submit') }}
                        </button>
                    </form>
                </div>
            @endif

            @if ($result)
                @if ($verified && $found)
                    <div class="relative overflow-hidden rounded-[1.35rem] bg-gradient-to-br {{ $bgGradient }} text-white shadow-[0_24px_60px_rgba(8,47,39,0.35)] p-5 sm:p-6 ring-1 ring-brand-gold/35">
                        <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-brand-gold via-[#ffe9a3] to-brand-gold pointer-events-none"></div>

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
                    <div class="relative overflow-hidden rounded-[1.35rem] bg-gradient-to-br {{ $bgGradient }} text-white shadow-xl p-6 ring-1 ring-brand-gold/25">
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
                    <div class="rounded-[1.35rem] bg-white ring-1 ring-brand/10 shadow-sm p-8 text-center">
                        <div class="mx-auto mb-4 size-14 rounded-full bg-brand-muted text-brand grid place-items-center text-2xl font-bold" aria-hidden="true">?</div>
                        <h2 class="text-xl font-bold text-gray-900">{{ __('site.card_verify.not_found_title') }}</h2>
                        <p class="mt-2 text-sm text-gray-600">{{ __('site.card_verify.not_found_body', ['id' => $result['id_display'] ?? '']) }}</p>
                    </div>
                @endif

                <a href="{{ route('site.card.verify') }}"
                   class="inline-flex items-center justify-center w-full bg-white hover:bg-brand-muted/40 text-brand font-bold px-5 py-3 rounded-xl text-sm ring-1 ring-brand/20 shadow-sm">
                    {{ __('site.card_verify.verify_another') }}
                </a>
            @endif
        </div>
    </section>
</x-site.layout>
