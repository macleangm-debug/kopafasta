<x-site.borrower-layout :title="brand_title(__('borrower.loans_page.title'))" active="loans" content-width="wide" :portalMode="($isGuarantorPortal ?? false) ? 'guarantor' : 'borrower'">

    <x-site.borrower-page-header
        :eyebrow="__('borrower.nav.loans')"
        :title="__('borrower.loans_page.title')"
        :subtitle="(($showGuaranteedTab ?? false) && ($activeTab ?? '') === 'guaranteed')
            ? __('borrower.loans_page.guaranteed_hint')
            : ((($showGuarantorTab ?? false) && ($activeTab ?? '') === 'guarantor')
                ? __('borrower.guarantor.pending_requests_hint')
                : __('borrower.loans_page.subtitle'))"
    />

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        @include('site.borrower.loans._tabs', [
            'activeTab' => $activeTab ?? 'applications',
            'viewMode' => $viewMode ?? 'cards',
            'inline' => true,
            'showGuarantorTab' => $showGuarantorTab ?? false,
            'showGuaranteedTab' => $showGuaranteedTab ?? false,
        ])
        <a href="{{ route('site.products') }}"
           class="inline-flex justify-center items-center bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-6 py-2.5 rounded-xl text-sm shrink-0 self-start sm:self-auto shadow-sm">
            {{ __('borrower.loans_page.apply_new_cta') }}
        </a>
    </div>

    @if (($activeTab ?? 'applications') === 'active')
        @include('site.borrower.loans._tab-active', ['loans' => $loans ?? collect()])
    @elseif (($activeTab ?? 'applications') === 'guarantor')
        @include('site.borrower.loans._tab-guarantor-requests', [
            'pendingGuarantorRequests' => $pendingGuarantorRequests ?? collect(),
            'customer' => $customer,
            'guarantorExposure' => $guarantorExposure ?? null,
        ])
    @elseif (($activeTab ?? 'applications') === 'guaranteed')
        @include('site.borrower.loans._tab-guaranteed', [
            'guaranteedLinks' => $guaranteedLinks ?? collect(),
        ])
    @else
        @include('site.borrower.loans._tab-applications', [
            'rows' => $applicationRows ?? [],
            'viewMode' => $viewMode ?? 'cards',
        ])
    @endif

</x-site.borrower-layout>
