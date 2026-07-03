<x-site.borrower-layout :title="brand_title(__('borrower.loans_page.title'))" active="loans" content-width="wide" :portalMode="($isGuarantorPortal ?? false) ? 'guarantor' : 'borrower'">

    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">{{ __('borrower.loans_page.title') }}</p>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __('borrower.loans_page.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">
        @if (($showGuaranteedTab ?? false) && ($activeTab ?? '') === 'guaranteed')
            {{ __('borrower.loans_page.guaranteed_hint') }}
        @elseif (($showGuarantorTab ?? false) && ($activeTab ?? '') === 'guarantor')
            {{ __('borrower.guarantor.pending_requests_hint') }}
        @else
            {{ __('borrower.loans_page.subtitle') }}
        @endif
        </p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 border-b border-gray-200/80 pb-3">
        @include('site.borrower.loans._tabs', [
            'activeTab' => $activeTab ?? 'applications',
            'viewMode' => $viewMode ?? 'cards',
            'inline' => true,
            'showGuarantorTab' => $showGuarantorTab ?? false,
            'showGuaranteedTab' => $showGuaranteedTab ?? false,
        ])
        <a href="{{ route('site.borrower.apply') }}"
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
