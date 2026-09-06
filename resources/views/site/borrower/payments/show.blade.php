@if (! empty($adminLivePreview))
    <x-admin.layout title="Payment gate preview" heading="Live test — payment.show" :subheading="$payment->reference">
        <div class="mb-4 rounded-2xl bg-brand-muted/50 ring-1 ring-brand/10 px-4 py-3 text-sm text-brand">
            <p class="font-semibold">Controlled integration rehearsal</p>
            <p class="mt-1 text-brand/80">Same payment.show gate borrowers use. Initiate PayIn only with the Pay now CTA below.</p>
        </div>
        <div class="pb-16 sm:pb-20">
            @include('site.borrower.payments._show_body')
        </div>
    </x-admin.layout>
@else
    <x-site.borrower-layout :title="brand_title($payment->reference)" active="payments">
        <div class="pb-16 sm:pb-20">
            @include('site.borrower.payments._show_body')
        </div>
    </x-site.borrower-layout>
@endif
