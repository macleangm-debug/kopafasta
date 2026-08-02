@php
    $alreadyMember = auth()->check() && auth()->user()?->customer;
@endphp
<x-site.guarantor-invite-shell
    :title="brand_title(__('borrower.apply.group.invite_declined_title'))"
    :eyebrow="__('borrower.apply.group.invite_declined_title')"
    :heading="__('borrower.apply.group.invite_declined_cta_title')"
    :lede="__('borrower.apply.group.invite_declined_upsell')"
>
    <div class="text-center mb-6">
        <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-brand-muted text-2xl text-brand ring-1 ring-brand/20">✓</div>
        <h1 class="text-2xl font-bold tracking-tight mb-2 text-brand">{{ __('borrower.apply.group.invite_declined_title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('borrower.apply.group.invite_declined_thanks') }}</p>
    </div>

    <div class="flex flex-col gap-3">
        @if ($alreadyMember)
            <a href="{{ route('site.borrower.apply') }}"
               class="inline-flex justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-3 rounded-xl text-sm">
                {{ __('borrower.apply.group.invite_declined_cta_apply') }}
            </a>
        @else
            <a href="{{ route('site.register.borrower') }}"
               class="inline-flex justify-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-5 py-3 rounded-xl text-sm">
                {{ __('borrower.apply.group.invite_declined_cta_member') }}
            </a>
            <a href="{{ route('site.products') }}"
               class="inline-flex justify-center bg-white ring-1 ring-brand/25 hover:bg-brand-muted/40 text-brand font-semibold px-5 py-3 rounded-xl text-sm">
                {{ __('borrower.apply.group.invite_declined_cta_apply') }}
            </a>
        @endif
        <a href="{{ route('site.home') }}"
           class="inline-flex justify-center text-sm font-semibold text-brand/70 hover:text-brand py-1">
            {{ __('borrower.apply.group.invite_declined_cta_not_now') }}
        </a>
    </div>
</x-site.guarantor-invite-shell>
