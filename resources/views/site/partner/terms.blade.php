<x-site.vendor-layout :title="brand_title($title)" active="profile">
    <x-site.borrower-page-header
        :eyebrow="brand_name()"
        :title="$title"
        :subtitle="__('partner_terms.required')"
    />

    <div class="max-w-2xl space-y-5">
        @if ($accepted)
            <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">
                {{ __('partner_terms.already_accepted') }}
                · {{ $accepted->accepted_at?->format('d M Y') }}
                · v{{ $accepted->agreement_version }} / policy v{{ $accepted->policy_version }}
            </div>
            <article class="glass-card p-6 prose prose-sm max-w-none whitespace-pre-line">{{ $accepted->rendered_text }}</article>
        @else
            <article class="glass-card p-6 prose prose-sm max-w-none whitespace-pre-line">{{ $rendered }}</article>
            <form method="POST" action="{{ route('site.partner.terms.accept') }}" class="glass-card p-6 space-y-4"
                  x-data
                  @submit.prevent="window.confirmForm($el, {
                      title: @js($title),
                      message: @js(__('partner_terms.accept')),
                      confirmLabel: @js(__('partner_terms.accept_button')),
                      tone: 'confirm',
                  })">
                @csrf
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="partner_terms_accepted" value="1" required class="mt-1 rounded border-gray-300 text-brand">
                    {{ __('partner_terms.accept') }}
                </label>
                <button type="submit" class="w-full bg-brand hover:bg-brand-light text-white font-semibold px-5 py-3 rounded-xl text-sm">
                    {{ __('partner_terms.accept_button') }}
                </button>
            </form>
        @endif
    </div>
</x-site.vendor-layout>
