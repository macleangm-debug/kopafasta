<x-site.affiliate-layout :title="brand_title(__('affiliate_terms.title'))" active="profile">
    <x-site.borrower-page-header
        :eyebrow="brand_name()"
        :title="__('affiliate_terms.title')"
        :subtitle="$accepted ? __('affiliate_terms.already_accepted') : __('affiliate_terms.required')"
    />

    <div class="max-w-3xl space-y-5">
        @if ($accepted)
            <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">
                {{ __('affiliate_terms.already_accepted') }}
                · {{ $accepted->accepted_at?->format('d M Y') }}
                · v{{ $accepted->agreement_version }} / policy v{{ $accepted->policy_version }}
            </div>
            <x-site.branded-agreement :header="$header" :sections="$sections" />
        @else
            <x-site.branded-agreement :header="$header" :sections="$sections" />
            <form method="POST" action="{{ route('site.affiliate.terms.accept') }}" class="glass-card p-6 space-y-4"
                  x-data
                  @submit.prevent="window.confirmForm($el, {
                      title: @js(__('affiliate_terms.title')),
                      message: @js(__('affiliate_terms.accept')),
                      confirmLabel: @js(__('affiliate_terms.accept_button')),
                      tone: 'confirm',
                  })">
                @csrf
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="affiliate_terms_accepted" value="1" required class="mt-1 rounded border-gray-300 text-brand">
                    {{ __('affiliate_terms.accept') }}
                </label>
                <button type="submit" class="w-full bg-brand hover:bg-brand-light text-white font-semibold px-5 py-3 rounded-xl text-sm">
                    {{ __('affiliate_terms.accept_button') }}
                </button>
            </form>
        @endif
    </div>
</x-site.affiliate-layout>
