<x-site.borrower-layout :title="brand_title(__('borrower.loans_page.title'))" active="loans">

    <h1 class="text-2xl font-bold mb-1">{{ __('borrower.loans_page.title') }}</h1>
    <p class="text-sm text-gray-500 mb-6">{{ __('borrower.loans_page.subtitle') }}</p>

    @include('site.borrower.loans._tabs', [
        'activeTab' => $activeTab,
        'pendingGuarantorCount' => ($pendingGuarantorRequests ?? collect())->count(),
        'viewMode' => $viewMode ?? 'cards',
    ])

    @if ($activeTab === 'applications')
        @include('site.borrower.loans._tab-applications')
    @elseif ($activeTab === 'active')
        @include('site.borrower.loans._tab-active')
    @elseif ($activeTab === 'guarantor-requests')
        @include('site.borrower.loans._tab-guarantor-requests')
    @else
        @include('site.borrower.loans._tab-guaranteed')
    @endif

</x-site.borrower-layout>
