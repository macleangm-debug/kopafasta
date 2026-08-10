<x-site.borrower-layout :title="brand_title(__('site.card_verify.page_title'))" active="dashboard" content-width="narrow">
    <x-site.borrower-page-header
        :eyebrow="__('borrower.nav.verify')"
        :title="__('site.card_verify.heading')"
        :subtitle="__('site.card_verify.subtitle')"
    />

    @include('site.public._card-verify-body', [
        'types' => $types,
        'result' => $result,
        'selectedType' => $selectedType,
        'number' => $number,
        'showForm' => $showForm,
        'formAction' => route('site.borrower.verify.lookup'),
        'verifyAnotherUrl' => route('site.borrower.verify'),
        'embedded' => true,
    ])
</x-site.borrower-layout>
