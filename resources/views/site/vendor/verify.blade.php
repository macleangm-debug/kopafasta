<x-site.vendor-layout :title="brand_title(__('site.card_verify.page_title'))" active="dashboard" hero-key="verify">
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
