<!--- Public member verification (QR destination). Locale follows country default or user switcher. -->
@php
    /** @var \App\Models\Customer|null $customer */
    $logoUrl = brand('logo_mark_url') ?: brand('logo_url') ?: 'images/brand/kopafasta-mark.png';
    $name = $customer
        ? strtoupper(trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')))
        : null;
    $statusLabel = $customer?->membershipStatusLabel();
    $statusColor = $customer?->membershipStatusColor() ?? 'slate';
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
    $issued = optional($customer?->membership_issued_at)->format('d M Y') ?? '—';
    $expires = optional($customer?->membership_expires_at)->format('d M Y') ?? '—';
    $days = $customer ? max(0, (int) $customer->membershipDaysRemaining()) : 0;
    $initial = $name ? strtoupper(substr($name, 0, 1)) : '?';
    $photoUrl = null;
    if ($customer) {
        $facePhoto = app(\App\Services\FaceVerificationService::class)->latestByAngle($customer)->get('front');
        $photoUrl = $facePhoto?->file_path ? asset('storage/'.$facePhoto->file_path) : null;
    }
    $joinUrl = route('site.register.borrower');
@endphp

<x-site.layout :title="brand_title(__('site.member_verify.page_title'))">
    <section class="relative min-h-[70vh] py-10 sm:py-14 px-4">
        <div class="absolute inset-0 bg-gradient-to-b from-[#0B3D32]/10 via-white to-[#f7faf8] pointer-events-none"></div>
        <div class="relative max-w-md mx-auto space-y-4">
            <div class="text-center mb-1">
                <h1 class="text-lg font-semibold tracking-tight text-gray-900">{{ __('site.member_verify.heading') }}</h1>
            </div>

            @if ($verified && $customer)
                <div class="relative overflow-hidden rounded-[1.35rem] bg-gradient-to-br {{ $bgGradient }} text-white shadow-[0_24px_60px_rgba(8,47,39,0.35)] p-5 sm:p-6 ring-1 ring-brand-gold/35">
                    <div class="absolute inset-[1px] rounded-[1.28rem] ring-1 ring-white/10 pointer-events-none"></div>
                    <div class="absolute -right-16 -top-16 h-52 w-52 rounded-full bg-brand-gold/10 pointer-events-none"></div>
                    <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-brand-gold via-[#ffe9a3] to-brand-gold pointer-events-none"></div>

                    <div class="relative flex items-center justify-between gap-3 mb-6">
                        <span class="inline-flex items-center gap-2.5 min-w-0">
                            <img src="{{ asset(ltrim((string) $logoUrl, '/')) }}" alt="" aria-hidden="true" class="h-12 w-auto object-contain shrink-0">
                            <span class="text-2xl sm:text-3xl font-bold tracking-tight text-white leading-none truncate">{{ brand_name() }}</span>
                        </span>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-[0.14em] {{ $badgeClass }} shrink-0 ring-1 ring-brand-gold/30">
                            {{ $statusLabel }}
                        </span>
                    </div>

                    <div class="relative flex items-start gap-4">
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="" class="size-20 rounded-2xl object-cover ring-2 ring-brand-gold/50 shrink-0">
                        @else
                            <div class="size-20 rounded-2xl bg-white/10 ring-2 ring-brand-gold/40 grid place-items-center text-2xl font-bold shrink-0">{{ $initial }}</div>
                        @endif
                        <div class="min-w-0 pt-0.5">
                            <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold leading-none">{{ __('borrower.membership.member_role') }}</p>
                            <h2 class="mt-1 text-xl font-bold tracking-wide leading-[1.1] break-words">{{ $name ?: '—' }}</h2>
                        </div>
                    </div>

                    <div class="relative mt-5 flex items-center gap-3 rounded-2xl bg-black/30 px-4 py-3.5 ring-1 ring-brand-gold/35">
                        <span class="size-10 rounded-full bg-brand-gold text-brand grid place-items-center shrink-0 shadow-sm" aria-hidden="true">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-white leading-tight">{{ __('site.member_verify.verified_badge') }}</p>
                            <p class="mt-0.5 text-[11px] text-brand-gold/90">{{ __('site.member_verify.verified_hint') }}</p>
                        </div>
                    </div>

                    <div class="relative mt-4 rounded-2xl bg-black/25 px-4 py-4 ring-1 ring-white/15">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-white/55 mb-2">{{ __('borrower.membership.member_no_label') }}</p>
                        <p class="font-mono text-xl font-bold tracking-[0.12em] break-all">{{ $memberNo }}</p>
                    </div>

                    <div class="relative mt-4 grid grid-cols-3 gap-3">
                        <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10">
                            <p class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">{{ __('borrower.membership.issued_label') }}</p>
                            <p class="mt-1.5 text-sm font-semibold tabular-nums leading-tight">{{ $issued }}</p>
                        </div>
                        <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10">
                            <p class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">{{ __('borrower.membership.expires_label') }}</p>
                            <p class="mt-1.5 text-sm font-semibold tabular-nums leading-tight">{{ $expires }}</p>
                        </div>
                        <div class="rounded-xl bg-brand-gold/15 px-3 py-3 ring-1 ring-brand-gold/40">
                            <p class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">{{ __('site.member_verify.days_left_label') }}</p>
                            <p class="mt-1.5 text-sm font-bold tabular-nums leading-tight">{{ $days }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-[1.35rem] bg-brand text-white p-5 shadow-lg relative overflow-hidden ring-1 ring-brand-gold/25">
                    <div class="absolute inset-0 opacity-25 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_55%)] pointer-events-none"></div>
                    <div class="relative">
                        <p class="text-[11px] uppercase tracking-[0.16em] text-brand-gold font-semibold">{{ __('site.member_verify.join_eyebrow') }}</p>
                        <h3 class="mt-1.5 text-lg font-bold tracking-tight">{{ __('site.member_verify.join_title') }}</h3>
                        <p class="mt-1.5 text-sm text-white/80">{{ __('site.member_verify.join_body') }}</p>
                        <a href="{{ $joinUrl }}"
                           class="mt-4 inline-flex items-center justify-center w-full sm:w-auto bg-brand-gold hover:brightness-95 text-brand font-bold px-5 py-2.5 rounded-xl text-sm">
                            {{ __('site.member_verify.join_cta') }}
                        </a>
                    </div>
                </div>
            @elseif ($customer)
                <div class="relative overflow-hidden rounded-[1.35rem] bg-gradient-to-br {{ $bgGradient }} text-white shadow-xl p-6 ring-1 ring-brand-gold/25">
                    <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-brand-gold via-[#ffe9a3] to-brand-gold"></div>
                    <div class="relative flex items-center gap-2.5 mb-5">
                        <img src="{{ asset(ltrim((string) $logoUrl, '/')) }}" alt="" class="h-9 w-auto object-contain">
                        <span class="text-xl font-bold tracking-tight">{{ brand_name() }}</span>
                    </div>
                    <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-semibold">{{ __('site.member_verify.inactive_title') }}</p>
                    <h2 class="mt-2 text-xl font-bold tracking-wide">{{ $name ?: '—' }}</h2>
                    <p class="mt-2 font-mono text-sm text-white/85">{{ $memberNo }}</p>
                    <p class="mt-4 text-sm text-white/80">{{ __('site.member_verify.inactive_body') }}</p>
                </div>

                <a href="{{ $joinUrl }}"
                   class="inline-flex items-center justify-center w-full bg-brand-gold hover:brightness-95 text-brand font-bold px-5 py-3 rounded-xl text-sm shadow-sm">
                    {{ __('site.member_verify.join_cta') }}
                </a>
            @else
                <div class="rounded-[1.35rem] bg-white ring-1 ring-brand/10 shadow-sm p-8 text-center">
                    <div class="mx-auto mb-4 size-14 rounded-full bg-brand-muted text-brand grid place-items-center text-2xl font-bold" aria-hidden="true">?</div>
                    <h2 class="text-xl font-bold text-gray-900">{{ __('site.member_verify.not_found_title') }}</h2>
                    <p class="mt-2 text-sm text-gray-600">{{ __('site.member_verify.not_found_body', ['member' => $memberNo]) }}</p>
                    <a href="{{ $joinUrl }}"
                       class="mt-5 inline-flex items-center justify-center bg-brand hover:bg-brand-light text-white font-bold px-5 py-2.5 rounded-xl text-sm">
                        {{ __('site.member_verify.join_cta') }}
                    </a>
                </div>
            @endif
        </div>
    </section>
</x-site.layout>
