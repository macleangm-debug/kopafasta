@if (! empty($adminLivePreview))
    <x-admin.layout title="Payment gate preview" heading="Live test — payments.show" :subheading="$payment->reference">
        <div class="mb-4 rounded-2xl bg-brand-muted/50 ring-1 ring-brand/10 px-4 py-3 text-sm text-brand">
            Admin sandbox preview of the borrower payment gate. Actions that require the borrower session may be limited.
        </div>
        @include('site.borrower.payments._show_body')
    </x-admin.layout>
@else
    <x-site.borrower-layout :title="brand_title($payment->reference)" active="payments">
        @include('site.borrower.payments._show_body')
    </x-site.borrower-layout>
@endif
