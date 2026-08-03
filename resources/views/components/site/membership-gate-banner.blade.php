@props([
    'customer' => null,
])

@php
    $customer = $customer ?? auth()->user()?->customer;
    $needsMembership = $customer
        && ! $customer->isMembershipActive()
        && ! $customer->isMembershipInGrace();
    $hideOnRenew = request()->routeIs('site.membership.renew', 'site.membership.renew.post');
@endphp

@if ($needsMembership && ! $hideOnRenew)
    <div class="sticky top-0 z-40 border-b border-amber-300/80 bg-gradient-to-r from-amber-500 via-amber-400 to-brand-gold text-gray-900 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-gray-900/70">{{ __('borrower.membership.banner_eyebrow') }}</p>
                <p class="text-sm font-semibold mt-0.5">{{ __('borrower.membership.banner_title') }}</p>
                <p class="text-xs text-gray-900/80 mt-0.5">{{ __('borrower.membership.banner_body') }}</p>
            </div>
            <a href="{{ route('site.membership.renew') }}"
               class="shrink-0 inline-flex items-center justify-center rounded-full bg-gray-900 hover:bg-black text-white font-semibold px-5 py-2.5 text-sm shadow-sm">
                {{ __('borrower.membership.banner_cta') }}
            </a>
        </div>
    </div>
@endif
