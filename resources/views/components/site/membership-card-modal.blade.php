{{-- Auto-opens after membership fee payment. CTA routes to Membership (card home). --}}
@props(['customer'])

@php
    $membershipUrl = route('site.borrower.profile', ['section' => 'membership']);
@endphp

<div
    x-data="{ open: true }"
    x-cloak
    x-show="open"
    class="fixed inset-0 z-[10070] flex items-end sm:items-center justify-center p-0 sm:p-6"
    role="dialog"
    aria-modal="true"
    aria-labelledby="membership-card-modal-title"
>
    <div class="absolute inset-0 bg-black/60" @click="open = false" aria-hidden="true"></div>

    <div class="relative w-full sm:max-w-lg max-h-[92vh] overflow-y-auto rounded-t-3xl sm:rounded-3xl bg-[#faf8f5] shadow-2xl ring-1 ring-black/10">
        <div class="sticky top-0 z-10 flex items-start justify-between gap-3 px-5 pt-5 pb-3 bg-[#faf8f5]/sm:rounded-t-3xl">
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">{{ brand_name() }}</p>
                <h2 id="membership-card-modal-title" class="text-lg font-bold text-gray-900 mt-0.5">
                    {{ __('borrower.membership.card_ready_title') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">{{ __('borrower.membership.card_ready_body') }}</p>
            </div>
            <button type="button"
                    @click="open = false"
                    class="shrink-0 rounded-xl p-2 text-gray-500 hover:bg-gray-100"
                    aria-label="{{ __('borrower.membership.close_card') }}">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <div class="px-5 pb-4">
            <x-site.member-card :customer="$customer" />
        </div>

        <div class="sticky bottom-0 px-5 pb-5 pt-2 bg-gradient-to-t from-[#faf8f5] via-[#faf8f5] to-transparent space-y-2">
            <a href="{{ $membershipUrl }}"
               data-loading="click"
               class="w-full inline-flex items-center justify-center rounded-xl bg-brand-gold text-brand font-bold px-5 py-3.5 text-sm shadow-sm hover:brightness-95">
                {{ __('borrower.membership.view_card') }}
            </a>
            <button type="button"
                    @click="open = false"
                    class="w-full inline-flex items-center justify-center rounded-xl bg-white text-gray-700 font-semibold px-5 py-3 text-sm ring-1 ring-gray-200 hover:bg-gray-50">
                {{ __('borrower.membership.close_card') }}
            </button>
        </div>
    </div>
</div>
