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
    $initial = $name ? strtoupper(substr($name, 0, 1)) : '?';
    $photoUrl = null;
    if ($customer) {
        $facePhoto = app(\App\Services\FaceVerificationService::class)->latestByAngle($customer)->get('front');
        $photoUrl = $facePhoto?->file_path ? asset('storage/'.$facePhoto->file_path) : null;
    }
@endphp

<x-site.layout :title="brand_title(__('site.member_verify.page_title'))">
    <section class="relative min-h-[70vh] py-10 sm:py-14 px-4">
        <div class="absolute inset-0 bg-gradient-to-b from-[#0B3D32]/08 via-white to-[#f7faf8] pointer-events-none"></div>
        <div class="relative max-w-md mx-auto">
            <div class="text-center mb-5">
                <h1 class="text-lg font-semibold tracking-tight text-gray-900">{{ __('site.member_verify.heading') }}</h1>
            </div>

            @if ($verified && $customer)
                <div class="relative overflow-hidden rounded-[1.35rem] bg-gradient-to-br {{ $bgGradient }} text-white shadow-[0_24px_60px_rgba(8,47,39,0.35)] p-5 sm:p-6 ring-1 ring-brand-gold/35">
                    <div class="absolute inset-[1px] rounded-[1.28rem] ring-1 ring-white/10 pointer-events-none"></div>
                    <div class="absolute -right-16 -top-16 h-52 w-52 rounded-full bg-brand-gold/10 pointer-events-none"></div>
                    <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-brand-gold via-[#ffe9a3] to-brand-gold pointer-events-none"></div>

                    <div class="relative flex items-center justify-between gap-3 mb-6">
                        <span class="inline-flex items-center gap-2.5 min-w-0">
                            <img src="{{ asset(ltrim((string) $logoUrl, '/')) }}" alt="" aria-hidden="true" class="h-10 w-auto object-contain shrink-0">
                            <span class="text-xl sm:text-2xl font-bold tracking-tight text-white leading-none truncate">{{ brand_name() }}</span>
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
                            <p class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-100 bg-emerald-900/30 rounded-lg px-2.5 py-1">
                                <span aria-hidden="true">✓</span>
                                {{ __('site.member_verify.verified_badge') }}
                            </p>
                        </div>
                    </div>

                    <div class="relative mt-5 rounded-2xl bg-black/25 px-4 py-4 ring-1 ring-white/15">
                        <p class="text-[10px] uppercase tracking-[0.2em] text-white/55 mb-2">{{ __('borrower.membership.member_no_label') }}</p>
                        <p class="font-mono text-xl font-bold tracking-[0.12em] break-all">{{ $memberNo }}</p>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 relative">
                        <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10 min-h-[4.5rem]">
                            <dt class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">{{ __('borrower.membership.issued_label') }}</dt>
                            <dd class="mt-1.5 text-sm font-semibold tabular-nums leading-tight">{{ $issued }}</dd>
                        </div>
                        <div class="rounded-xl bg-black/20 px-3 py-3 ring-1 ring-white/10 min-h-[4.5rem]">
                            <dt class="text-[10px] uppercase tracking-wider text-brand-gold font-semibold">{{ __('borrower.membership.expires_label') }}</dt>
                            <dd class="mt-1.5 text-sm font-semibold tabular-nums leading-tight">{{ $expires }}</dd>
                        </div>
                    </dl>
                </div>
            @elseif ($customer)
                <div class="relative overflow-hidden rounded-[1.35rem] bg-gradient-to-br {{ $bgGradient }} text-white shadow-xl p-6 ring-1 ring-white/15">
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
            @else
                <div class="rounded-[1.35rem] bg-white ring-1 ring-gray-200 shadow-sm p-8 text-center">
                    <div class="mx-auto mb-4 size-14 rounded-full bg-gray-100 grid place-items-center text-gray-400 text-2xl" aria-hidden="true">?</div>
                    <h2 class="text-xl font-bold text-gray-900">{{ __('site.member_verify.not_found_title') }}</h2>
                    <p class="mt-2 text-sm text-gray-600">{{ __('site.member_verify.not_found_body', ['member' => $memberNo]) }}</p>
                </div>
            @endif

            <p class="mt-6 text-center text-xs text-gray-500">{{ __('site.member_verify.footer_note') }}</p>
        </div>
    </section>
</x-site.layout>
