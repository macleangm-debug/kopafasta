<x-site.layout :title="brand_title(__('site.card_verify.page_title'))">
    @include('site.public._card-verify-body', [
        'types' => $types,
        'result' => $result,
        'selectedType' => $selectedType,
        'number' => $number,
        'showForm' => $showForm,
        'formAction' => route('site.card.verify.lookup'),
        'verifyAnotherUrl' => route('site.card.verify'),
    ])
</x-site.layout>
