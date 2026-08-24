<x-site.vendor-layout :title="brand_title(__('site.card_verify.page_title'))" active="dashboard">
    <x-site.borrower-page-header
        :eyebrow="__('site.partner_portal.cta_verify_member')"
        :title="__('site.card_verify.heading')"
        :subtitle="__('site.card_verify.subtitle')"
    />

    @include('site.public._card-verify-body', [
        'types' => $types,
        'result' => $result,
        'selectedType' => $selectedType,
        'number' => $number,
        'showForm' => $showForm,
        'formAction' => route('site.partner.verify.lookup'),
        'verifyAnotherUrl' => route('site.partner.verify'),
        'embedded' => true,
    ])
</x-site.vendor-layout>
